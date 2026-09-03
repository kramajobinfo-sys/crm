<?php
namespace App\Listeners;
use App\Events\UserLoggedIn;
use App\Models\LoginHistory;
class RecordLoginHistory
{
    public function handle(UserLoggedIn $event): void
    {
        LoginHistory::create([
            'user_id' => $event->user->id, 'ip_address' => $event->ipAddress,
            'user_agent' => substr($event->userAgent ?? '', 0, 500), 'status' => 'success', 'created_at' => now(),
        ]);
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
