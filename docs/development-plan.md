# Xendity eKYC Laravel Package — Development Plan

> **Status:** phases 1–4 are implemented. Phase 5 (UAT verification) is pending the open questions in §8. Implementation differences from the original plan: transactions are always persisted (no persistence toggle or custom verifier); the model class is configurable via `xendity-ekyc.model`; the views, translations and public assets scaffolding was removed.

Source: *Xendity eKYC Gateway API v2.10* (last updated 9 Oct 2025).
Package: `muhdaimans/xendity-ekyc`, namespace `XendityEkyc\XendityEkyc`, PHP ^8.3, Laravel 12/13.

## 1. What the package does

The Xendity gateway exposes two encrypted POST endpoints plus an outbound webhook:

| Gateway feature | Endpoint | Package surface |
|---|---|---|
| Create transaction (get onboarding URL) | `{base}/create-transaction` | `XendityEkyc::createTransaction()` |
| Get status (polling, Option 2) | `{base}/get-status` | `XendityEkyc::getStatus()` |
| Webhook / postback (Option 1) | Xendity → `backend_url` | Package route + `TransactionUpdated` event |
| Redirect after completion | Xendity → `response_url` (`?refId&status&result` when `response_mode=1`) | `XendityEkyc::redirectResult(Request)` helper (untrusted, informational only) |

Both integration options from §3 of the spec are supported: the host app either listens to the event (webhook) or polls `getStatus()`.

## 2. Protocol details the code must implement

### 2.1 Signature (§5.1.2)

```
signature = base64_encode( md5( api_key . security_key . package_name . ref_id . security_key . request_time ) )
```

- `md5()` returns the **hex string**; base64 is applied to that hex string (not raw bytes).
- `request_time` format: `Y-m-d H:i:s` (the table says `HH-mm-ss`, but every example uses colons).
- Required on every request.

> ⚠️ The spec's worked example does not reproduce: md5 of the given string is `e279d51d…`, not `3e88e4dc…`. Tests must use our own computed fixtures, and the formula must be confirmed against UAT (see §8).

### 2.2 Encryption (§5.1.3)

```
key  = cipher_key . api_key          // truncated to 32 bytes (AES-256)
iv   = cipher_key                    // 16 bytes
data = base64_encode( openssl_encrypt( json_encode(body), 'aes-256-cbc', key, OPENSSL_RAW_DATA, iv ) )
wire = { "api_key": api_key, "data": data }
```

- PHP's `openssl_encrypt` silently truncates/zero-pads the key; the JS sample does `substring(0, 32)`. We do `substr($key, 0, 32)` explicitly and require `cipher_key` to be exactly 16 bytes (validated at boot/first use).
- Responses are also encrypted; decryption is the inverse. The exact response envelope is not documented — the client must accept `{"data": "<b64>"}`, a bare base64 string, and plain JSON (fallback), in that order.

### 2.3 Transport

- `POST`, `Content-Type: application/json`, HTTPS.
- Base URL: `https://staging.ekyc.xendity.com/v1/gateway/` (UAT). The spec lists the same URL for production — must be configurable.
- Server IP/domain must be whitelisted by Xendity (operational, documented in README).

## 3. Architecture

```
src/
├── XendityEkyc.php                     # Main service (facade root): createTransaction, getStatus, redirectResult
├── XendityEkycServiceProvider.php      # Config, routes, migration, command, singleton bindings
├── Facades/XendityEkyc.php
├── Support/
│   ├── Signature.php                   # generate(refId, requestTime), matches(...)
│   └── Encrypter.php                   # encrypt(array): string, decrypt(string): array
├── Http/
│   ├── Client.php                      # Wraps Illuminate Http: encrypt → POST → decrypt → error mapping
│   └── Controllers/WebhookController.php
├── Data/                               # Immutable readonly value objects (no extra deps)
│   ├── CreateTransactionRequest.php    # ref_id, document_name, document_number, document_type, remark, product_info, urls, modes
│   ├── Transaction.php                 # create-transaction response: ref_id (prefixed!), onboarding_id, onboarding_url, expired_at, signature
│   ├── TransactionStatus.php           # get-status / webhook payload: status, result, reject_message, step1, step2, raw()
│   ├── Step1Result.php                 # ocr_result (array), text_similarity, landmark, landmark_status, images, attempt, updated_at
│   └── Step2Result.php                 # is_identical, percentage (float), best_frame, attempt, updated_at
├── Enums/
│   ├── DocumentType.php                # 1 MyKad … 15 Indonesia e-KTP (string-backed: '1'..'15')
│   ├── Platform.php                    # Web | iOS | Android
│   ├── CallbackMode.php                # 0 None, 1 Summary, 2 Detail
│   ├── ResponseMode.php                # 0 NoQueries, 1 WithQueries
│   ├── StatusMode.php                  # 1 Summary, 2 Detail
│   ├── Status.php                      # 0 NotOpened, 1 Processing, 2 Completed, 3 Expired
│   ├── Result.php                      # 0 Rejected, 1 Approved, 2 NotAvailable
│   └── ErrorCode.php                   # 100–105, 400, 401 (+ 5xx handled generically)
├── Events/TransactionUpdated.php       # Fired from webhook (and optionally from getStatus when stored state changes)
├── Models/EkycTransaction.php          # Optional persistence (see 3.3)
├── Exceptions/
│   ├── XendityEkycException.php        # base
│   ├── ApiException.php                # carries ErrorCode + raw response
│   ├── EncryptionException.php
│   └── InvalidWebhookException.php
└── Console/Commands/StatusCommand.php  # replaces placeholder: `xendity-ekyc:status {onboarding_id}`
```

