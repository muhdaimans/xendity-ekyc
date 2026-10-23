<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc;

use Illuminate\Support\ServiceProvider;
use XendityEkyc\XendityEkyc\Console\Commands\SimulateCommand;
use XendityEkyc\XendityEkyc\Console\Commands\StatusCommand;

class XendityEkycServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/xendity-ekyc.php', 'xendity-ekyc');

        $this->app->singleton(Gateway::class);

        $this->app->singleton(XendityEkyc::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/xendity-ekyc.php');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/xendity-ekyc.php' => config_path('xendity-ekyc.php'),
        ], ['xendity-ekyc', 'xendity-ekyc-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['xendity-ekyc', 'xendity-ekyc-migrations']);

        $this->commands([
            SimulateCommand::class,
            StatusCommand::class,
        ]);
    }
}
