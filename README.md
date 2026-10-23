<div align="center">
    <h1>Xendity Ekyc</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/muhdaimans/xendity-ekyc"><img src="https://img.shields.io/packagist/v/muhdaimans/xendity-ekyc.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/muhdaimans/xendity-ekyc"><img src="https://img.shields.io/packagist/php-v/muhdaimans/xendity-ekyc.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/muhdaimans/xendity-ekyc"><img src="https://badge.laravel.cloud/badge/muhdaimans/xendity-ekyc?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/muhdaimans/xendity-ekyc/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/muhdaimans/xendity-ekyc/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/muhdaimans/xendity-ekyc"><img src="https://img.shields.io/packagist/dt/muhdaimans/xendity-ekyc.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Laravel client for the [Xendity](https://xendity.com) eKYC Gateway API (v2.10). It signs and encrypts requests, stores onboarding transactions, and receives webhook postbacks for you.

Supported documents: MyKad, MyTentera, Passport, MyKid, MyPR, MyKas, PH PRC / Driving License / SSS / UMID / Voter ID / National ID / Postal ID, Cambodia ID and Indonesia e-KTP.

## Installation

```bash
composer require muhdaimans/xendity-ekyc
```

Publish and run the migration, and optionally publish the config:

```bash
php artisan vendor:publish --tag="xendity-ekyc-migrations"
php artisan migrate

php artisan vendor:publish --tag="xendity-ekyc-config"
```

Add the credentials provided by Xendity to `.env`:

```dotenv
XENDITY_EKYC_BASE_URL=https://staging.ekyc.xendity.com/v1/gateway/
XENDITY_EKYC_API_KEY=
XENDITY_EKYC_PACKAGE_NAME=
XENDITY_EKYC_SECURITY_KEY=
XENDITY_EKYC_CIPHER_KEY=          # 16 characters
XENDITY_EKYC_RESPONSE_URL=https://your-app.test/ekyc/done
XENDITY_EKYC_TIMEZONE=Asia/Kuala_Lumpur
```

Your server's IP or domain must also be whitelisted by Xendity.

## Usage

### 1. Create a transaction

```php
use XendityEkyc\XendityEkyc\Data\CreateTransactionRequest;
use XendityEkyc\XendityEkyc\Enums\DocumentType;
use XendityEkyc\XendityEkyc\Facades\XendityEkyc;

$transaction = XendityEkyc::createTransaction(new CreateTransactionRequest(
    documentName: 'ALI BIN ABU',
    documentNumber: '900101015555',       // no dashes, max 14 characters
    documentType: DocumentType::MyKad,    // default
    refId: 'ORDER-1001',                  // optional, a ULID is generated when omitted
), kycable: $request->user());

return redirect()->away($transaction->onboarding_url);
```

The transaction is stored in `xendity_ekyc_transactions`. Xendity prefixes the `ref_id`; the stored `ref_id` is the prefixed value and `client_ref_id` is yours.

Add the `HasEkycTransactions` trait to the model that goes through eKYC:

```php
use XendityEkyc\XendityEkyc\Concerns\HasEkycTransactions;

class User extends Authenticatable
{
    use HasEkycTransactions;
}

$user->ekycTransactions;
$user->latestEkycTransaction;
$user->hasPassedEkyc();
```

### 2a. Receive updates by webhook (Integration Option 1)

The package registers `POST /xendity-ekyc/webhook` (route `xendity-ekyc.webhook`) and sends it as `backend_url` automatically. Each postback is decrypted, matched by `ref_id` and `onboarding_id`, and its signature is compared with the one stored at creation. When the status, result or reject message changes, a `TransactionUpdated` event is dispatched:

```php
use XendityEkyc\XendityEkyc\Events\TransactionUpdated;

Event::listen(function (TransactionUpdated $event) {
    if ($event->transaction->isApproved()) {
        $event->transaction->kycable?->markAsVerified();
    }
});
```

Xendity only calls back on port 80 or 443, so the URL must be publicly reachable. Set `XENDITY_EKYC_BACKEND_URL` to override it, or disable the route with `webhook.enabled`.

### 2b. Poll with Get Status (Integration Option 2)

```php
use XendityEkyc\XendityEkyc\Enums\StatusMode;

$status = XendityEkyc::refresh($transaction);                       // stored transaction, syncs + dispatches the event
$status = XendityEkyc::getStatus($refId, $onboardingId, StatusMode::Detail); // stateless

$status->isCompleted();
$status->isApproved();
$status->rejectMessage;
$status->step1?->fullName();
$status->step1?->ocr('address');
$status->step1?->landmarkIsValid();
$status->step2?->percentage;
$status->raw;                                                       // the full payload
```

Detail mode includes base64 document and face images. The package never stores them.

### 3. Handle the redirect

After the user finishes, Xendity redirects to `response_url` with `refId`, `status` and `result` in the query string. The query string is not signed, so use it only to find the transaction, then refresh it:

```php
$transaction = XendityEkyc::transactionFromRedirect($request);

if ($transaction) {
    XendityEkyc::refresh($transaction);
}
```

### Errors

Gateway errors throw `XendityEkyc\XendityEkyc\Exceptions\ApiException`. Its `errorCode` and `error()` (`ErrorCode` enum) hold codes 100–105, 400 and 401. Decryption problems throw `EncryptionException`, and missing configuration throws `XendityEkycException`.

### Artisan

```bash
php artisan xendity-ekyc:status {ref_id|onboarding_id} [--onboarding-id=]
```

### WebViews

Xendity supports Safari and Chrome on iOS, and Chrome on Android. When opening the onboarding URL inside a WebView, set its user agent to Chrome.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Xendity Ekyc! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Muhamad Aiman](https://github.com/muhdaimans)
- [All Contributors](../../contributors)

## License

Xendity Ekyc is open-sourced software licensed under the [MIT license](LICENSE.md).
