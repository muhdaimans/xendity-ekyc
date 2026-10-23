<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use XendityEkyc\XendityEkyc\Data\CreateTransactionRequest;
use XendityEkyc\XendityEkyc\Data\StatusResult;
use XendityEkyc\XendityEkyc\Enums\CallbackMode;
use XendityEkyc\XendityEkyc\Enums\Platform;
use XendityEkyc\XendityEkyc\Enums\ResponseMode;
use XendityEkyc\XendityEkyc\Enums\StatusMode;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Events\TransactionUpdated;
use XendityEkyc\XendityEkyc\Exceptions\ApiException;
use XendityEkyc\XendityEkyc\Exceptions\InvalidWebhookException;
use XendityEkyc\XendityEkyc\Exceptions\XendityEkycException;
use XendityEkyc\XendityEkyc\Models\EkycTransaction;
use XendityEkyc\XendityEkyc\Support\Payload;

class XendityEkyc
{
    public function __construct(
        private readonly Gateway $gateway,
        private readonly Repository $config,
    ) {}

    /**
     * Create an onboarding transaction and store it.
     *
     * Redirect or send the end-user to the returned model's onboarding_url.
     */
    public function createTransaction(CreateTransactionRequest $request, ?Model $kycable = null): EkycTransaction
    {
        $refId = $request->refId ?? (string) Str::ulid();
        $requestTime = $this->gateway->requestTime();
        $signature = $this->gateway->signature()->generate($refId, $requestTime);
        $platform = $request->platform ?? $this->platform();
        $backendUrl = $request->backendUrl ?? $this->backendUrl();
        $responseUrl = $request->responseUrl ?? $this->optionalString('response_url')
            ?? throw XendityEkycException::missingResponseUrl();

        $callbackMode = $backendUrl === null
            ? CallbackMode::None
            : $request->callbackMode ?? CallbackMode::from($this->config->integer('xendity-ekyc.callback_mode', 1));

        $responseMode = $request->responseMode ?? ResponseMode::from($this->config->integer('xendity-ekyc.response_mode', 1));

        $response = $this->gateway->post('create-transaction', array_filter([
            'api_key' => $this->gateway->apiKey(),
            'package_name' => $this->gateway->packageName(),
            'ref_id' => $refId,
            'document_name' => $request->documentName,
            'document_number' => $request->documentNumber,
            'document_type' => $request->documentType->value,
            'product_info' => $request->productInfo,
            'remark' => $request->remark,
            'platform' => $platform->value,
            'signature' => $signature,
            'response_url' => $responseUrl,
            'backend_url' => $backendUrl,
            'callback_mode' => $callbackMode->value,
            'response_mode' => $responseMode->value,
            'request_time' => $requestTime,
        ], fn (mixed $value): bool => $value !== null));

        $onboardingId = Payload::string($response, 'onboarding_id');
        $onboardingUrl = Payload::string($response, 'onboarding_url');

        if ($onboardingId === null || $onboardingUrl === null) {
            throw new ApiException('The Xendity eKYC gateway did not return an onboarding URL.', payload: $response);
        }

        $expiredAt = Payload::string($response, 'expired_at');

        $transaction = self::newTransaction();

        $transaction->fill([
            'client_ref_id' => $refId,
            'ref_id' => Payload::string($response, 'ref_id') ?? $refId,
            'onboarding_id' => $onboardingId,
            'onboarding_url' => $onboardingUrl,
            'signature' => $signature,
            'document_type' => $request->documentType,
            'platform' => $platform,
            'status' => TransactionStatus::UrlNotOpened,
            'expired_at' => $expiredAt === null
                ? null
                : Date::parse($expiredAt, $this->timezone())->setTimezone($this->config->string('app.timezone', 'UTC')),
        ]);

        if ($kycable !== null) {
            $transaction->kycable()->associate($kycable);
        }

        $transaction->save();

        return $transaction;
    }

