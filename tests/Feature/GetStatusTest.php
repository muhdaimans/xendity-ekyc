<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use XendityEkyc\XendityEkyc\Enums\StatusMode;
use XendityEkyc\XendityEkyc\Enums\TransactionResult;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Events\TransactionUpdated;
use XendityEkyc\XendityEkyc\Facades\XendityEkyc;

it('queries the status with the returned ref id', function () {
    Http::fake(['*/get-status' => Http::response(encryptedBody(gatewayFixture('status-detail-passport')))]);

    $status = XendityEkyc::getStatus('XDYORDER-1', 'onb_123', StatusMode::Detail);

    expect($status->result)->toBe(TransactionResult::Rejected)
        ->and($status->mode)->toBe(StatusMode::Detail);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/get-status')
        && decryptedRequest($request)['ref_id'] === 'XDYORDER-1'
        && decryptedRequest($request)['onboarding_id'] === 'onb_123'
        && decryptedRequest($request)['mode'] === 2
        && is_string(decryptedRequest($request)['signature']));
});

it('accepts plain json responses', function () {
    Http::fake(['*' => Http::response(gatewayFixture('status-summary-mykad'))]);

    expect(XendityEkyc::getStatus('XDYORDER-1', 'onb_123')->isApproved())->toBeTrue();
});

it('refreshes a stored transaction and dispatches an event on change', function () {
    Event::fake([TransactionUpdated::class]);
    Http::fake(['*' => Http::sequence()
        ->push(encryptedBody(gatewayFixture('status-detail-passport')))
        ->push(encryptedBody(gatewayFixture('status-detail-passport')))]);

    $transaction = storedTransaction();

    XendityEkyc::refresh($transaction, StatusMode::Detail);
    XendityEkyc::refresh($transaction, StatusMode::Detail);

    $transaction->refresh();

    expect($transaction->status)->toBe(TransactionStatus::Completed)
        ->and($transaction->isRejected())->toBeTrue()
        ->and($transaction->reject_message)->toBe('Step 2 failed')
        ->and($transaction->completed_at)->not->toBeNull()
        ->and(json_encode($transaction->payload))->not->toContain('base64string');

    Event::assertDispatchedTimes(TransactionUpdated::class, 1);
});
