<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use XendityEkyc\XendityEkyc\Facades\XendityEkyc as XendityEkycFacade;
use XendityEkyc\XendityEkyc\Gateway;
use XendityEkyc\XendityEkyc\XendityEkyc;
use XendityEkyc\XendityEkyc\XendityEkycServiceProvider;

it('registers the services as singletons', function () {
    expect(app(XendityEkyc::class))->toBe(app(XendityEkyc::class))
        ->and(app(Gateway::class))->toBe(app(Gateway::class))
        ->and(XendityEkycFacade::getFacadeRoot())->toBe(app(XendityEkyc::class));
});

it('merges the package config', function () {
    expect(config('xendity-ekyc.base_url'))->toBe('https://staging.ekyc.xendity.com/v1/gateway/')
        ->and(config('xendity-ekyc.callback_mode'))->toBe(1)
        ->and(config('xendity-ekyc.webhook.path'))->toBe('xendity-ekyc/webhook');
});

it('registers the webhook route', function () {
    expect(Route::has('xendity-ekyc.webhook'))->toBeTrue()
        ->and(route('xendity-ekyc.webhook', absolute: false))->toBe('/xendity-ekyc/webhook');
});

it('publishes the config and migrations', function () {
    $config = ServiceProvider::pathsToPublish(XendityEkycServiceProvider::class, 'xendity-ekyc-config');
    $migrations = ServiceProvider::pathsToPublish(XendityEkycServiceProvider::class, 'xendity-ekyc-migrations');

    expect($config)->toHaveCount(1)
        ->and(array_key_first($config))->toEndWith('config/xendity-ekyc.php')
        ->and(reset($config))->toBe(config_path('xendity-ekyc.php'))
        ->and($migrations)->toHaveCount(1)
        ->and(glob(array_key_first($migrations).'/*_create_xendity_ekyc_transactions_table.php'))->toHaveCount(1)
        ->and(ServiceProvider::pathsToPublish(XendityEkycServiceProvider::class, 'xendity-ekyc'))->toHaveCount(2);
});
