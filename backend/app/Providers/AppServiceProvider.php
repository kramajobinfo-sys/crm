<?php
namespace App\Providers;
use App\Http\Middleware\JwtAuthenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        if ($this->app->environment('production')) URL::forceScheme('https');
        RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // tymon/jwt-auth's own provider registers a 'jwt.auth' alias too (Tymon\...\Authenticate),
        // and package providers boot before this one — so its registration otherwise wins over
        // ours from bootstrap/app.php, silently skipping API-key auth and our formatted error
        // responses for every expired/invalid/missing token. Re-assert ours last.
        Route::aliasMiddleware('jwt.auth', JwtAuthenticate::class);

        // Entity-level change auditing (who changed what, old→new). AuditObserver captures
        // created/updated/deleted/restored with a field-level diff and secret scrubbing; register it
        // on the business-critical models the audit trail must cover.
        foreach ([
            \App\Models\Lead::class, \App\Models\Customer::class, \App\Models\Contact::class,
            \App\Models\Deal::class, \App\Models\Quotation::class, \App\Models\SalesOrder::class,
            \App\Models\Invoice::class, \App\Models\Payment::class, \App\Models\Ticket::class,
            \App\Models\User::class, \App\Models\PurchaseOrder::class,
        ] as $model) {
            if (class_exists($model)) $model::observe(\App\Observers\AuditObserver::class);
        }
    }
}
