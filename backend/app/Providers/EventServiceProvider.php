<?php
namespace App\Providers;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\UserLoggedIn::class => [\App\Listeners\RecordLoginHistory::class],
        \App\Events\UserLoginFailed::class => [\App\Listeners\RecordFailedLogin::class],
    ];
    public function boot(): void {}
    public function shouldDiscoverEvents(): bool { return false; }
}
