<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use XendityEkyc\XendityEkyc\Gateway;
use XendityEkyc\XendityEkyc\Models\EkycTransaction;
use XendityEkyc\XendityEkyc\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Build an encrypted gateway response body.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, string>
 */
function encryptedBody(array $payload): array
{
    $gateway = app(Gateway::class);

    return ['api_key' => $gateway->apiKey(), 'data' => $gateway->encrypter()->encrypt($payload)];
}

/**
 * Decrypt the payload of an outgoing gateway request.
 *
 * @return array<string, mixed>
 */
function decryptedRequest(Request $request): array
{
    return app(Gateway::class)->encrypter()->decrypt($request['data']);
}

/**
 * @return array<string, mixed>
 */
function gatewayFixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/Fixtures/{$name}.json"), true);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function storedTransaction(array $attributes = []): EkycTransaction
{
    return EkycTransaction::query()->create([
        'client_ref_id' => 'ORDER-1',
        'ref_id' => 'XDYORDER-1',
        'onboarding_id' => 'onb_123',
        'onboarding_url' => 'https://staging.ekyc.xendity.com/#/guideline?token=abc',
        'signature' => 'stored-signature',
        'document_type' => '1',
        'platform' => 'Web',
        ...$attributes,
    ]);
}
