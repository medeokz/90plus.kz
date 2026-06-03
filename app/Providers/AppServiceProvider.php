<?php

namespace App\Providers;

use App\Services\CompactMatchesService;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Carbon::setLocale(config('app.locale', 'kk'));

        Paginator::defaultView('vendor.pagination.simple-default');

        View::composer('layouts.app', function ($view) {
            $compactMatches = app(CompactMatchesService::class)->getData();

            $view->with([
                'compactLiveMatches' => $compactMatches['live'],
                'compactUpcomingMatches' => $compactMatches['upcoming'],
            ]);
        });
    }
}
