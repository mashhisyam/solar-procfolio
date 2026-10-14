<?php

namespace App\Providers;

use App\Models\User;
use App\Support\SettingsMemo;
use App\Support\WorkdayCalendar;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(WorkdayCalendar::class);
        $this->app->scoped(SettingsMemo::class);
    }

    public function boot(): void
    {
        Gate::define('edit', fn (User $u) => $u->active && $u->canEdit());
        Gate::define('admin', fn (User $u) => $u->active && $u->isAdmin());

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('azure', \SocialiteProviders\Azure\Provider::class);
        });
    }
}
