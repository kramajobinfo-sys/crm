<?php
namespace Database\Seeders;

use App\Models\Call;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $users = User::where('company_id', $cid)
            ->whereIn('email', ['sales.mgr@krama.local', 'sales@krama.local'])
            ->pluck('id', 'email');
        $mgr = $users['sales.mgr@krama.local'] ?? null;
        $rep = $users['sales@krama.local'] ?? $mgr;

        $deal = Deal::withoutGlobalScopes()->where('company_id', $cid)->orderBy('id')->first();
        $lead = Lead::withoutGlobalScopes()->where('company_id', $cid)->orderBy('id')->first();
        $cust = Customer::withoutGlobalScopes()->where('company_id', $cid)->orderBy('id')->first();

        // Idempotency: this seeder creates rows without a natural unique key, so wipe the
        // company's demo activities first rather than piling up duplicates on re-run.
        Task::withoutGlobalScopes()->where('company_id', $cid)->forceDelete();
        Meeting::withoutGlobalScopes()->where('company_id', $cid)->forceDelete();
        Call::withoutGlobalScopes()->where('company_id', $cid)->forceDelete();
        Reminder::withoutGlobalScopes()->where('company_id', $cid)->delete();

        // [title, status, priority, owner, dueInDays, related]
        $tasks = [
            ['Send proposal to Al Futtaim', 'open', 'high', $mgr, 1, $deal],
            ['Follow up on Gulf Home order', 'in_progress', 'medium', $mgr, 3, $deal],
            ['Call back Aisha Rahman', 'open', 'medium', $rep, 0, $lead],
            ['Prepare showroom quote', 'open', 'high', $rep, 2, $deal],
            ['Overdue: chase Ministry tender docs', 'open', 'urgent', $mgr, -2, $deal],
            ['Update customer credit terms', 'done', 'low', $rep, -5, $cust],
        ];
        foreach ($tasks as [$title, $status, $priority, $owner, $dueDays, $rel]) {
            Task::create([
                'company_id' => $cid, 'title' => $title, 'status' => $status, 'priority' => $priority,
                'assigned_to' => $owner, 'created_by' => $mgr,
                'due_at' => now()->addDays($dueDays)->setTime(10, 0),
                'completed_at' => $status === 'done' ? now()->subDays(4) : null,
                'related_type' => $rel ? $rel::class : null, 'related_id' => $rel?->id,
            ]);
        }

        $meetings = [
            ['Al Futtaim fit-out kickoff', 'scheduled', 'Client office, Dubai', $mgr, 1, $deal],
            ['Weekly sales pipeline review', 'scheduled', 'HQ meeting room 2', $mgr, 2, null],
            ['Emaar handover walkthrough', 'completed', 'Emaar hotel lobby', $mgr, -3, $deal],
        ];
        foreach ($meetings as [$title, $status, $loc, $org, $startDays, $rel]) {
            $meeting = Meeting::create([
                'company_id' => $cid, 'title' => $title, 'status' => $status, 'location' => $loc,
                'organizer_id' => $org,
                'start_at' => now()->addDays($startDays)->setTime(14, 0),
                'end_at' => now()->addDays($startDays)->setTime(15, 0),
                'related_type' => $rel ? $rel::class : null, 'related_id' => $rel?->id,
            ]);
            $meeting->participants()->createMany(array_filter([
                $mgr ? ['company_id' => $cid, 'user_id' => $mgr, 'response' => 'accepted'] : null,
                $rep ? ['company_id' => $cid, 'user_id' => $rep, 'response' => 'invited'] : null,
                ['company_id' => $cid, 'name' => 'Client contact', 'email' => 'contact@client.example', 'response' => 'tentative'],
            ]));
        }

        $calls = [
            ['Intro call with Al Futtaim', 'outbound', 'completed', $mgr, 420, -1, $deal],
            ['Missed call from Gulf Home', 'inbound', 'missed', $rep, 0, 0, $deal],
            ['Schedule callback with lead', 'outbound', 'scheduled', $rep, 0, 1, $lead],
        ];
        foreach ($calls as [$subject, $dir, $status, $user, $dur, $whenDays, $rel]) {
            Call::create([
                'company_id' => $cid, 'subject' => $subject, 'direction' => $dir, 'status' => $status,
                'phone' => '+971 4 000 0000', 'duration_seconds' => $dur, 'user_id' => $user,
                'notes' => $status === 'completed' ? 'Discussed scope and next steps.' : null,
                'scheduled_at' => $status === 'scheduled' ? now()->addDays($whenDays)->setTime(11, 0) : null,
                'occurred_at' => $status === 'completed' ? now()->addDays($whenDays)->setTime(11, 0) : null,
                'related_type' => $rel ? $rel::class : null, 'related_id' => $rel?->id,
            ]);
        }

        Reminder::create([
            'company_id' => $cid, 'user_id' => $mgr, 'title' => 'Follow up on Al Futtaim proposal',
            'remind_at' => now()->addDay()->setTime(9, 0), 'channel' => 'in_app',
            'related_type' => $deal ? Deal::class : null, 'related_id' => $deal?->id,
        ]);
        Reminder::create([
            'company_id' => $cid, 'user_id' => $rep, 'title' => 'Call Aisha Rahman back',
            'remind_at' => now()->addHours(3), 'channel' => 'in_app',
            'related_type' => $lead ? Lead::class : null, 'related_id' => $lead?->id,
        ]);

        $this->command?->info('Seeded '.count($tasks).' tasks, '.count($meetings).' meetings, '.count($calls).' calls, 2 reminders.');
    }
}
