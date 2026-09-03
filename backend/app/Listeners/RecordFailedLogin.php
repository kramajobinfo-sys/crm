<?php
namespace App\Listeners;
use App\Events\UserLoginFailed;
use App\Models\LoginHistory;
use App\Models\User;
class RecordFailedLogin
{
    public function handle(UserLoginFailed $event): void
    {
        $userId = User::withoutGlobalScopes()->where('email', $event->email)->value('id');
        LoginHistory::create([
            'user_id' => $userId, 'ip_address' => $event->ipAddress,
            'user_agent' => substr($event->userAgent ?? '', 0, 500),
            'status' => 'failed', 'failure_reason' => $event->reason, 'created_at' => now(),
        ]);
    }
}
