<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [];
    public function register(): void
    {
        foreach ($this->bindings as $contract => $impl) $this->app->bind($contract, $impl);
    }
    public function boot(): void {}
}
