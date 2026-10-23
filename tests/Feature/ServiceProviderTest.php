<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use XendityEkyc\XendityEkyc\Facades\XendityEkyc as XendityEkycFacade;
use XendityEkyc\XendityEkyc\Gateway;
use XendityEkyc\XendityEkyc\XendityEkyc;

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
    $this->artisan('vendor:publish', ['--tag' => 'xendity-ekyc-config', '--force' => true])->assertSuccessful();
    $this->artisan('vendor:publish', ['--tag' => 'xendity-ekyc-migrations'])->assertSuccessful();

    expect(config_path('xendity-ekyc.php'))->toBeFile()
        ->and(glob(database_path('migrations/*_create_xendity_ekyc_transactions_table.php')))->not->toBeEmpty();

    @unlink(config_path('xendity-ekyc.php'));
    array_map(unlink(...), glob(database_path('migrations/*_create_xendity_ekyc_transactions_table.php')) ?: []);
});