    /**
     * Query the gateway for the latest status of a transaction.
     *
     * The ref_id must be the prefixed value returned by create-transaction.
     */
    public function getStatus(string $refId, string $onboardingId, StatusMode $mode = StatusMode::Summary): StatusResult
    {
        $requestTime = $this->gateway->requestTime();

        $response = $this->gateway->post('get-status', [
            'api_key' => $this->gateway->apiKey(),
            'package_name' => $this->gateway->packageName(),
            'ref_id' => $refId,
            'onboarding_id' => $onboardingId,
            'platform' => $this->platform()->value,
            'signature' => $this->gateway->signature()->generate($refId, $requestTime),
            'request_time' => $requestTime,
            'mode' => $mode->value,
        ]);

        return StatusResult::fromArray($response);
    }

    /**
     * Fetch the latest status for a stored transaction and sync it.
     */
    public function refresh(EkycTransaction $transaction, StatusMode $mode = StatusMode::Summary): StatusResult
    {
        $status = $this->getStatus($transaction->ref_id, $transaction->onboarding_id, $mode);

        $this->apply($transaction, $status);

        return $status;
    }

    /**
     * Verify and apply a webhook postback (section 5.5).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): EkycTransaction
    {
        $status = StatusResult::fromArray($payload);

        foreach (['api_key' => $this->gateway->apiKey(), 'package_name' => $this->gateway->packageName()] as $key => $expected) {
            $actual = Payload::string($status->raw, $key);

            if ($actual !== null && ! hash_equals($expected, $actual)) {
                throw InvalidWebhookException::invalidCredentials();
            }
        }

        $transaction = self::transactions()
            ->where('ref_id', $status->refId)
            ->where('onboarding_id', $status->onboardingId)
            ->first() ?? throw InvalidWebhookException::unknownTransaction();

        if ($status->signature === null || ! $this->gateway->signature()->matches($transaction->signature, $status->signature)) {
            throw InvalidWebhookException::invalidSignature();
        }

        $this->apply($transaction, $status);

        return $transaction;
    }

    /**
     * Resolve the stored transaction from the response_url redirect (response_mode = 1).
     *
     * The query string is not signed: use it only to locate the transaction, then refresh().
     */
    public function transactionFromRedirect(Request $request): ?EkycTransaction
    {
        $refId = $request->query('refId', $request->query('ref_id'));

        return is_string($refId) && $refId !== '' ? self::transactions()->where('ref_id', $refId)->first() : null;
    }

    /**
     * @return class-string<EkycTransaction>
     */
    public static function transactionModel(): string
    {
        $model = config('xendity-ekyc.model', EkycTransaction::class);

        return is_string($model) && is_a($model, EkycTransaction::class, true) ? $model : EkycTransaction::class;
    }

    /**
     * @return Builder<EkycTransaction>
     */
    public static function transactions(): Builder
    {
        return self::transactionModel()::query();
    }

    private static function newTransaction(): EkycTransaction
    {
        $model = self::transactionModel();

        return new $model;
    }

    private function apply(EkycTransaction $transaction, StatusResult $status): void
    {
        if ($transaction->applyStatus($status)) {
            TransactionUpdated::dispatch($transaction, $status);
        }
    }

    private function platform(): Platform
    {
        return Platform::tryFrom($this->config->string('xendity-ekyc.platform', 'Web')) ?? Platform::Web;
    }

    private function backendUrl(): ?string
    {
        $url = $this->optionalString('backend_url');

        if ($url !== null || ! $this->config->boolean('xendity-ekyc.webhook.enabled', true) || ! Route::has('xendity-ekyc.webhook')) {
            return $url;
        }

        return route('xendity-ekyc.webhook');
    }

    private function timezone(): string
    {
        return $this->config->string('xendity-ekyc.timezone', 'Asia/Kuala_Lumpur');
    }

    private function optionalString(string $key): ?string
    {
        $value = $this->config->get("xendity-ekyc.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
