<?php

declare(strict_types=1);

use XendityEkyc\XendityEkyc\Data\CreateTransactionRequest;

it('enforces the documented field lengths', function (array $arguments, string $message) {
    expect(fn () => new CreateTransactionRequest(...$arguments))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'ref id' => [['documentName' => 'ALI', 'documentNumber' => '900101015555', 'refId' => str_repeat('a', 33)], '[refId]'],
    'name' => [['documentName' => str_repeat('a', 101), 'documentNumber' => '900101015555'], '[documentName]'],
    'number' => [['documentName' => 'ALI', 'documentNumber' => str_repeat('1', 15)], '[documentNumber]'],
    'required name' => [['documentName' => ' ', 'documentNumber' => '900101015555'], '[documentName] field is required'],
]);
