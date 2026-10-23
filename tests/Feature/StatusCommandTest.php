<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('refreshes a stored transaction', function () {
    Http::fake(['*' => Http::response(encryptedBody(gatewayFixture('status-summary-mykad')))]);
    $transaction = storedTransaction();

    $this->artisan('xendity-ekyc:status', ['reference' => 'onb_123'])
        ->expectsOutputToContain('Completed')
        ->expectsOutputToContain('Approved')
        ->assertSuccessful();

    expect($transaction->refresh()->isApproved())->toBeTrue();
});

it('queries an unknown transaction with an onboarding id', function () {
    Http::fake(['*' => Http::response(encryptedBody(gatewayFixture('status-processing')))]);

    $this->artisan('xendity-ekyc:status', ['reference' => 'XDYORDER-1', '--onboarding-id' => 'onb_123'])
        ->expectsOutputToContain('Processing')
        ->assertSuccessful();
});

it('fails for an unknown transaction without an onboarding id', function () {
    $this->artisan('xendity-ekyc:status', ['reference' => 'missing'])
        ->expectsOutputToContain('--onboarding-id')
        ->assertFailed();
});

it('reports gateway errors', function () {
    Http::fake(['*' => Http::response(['success' => false, 'error_code' => '103'])]);

    $this->artisan('xendity-ekyc:status', ['reference' => 'XDYORDER-1', '--onboarding-id' => 'onb_123'])
        ->expectsOutputToContain('Invalid ref ID')
        ->assertFailed();
});
