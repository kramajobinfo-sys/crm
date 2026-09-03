<?php

namespace App\Console\Commands;

use App\Jobs\SendReminderEmail;
use App\Models\Reminder;
use App\Notifications\ActivityReminderNotification;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchActivityReminders extends Command
{
    protected $signature = 'activities:dispatch-reminders {--limit=200 : Maximum reminders to process}';

    protected $description = 'Dispatch due activity reminders to their assigned users';

    public function handle(EmailService $emails): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $ids = Reminder::withoutGlobalScopes()->due()->orderBy('remind_at')->limit($limit)->pluck('id');
        $processed = 0;
        $emailQueueFailures = 0;

        foreach ($ids as $id) {
            $reminder = DB::transaction(function () use ($id) {
                $reminder = Reminder::withoutGlobalScopes()->with('user')->lockForUpdate()->find($id);

                if (! $reminder || $reminder->is_sent || $reminder->remind_at->isFuture()) {
                    return null;
                }

                // A deleted/inactive assignee must not leave an unprocessable reminder in the
                // scheduler forever. Active users always receive a durable in-app notification.
                if ($reminder->user?->is_active) {
                    $reminder->user->notifyNow(new ActivityReminderNotification($reminder));
                }

                $reminder->forceFill(['is_sent' => true, 'sent_at' => now()])->save();

                return $reminder;
            });

            if (! $reminder) {
                continue;
            }
            $processed++;

            // Email reminders also remain visible in-app, so a temporary SMTP problem never
            // makes the reminder disappear. Network delivery runs on the queue and cannot hold
            // the scheduler (and every other scheduled CRM job) open.
            if ($reminder->channel === 'email' && $reminder->user?->is_active && $reminder->user->email) {
                try {
                    $email = $emails->compose([
                        'company_id' => $reminder->company_id,
                        'user_id' => $reminder->user_id,
                        'to' => [$reminder->user->email],
                        'subject' => 'Reminder: '.$reminder->title,
                        'body_html' => '<p>'.e($reminder->title).'</p><p>This reminder is now due in Krama CRM.</p>',
                        'related_type' => $reminder->related_type,
                        'related_id' => $reminder->related_id,
                    ], false);
                    SendReminderEmail::dispatch($email->id);
                } catch (\Throwable $e) {
                    $emailQueueFailures++;
                    Log::warning('Activity reminder email could not be prepared.', [
                        'reminder_id' => $reminder->id,
                        'company_id' => $reminder->company_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Dispatched {$processed} due reminder(s); {$emailQueueFailures} email queue failure(s).");

        return self::SUCCESS;
    }
}
