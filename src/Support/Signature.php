<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Support;

/**
 * Request signature as defined in section 5.1.2 of the gateway specification:
 * base64_encode(md5(api_key + security_key + package_name + ref_id + security_key + request_time)).
 */
final readonly class Signature
{
    public function __construct(
        private string $apiKey,
        private string $packageName,
        private string $securityKey,
    ) {}

    public function generate(string $refId, string $requestTime): string
    {
        return base64_encode(hash('md5', $this->apiKey.$this->securityKey.$this->packageName.$refId.$this->securityKey.$requestTime));
    }

    public function matches(string $expected, string $actual): bool
    {
        return hash_equals($expected, $actual);
    }
}
