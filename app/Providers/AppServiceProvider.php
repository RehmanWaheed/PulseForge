<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\IncidentCreatedEvent;
use App\Events\IncidentResolvedEvent;
use App\Listeners\SendIncidentCreatedAlertListener;
use App\Listeners\SendIncidentResolvedAlertListener;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn (): TenantContext => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            IncidentCreatedEvent::class,
            SendIncidentCreatedAlertListener::class
        );

        Event::listen(
            IncidentResolvedEvent::class,
            SendIncidentResolvedAlertListener::class
        );
    }
}
