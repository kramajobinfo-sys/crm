<?php

namespace Tests\Feature\Activities;

use App\Jobs\SendReminderEmail;
use App\Models\Company;
use App\Models\Email;
use App\Models\Reminder;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class DispatchActivityRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_reminder_creates_one_notification_and_is_not_dispatched_twice(): void
    {
        [$company, $user] = $this->tenant();
        $reminder = Reminder::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'title' => 'Follow up on Acme quotation',
            'remind_at' => now()->subMinute(),
            'channel' => 'in_app',
        ]);

        $this->artisan('activities:dispatch-reminders')->assertSuccessful();
        $this->artisan('activities:dispatch-reminders')->assertSuccessful();

        $this->assertTrue($reminder->fresh()->is_sent);
        $this->assertNotNull($reminder->fresh()->sent_at);
        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('Follow up on Acme quotation', $user->notifications()->first()->data['message']);
    }

    public function test_future_reminder_is_not_dispatched(): void
    {
        [$company, $user] = $this->tenant();
        $reminder = Reminder::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'title' => 'Future reminder',
            'remind_at' => now()->addHour(),
            'channel' => 'in_app',
        ]);

        $this->artisan('activities:dispatch-reminders')->assertSuccessful();

        $this->assertFalse($reminder->fresh()->is_sent);
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_email_reminder_passes_the_explicit_tenant_to_email_service(): void
    {
        Queue::fake();
        [$company, $user] = $this->tenant();
        Reminder::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'title' => 'Email reminder',
            'remind_at' => now()->subMinute(),
            'channel' => 'email',
        ]);

        $this->mock(EmailService::class, function (MockInterface $mock) use ($company, $user) {
            $email = new Email(['status' => 'draft']);
            $email->id = 912;
            $mock->shouldReceive('compose')->once()->withArgs(function (array $data, bool $send) use ($company, $user) {
                return ! $send && $data['company_id'] === $company->id && $data['to'] === [$user->email];
            })->andReturn($email);
        });

        $this->artisan('activities:dispatch-reminders')->assertSuccessful();
        $this->assertSame(1, $user->notifications()->count());
        Queue::assertPushed(SendReminderEmail::class, fn (SendReminderEmail $job) => $job->emailId === 912);
    }

    /** @return array{Company, User} */
    private function tenant(): array
    {
        $company = Company::create(['name' => 'Tenant A', 'code' => 'TENANT-A', 'is_active' => true]);
        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Reminder User',
            'email' => 'reminders@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        return [$company, $user];
    }
}