Design choices:

- **No new runtime dependencies.** `illuminate/http` (Http client) is already available via Laravel; add it explicitly to `require` alongside `illuminate/support`. Use `guzzlehttp/guzzle` transitively through Laravel.
- **Raw payload always kept.** Each document type returns different `ocr_result` / `landmark` keys (MyKad, PH IDs, Cambodia, e-KTP, passport…). Typed accessors cover the common fields; `->raw()` and `->ocr('field')` cover the rest. We do not model every per-country field.
- **Key normalisation.** Samples contain `"mode "` and `"nationality "` with trailing spaces; recursively trim keys when hydrating. Casts: `percentage` string → float, `status`/`result` → enums, booleans as-is.
- **`ref_id` prefixing.** The gateway returns `ref_id` with a client prefix. `Transaction::$refId` holds the *returned* value and `getStatus()` must be called with it — documented loudly and handled automatically when the model is used.

### 3.1 Public API (target usage)

```php
use XendityEkyc\XendityEkyc\Facades\XendityEkyc;
use XendityEkyc\XendityEkyc\Data\CreateTransactionRequest;
use XendityEkyc\XendityEkyc\Enums\DocumentType;

$transaction = XendityEkyc::createTransaction(new CreateTransactionRequest(
    refId: 'ORDER-1001',
    documentName: 'ALI BIN ABU',
    documentNumber: '900101015555',
    documentType: DocumentType::MyKad,
    responseUrl: route('kyc.done'),          // defaults to config
));

redirect()->away($transaction->onboardingUrl);

$status = XendityEkyc::getStatus($transaction->refId, $transaction->onboardingId); // StatusMode::Summary default

if ($status->isCompleted() && $status->isApproved()) { /* … */ }
$status->rejectMessage;
$status->step1->ocr('full_name');
```

Webhook consumer:

```php
Event::listen(TransactionUpdated::class, function (TransactionUpdated $event) {
    $event->status->result;      // Result enum
    $event->transaction;         // EkycTransaction model or null when persistence is off
});
```

### 3.2 Configuration (`config/xendity-ekyc.php`)

| Key | Env | Default |
|---|---|---|
| `base_url` | `XENDITY_EKYC_BASE_URL` | `https://staging.ekyc.xendity.com/v1/gateway/` |
| `api_key` | `XENDITY_EKYC_API_KEY` | — |
| `package_name` | `XENDITY_EKYC_PACKAGE_NAME` | — |
| `security_key` (md5 key) | `XENDITY_EKYC_SECURITY_KEY` | — |
| `cipher_key` (IV, 16 chars) | `XENDITY_EKYC_CIPHER_KEY` | — |
| `platform` | `XENDITY_EKYC_PLATFORM` | `Web` |
| `callback_mode` | — | `1` (Summary, recommended) |
| `response_mode` | — | `1` |
| `response_url` | `XENDITY_EKYC_RESPONSE_URL` | `null` (required per request if null) |
| `timezone` | `XENDITY_EKYC_TIMEZONE` | `Asia/Kuala_Lumpur` (for `request_time`) |
| `http.timeout` / `http.retry` | — | `30` / `[2, 200ms]` (retry only on connection errors & 5xx) |
| `webhook.enabled` | — | `true` |
| `webhook.path` | — | `xendity-ekyc/webhook` |
| `webhook.middleware` | — | `['api']` |
| `persistence.enabled` | — | `true` |
| `persistence.table` | — | `xendity_ekyc_transactions` |

When `webhook.enabled` and no explicit `backend_url` is passed, `backend_url` defaults to `route('xendity-ekyc.webhook')`. Note: Xendity only allows ports 80/443 for the callback.

### 3.3 Persistence & webhook security (§5.5.2)

The spec recommends validating `ref_id` + `onboarding_id` against our DB and comparing the echoed `signature` with the one we generated at creation. Because the webhook's `request_time` is server-generated, the original signature cannot be recomputed — it must be stored. So the package ships one migration:

`xendity_ekyc_transactions`: `id`, `ref_id` (returned/prefixed, unique), `client_ref_id`, `onboarding_id` (unique), `signature`, `document_type`, `onboarding_url`, `expired_at`, `status` (tinyint), `result` (tinyint, nullable), `reject_message`, `payload` (json, last summary — **no base64 images**), nullable morphs `kycable` (link to User/Customer), timestamps.

