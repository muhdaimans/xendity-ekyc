<?php

declare(strict_types=1);

use XendityEkyc\XendityEkyc\Exceptions\EncryptionException;
use XendityEkyc\XendityEkyc\Support\Encrypter;

it('encrypts with the documented openssl formula', function () {
    $encrypter = new Encrypter('ApiKey0123456789abcdefghijklmnop', 'abcdefghijklmnop');
    $payload = ['ref_id' => 'ORDER-1', 'document_name' => 'ALI BIN ABU'];

    $expected = base64_encode(openssl_encrypt(
        json_encode($payload),
        'aes-256-cbc',
        'abcdefghijklmnop'.'ApiKey0123456789abcdefghijklmnop',
        OPENSSL_RAW_DATA,
        'abcdefghijklmnop',
    ));

    expect($encrypter->encrypt($payload))->toBe($expected);
});

it('round trips payloads and trims keys', function () {
    $encrypter = new Encrypter('ApiKey0123456789abcdefghijklmnop', 'abcdefghijklmnop');

    expect($encrypter->decrypt($encrypter->encrypt(['mode ' => 1, 'nested' => ['a ' => true]])))
        ->toBe(['mode' => 1, 'nested' => ['a' => true]]);
});

it('pads short keys like openssl does', function () {
    $encrypter = new Encrypter('api_key', 'abcdefghijklmnop');

    $expected = base64_encode(openssl_encrypt('{"a":1}', 'aes-256-cbc', 'abcdefghijklmnopapi_key', OPENSSL_RAW_DATA, 'abcdefghijklmnop'));

    expect($encrypter->encrypt(['a' => 1]))->toBe($expected);
});

it('rejects a cipher key that is not 16 bytes', function () {
    (new Encrypter('api_key', 'short'))->encrypt(['a' => 1]);
})->throws(EncryptionException::class, '16 bytes');

it('fails to decrypt data encrypted with other credentials', function () {
    $data = (new Encrypter('other-key-0123456789abcdefghijkl', 'ponmlkjihgfedcba'))->encrypt(['a' => 1]);

    (new Encrypter('ApiKey0123456789abcdefghijklmnop', 'abcdefghijklmnop'))->decrypt($data);
})->throws(EncryptionException::class);
