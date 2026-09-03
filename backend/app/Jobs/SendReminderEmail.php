<?php

namespace App\Jobs;

use App\Models\Email;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendReminderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $emailId) {}

    public function handle(EmailService $emails): void
    {
        $email = Email::withoutGlobalScopes()->find($this->emailId);
        if ($email && in_array($email->status, ['draft', 'failed'], true)) {
            $result = $emails->send($email);
            if ($result->status === 'failed') {
                throw new \RuntimeException($result->error ?: 'Reminder email delivery failed.');
            }
        }
    }
}
