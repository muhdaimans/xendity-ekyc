<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Events\TransactionUpdated;

beforeEach(function () {
    Event::fake([TransactionUpdated::class]);
});

it('applies an encrypted postback', function () {
    $transaction = storedTransaction();

    $this->postJson(route('xendity-ekyc.webhook'), encryptedBody(gatewayFixture('status-summary-mykad')))
        ->assertOk()
        ->assertExactJson(['success' => true]);

    expect($transaction->refresh()->isApproved())->toBeTrue()
        ->and($transaction->status)->toBe(TransactionStatus::Completed);

    Event::assertDispatched(TransactionUpdated::class, fn (TransactionUpdated $event) => $event->transaction->is($transaction)
        && $event->status->isApproved());
});

it('applies a plain json postback', function () {
    storedTransaction();

    $this->postJson(route('xendity-ekyc.webhook'), gatewayFixture('status-summary-mykad'))->assertOk();

    Event::assertDispatchedTimes(TransactionUpdated::class, 1);
});

it('applies a form encoded postback', function () {
    storedTransaction();

    $this->post(route('xendity-ekyc.webhook'), encryptedBody(gatewayFixture('status-summary-mykad')))->assertOk();

    Event::assertDispatchedTimes(TransactionUpdated::class, 1);
});

it('is idempotent for replayed postbacks', function () {
    storedTransaction();

    $this->postJson(route('xendity-ekyc.webhook'), encryptedBody(gatewayFixture('status-summary-mykad')))->assertOk();
    $this->postJson(route('xendity-ekyc.webhook'), encryptedBody(gatewayFixture('status-summary-mykad')))->assertOk();

    Event::assertDispatchedTimes(TransactionUpdated::class, 1);
});

it('rejects unknown transactions', function () {
    $this->postJson(route('xendity-ekyc.webhook'), encryptedBody(gatewayFixture('status-summary-mykad')))
        ->assertForbidden()
        ->assertJson(['success' => false]);
});

it('rejects a mismatched signature', function () {
    storedTransaction(['signature' => 'another-signature']);

    $this->postJson(route('xendity-ekyc.webhook'), encryptedBody(gatewayFixture('status-summary-mykad')))->assertForbidden();

    Event::assertNotDispatched(TransactionUpdated::class);
});

it('rejects a mismatched api key', function () {
    storedTransaction();
    $payload = gatewayFixture('status-summary-mykad');
    $payload['data']['api_key'] = 'someone-else';

    $this->postJson(route('xendity-ekyc.webhook'), encryptedBody($payload))->assertForbidden();
});

it('rejects payloads that cannot be decrypted', function () {
    $this->postJson(route('xendity-ekyc.webhook'), ['api_key' => 'x', 'data' => 'bm90LWVuY3J5cHRlZA=='])
        ->assertBadRequest();
});