Webhook flow:

1. Decrypt body if it is an encrypted envelope (format unconfirmed — support both).
2. Look up by `onboarding_id` + `ref_id`; 404-equivalent (`InvalidWebhookException` → 403) if missing.
3. `hash_equals($stored->signature, $payload['signature'])`.
4. Update status/result/reject_message, fire `TransactionUpdated`, return `200 {"success": true}`.
5. Idempotent: replays of the same status don't re-fire unless state changed.

If persistence is disabled, steps 2–3 are delegated to a user-supplied verifier (`XendityEkyc::verifyWebhookUsing(Closure)`), otherwise reject.

**PII:** Detail mode returns base64 IC images and face frames. These are never stored or logged by the package; logging of payloads is off by default and masks `document_number`, images and `api_key`.

## 4. Error handling

- Map `error_code` (100 Missing Parameter, 101 Invalid API Key, 102 Invalid Package Name, 103 Invalid Ref ID, 104 Invalid Signature, 105 Service unavailable, 400 Encrypt failed, 401 Invalid credential) to `ErrorCode` and throw `ApiException`.
- `success: false` without a code → `ApiException` with raw body.
- HTTP 5xx → `ApiException` (retryable); connection errors → bubble Laravel's `ConnectionException` wrapped.
- Decryption failure → `EncryptionException` (usually wrong `cipher_key`/`api_key`).

## 5. Validation (client-side, before sending)

From §5.3.2 lengths: `ref_id` ≤ 32, `document_name` ≤ 100, `document_number` ≤ 14 (digits only for MyKad, no dashes), `remark` ≤ 200, `response_url`/`backend_url` ≤ 200, `platform` in enum. Validated in the `CreateTransactionRequest` constructor → `InvalidArgumentException`. Keep `ref_id` short because Xendity prepends a prefix.

## 6. Testing plan (Pest + Testbench, `Http::fake()`)

| Area | Tests |
|---|---|
| `Signature` | formula order; base64 of hex md5; fixture computed from spec inputs; `matches()` uses constant-time compare |
| `Encrypter` | round-trip; output equals raw `openssl_encrypt` per spec formula; key truncation to 32 bytes; 16-byte IV validation; bad data → `EncryptionException` |
| `createTransaction` | posts to `/create-transaction` with `{api_key, data}`; decrypted body contains all mandatory fields, signature, `request_time` in configured tz; defaults from config; response hydrated; persisted row created |
| `getStatus` | summary & detail fixtures (MyKad, passport w/o landmark, PH, Cambodia, e-KTP with `reject_message`); trimmed keys; enum casts; `error_code` → `ApiException` |
| Webhook | encrypted & plain payloads; unknown transaction → 403; signature mismatch → 403; success → model updated + event dispatched once; replay idempotent |
| Provider | config merge, route registered only when enabled, migration publishable, command registered |
| Command | `xendity-ekyc:status` prints status/result |
| Arch | existing presets + `Data` classes readonly, enums backed |

Fixtures: one JSON file per sample response from §5.4.4/5.4.5 under `tests/Fixtures/`. Keep `composer test` (phpstan, pint, 100% type coverage, pest) green.

## 7. Delivery phases

1. **Core crypto** — config, `Signature`, `Encrypter`, exceptions, enums. Tests.
2. **API client** — `Http\Client`, `createTransaction`, `getStatus`, DTOs, error mapping. Tests with fixtures.
3. **Webhook + persistence** — migration, model, controller, route, event, verifier hook. Tests.
4. **DX** — facade docblocks, `xendity-ekyc:status` command (remove placeholder command/view/route), README usage for both integration options, `.env` keys, WebView user-agent note, Boost skill update (`package-generate-skill`).
5. **UAT verification** — run against staging with real credentials; capture real (sanitised) responses as fixtures; resolve §8 items; then release `v0.1.0` (`package-release`).

## 8. Open questions for Xendity (block phase 5)

1. Signature example in §5.1.2 doesn't match its own inputs — confirm formula (hex md5 → base64) with a real request.
2. Exact shape of the **encrypted response** body (`{"data": …}`? includes `success`?).
3. Is the **webhook postback** encrypted, and as JSON body or form data? Which HTTP status must we return, and does Xendity retry on failure?
4. Get-status: must `ref_id` be the prefixed value returned by create-transaction? (Spec implies yes.)
5. Production base URL (spec lists the staging URL for both).
6. `request_time` timezone (assume MYT) and allowed clock skew.
7. `callback_mode` / `response_mode` — integer or string? Samples send `"0"` and misspell `repsonse_mode`.
8. `updated_at` units (`15913221` looks truncated, not a Unix timestamp).
9. Error response envelope (HTTP status + body) for codes 100–105/400/401.
10. Is `cipher_key` always 16 characters and `api_key` 32 (so key = first 32 bytes of their concatenation)?
