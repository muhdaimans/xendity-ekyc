<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Workbench\App\Models\User;
use XendityEkyc\XendityEkyc\Data\CreateTransactionRequest;
use XendityEkyc\XendityEkyc\Enums\DocumentType;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Exceptions\ApiException;
use XendityEkyc\XendityEkyc\Exceptions\XendityEkycException;
use XendityEkyc\XendityEkyc\Facades\XendityEkyc;
use XendityEkyc\XendityEkyc\Support\Signature;

beforeEach(function () {
    $this->travelTo('2026-10-23 02:00:00');
});

function fakeCreateTransaction(): void
{
    Http::fake([
        'staging.ekyc.xendity.com/v1/gateway/create-transaction' => Http::response(encryptedBody([
            'api_key' => 'ApiKey0123456789abcdefghijklmnop',
            'package_name' => 'Xendity',
            'ref_id' => 'XDYORDER-1001',
            'signature' => 'echoed',
            'onboarding_id' => 'onb_abc',
            'onboarding_url' => 'https://staging.ekyc.xendity.com/#/guideline?token=abc',
            'expired_at' => '2026-10-23 15:00:00',
        ])),
    ]);
}

it('creates and stores a transaction', function () {
    fakeCreateTransaction();

    $user = User::query()->create(['name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'secret']);

    $transaction = XendityEkyc::createTransaction(new CreateTransactionRequest(
        documentName: 'ALI BIN ABU',
        documentNumber: '900101015555',
        refId: 'ORDER-1001',
        remark: 'Loan application',
    ), $user);

    expect($transaction->exists)->toBeTrue()
        ->and($transaction->client_ref_id)->toBe('ORDER-1001')
        ->and($transaction->ref_id)->toBe('XDYORDER-1001')
        ->and($transaction->onboarding_id)->toBe('onb_abc')
        ->and($transaction->onboarding_url)->toContain('token=abc')
        ->and($transaction->status)->toBe(TransactionStatus::UrlNotOpened)
        ->and($transaction->document_type)->toBe(DocumentType::MyKad)
        ->and($transaction->expired_at?->toDateTimeString())->toBe('2026-10-23 07:00:00')
        ->and($transaction->kycable->is($user))->toBeTrue()
        ->and($user->latestEkycTransaction?->is($transaction))->toBeTrue();

    Http::assertSent(function (Request $request) use ($transaction) {
        $payload = decryptedRequest($request);
        $signature = (new Signature('ApiKey0123456789abcdefghijklmnop', 'Xendity', 'Xendity883929'))
            ->generate('ORDER-1001', '2026-10-23 10:00:00');

        return $request->url() === 'https://staging.ekyc.xendity.com/v1/gateway/create-transaction'
            && $request->hasHeader('Content-Type', 'application/json')
            && $request['api_key'] === 'ApiKey0123456789abcdefghijklmnop'
            && $payload['ref_id'] === 'ORDER-1001'
            && $payload['package_name'] === 'Xendity'
            && $payload['document_type'] === '1'
            && $payload['platform'] === 'Web'
            && $payload['request_time'] === '2026-10-23 10:00:00'
            && $payload['signature'] === $signature
            && $transaction->signature === $signature
            && $payload['response_url'] === 'https://merchant.test/ekyc/done'
            && $payload['backend_url'] === route('xendity-ekyc.webhook')
            && $payload['callback_mode'] === 1
            && $payload['response_mode'] === 1
            && $payload['remark'] === 'Loan application'
            && ! array_key_exists('product_info', $payload);
    });
});

it('generates a ref id and disables callbacks without a backend url', function () {
    fakeCreateTransaction();
    config()->set('xendity-ekyc.webhook.enabled', false);

    $transaction = XendityEkyc::createTransaction(new CreateTransactionRequest('ALI BIN ABU', '900101015555', DocumentType::Passport));

    expect(strlen($transaction->client_ref_id))->toBe(26);

    Http::assertSent(fn (Request $request) => decryptedRequest($request)['callback_mode'] === 0
        && ! array_key_exists('backend_url', decryptedRequest($request))
        && decryptedRequest($request)['document_type'] === '3');
});

it('requires a response url', function () {
    config()->set('xendity-ekyc.response_url', null);

    XendityEkyc::createTransaction(new CreateTransactionRequest('ALI BIN ABU', '900101015555'));
})->throws(XendityEkycException::class, 'response URL');

it('requires credentials', function () {
    config()->set('xendity-ekyc.api_key', null);

    XendityEkyc::createTransaction(new CreateTransactionRequest('ALI BIN ABU', '900101015555'));
})->throws(XendityEkycException::class, '[xendity-ekyc.api_key]');

it('maps gateway error codes', function () {
    Http::fake(['*' => Http::response(['success' => false, 'error_code' => '104'], 400)]);

    try {
        XendityEkyc::createTransaction(new CreateTransactionRequest('ALI BIN ABU', '900101015555'));
    } catch (ApiException $exception) {
        expect($exception->errorCode)->toBe('104')
            ->and($exception->error()?->name)->toBe('InvalidSignature')
            ->and($exception->status)->toBe(400)
            ->and($exception->getMessage())->toContain('Invalid security token');

        return;
    }

    $this->fail('Expected an ApiException.');
});

it('maps encrypted error responses', function () {
    Http::fake(['*' => Http::response(encryptedBody(['error_code' => '101']))]);

    XendityEkyc::createTransaction(new CreateTransactionRequest('ALI BIN ABU', '900101015555'));
})->throws(ApiException::class, 'Invalid API key');

it('fails on server errors after retrying', function () {
    Http::fake(['*' => Http::response('Bad gateway', 502)]);

    expect(fn () => XendityEkyc::createTransaction(new CreateTransactionRequest('ALI BIN ABU', '900101015555')))
        ->toThrow(ApiException::class);

    Http::assertSentCount(2);
});

it('fails when the onboarding url is missing', function () {
    Http::fake(['*' => Http::response(encryptedBody(['ref_id' => 'X']))]);

    XendityEkyc::createTransaction(new CreateTransactionRequest('ALI BIN ABU', '900101015555'));
})->throws(ApiException::class, 'onboarding URL');
