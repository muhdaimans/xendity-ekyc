<?php

declare(strict_types=1);

use XendityEkyc\XendityEkyc\Support\Signature;

it('signs requests as base64 of the md5 hex digest in the documented field order', function () {
    $signature = new Signature('samplekey', 'Xendity', 'Xendity883929');

    $source = 'samplekeyXendity883929XendityXendity123Xendity8839292020-04-16 14:14:17';

    expect($signature->generate('Xendity123', '2020-04-16 14:14:17'))
        ->toBe(base64_encode(hash('md5', $source)))
        ->toBe('ZTI3OWQ1MWQ2ZTk2ZTllYmU4YTVmYTJiZGRjZDE2OGY=');
});

it('compares signatures', function () {
    $signature = new Signature('key', 'package', 'secret');

    expect($signature->matches('abc', 'abc'))->toBeTrue()
        ->and($signature->matches('abc', 'abd'))->toBeFalse();
});
