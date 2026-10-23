<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use XendityEkyc\XendityEkyc\Http\Controllers\WebhookController;

if (config()->boolean('xendity-ekyc.webhook.enabled', true)) {
    Route::post(config()->string('xendity-ekyc.webhook.path', 'xendity-ekyc/webhook'), WebhookController::class)
        ->middleware(config()->array('xendity-ekyc.webhook.middleware', ['api']))
        ->name('xendity-ekyc.webhook');
}
