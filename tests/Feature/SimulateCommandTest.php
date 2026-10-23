<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Events\TransactionUpdated;

beforeEach(function () {
    Event::fake([TransactionUpdated::class]);
    Http::preventStrayRequests();
});

it('simulates a successful transaction', function () {
    $transaction = storedTransaction();

    $this->artisan('xendity-ekyc:simulate', ['reference' => 'XDYORDER-1', 'outcome' => 'success'])
        ->expectsOutputToContain('Approved')
        ->assertSuccessful();

    $transaction->refresh();

    expect($transaction->isApproved())->toBeTrue()
        ->and($transaction->status)->toBe(TransactionStatus::Completed)
        ->and($transaction->completed_at)->not->toBeNull();

    Event::assertDispatched(TransactionUpdated::class, fn (TransactionUpdated $event) => $event->transaction->is($transaction)
        && $event->status->isApproved());
});

it('simulates a failed transaction with a message', function () {
    $transaction = storedTransaction();

    $this->artisan('xendity-ekyc:simulate', ['reference' => 'onb_123', 'outcome' => 'failed', '--message' => 'Face mismatch'])
        ->expectsOutputToContain('Rejected')
        ->assertSuccessful();

    $transaction->refresh();

    expect($transaction->isRejected())->toBeTrue()
        ->and($transaction->reject_message)->toBe('Face mismatch');

    Event::assertDispatchedTimes(TransactionUpdated::class, 1);
});

it('does not dispatch the event when nothing changes', function () {
    storedTransaction();

    $this->artisan('xendity-ekyc:simulate', ['reference' => 'XDYORDER-1', 'outcome' => 'success'])->assertSuccessful();
    $this->artisan('xendity-ekyc:simulate', ['reference' => 'XDYORDER-1', 'outcome' => 'success'])->assertSuccessful();

    Event::assertDispatchedTimes(TransactionUpdated::class, 1);
});

it('rejects an unknown outcome', function () {
    storedTransaction();

    $this->artisan('xendity-ekyc:simulate', ['reference' => 'XDYORDER-1', 'outcome' => 'maybe'])
        ->expectsOutputToContain('success or failed')
        ->assertExitCode(2);
});

it('fails for an unknown transaction', function () {
    $this->artisan('xendity-ekyc:simulate', ['reference' => 'missing', 'outcome' => 'success'])
        ->expectsOutputToContain('No stored transaction')
        ->assertFailed();
});

it('refuses to run in production', function () {
    $transaction = storedTransaction();
    $this->app['env'] = 'production';

    $this->artisan('xendity-ekyc:simulate', ['reference' => 'XDYORDER-1', 'outcome' => 'success'])
        ->expectsOutputToContain('production')
        ->assertFailed();

    expect($transaction->refresh()->status)->toBe(TransactionStatus::UrlNotOpened);
    Event::assertNotDispatched(TransactionUpdated::class);
});
