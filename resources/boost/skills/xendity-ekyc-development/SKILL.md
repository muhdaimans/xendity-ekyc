---
name: xendity-ekyc-development
description: >
  Integrate Xendity eKYC identity verification (MyKad, passport, PH, Cambodia, e-KTP) into a Laravel
  application with muhdaimans/xendity-ekyc: create onboarding transactions, handle the webhook, poll status.
license: MIT
metadata:
  author: Muhamad Aiman
---

# Xendity Ekyc

Use this skill when a Laravel application needs to verify end users through the Xendity eKYC gateway.

## Primary Goal

- apply the `muhdaimans/xendity-ekyc` public API in the smallest correct way

## Workflow

### 1. Inspect the Laravel app context

- confirm the app is a Laravel project and find the model being verified (usually `User`)
- check `.env` for the `XENDITY_EKYC_*` keys: API key, package name, security key, cipher key (16 chars), response URL

### 2. Install

- `php artisan vendor:publish --tag="xendity-ekyc-migrations"` then `php artisan migrate`
- add `XendityEkyc\XendityEkyc\Concerns\HasEkycTransactions` to the verified model

### 3. Start verification

- build `XendityEkyc\XendityEkyc\Data\CreateTransactionRequest` (documentName, documentNumber without dashes, optional `DocumentType`, refId ≤ 32 chars)
- call `XendityEkyc::createTransaction($request, $user)` and redirect to `$transaction->onboarding_url`

### 4. Receive the result

- prefer the built-in webhook: listen for `XendityEkyc\XendityEkyc\Events\TransactionUpdated` and act on `$event->transaction->isApproved()`
- without a public URL, poll with `XendityEkyc::refresh($transaction)`
- on the `response_url` page, use `XendityEkyc::transactionFromRedirect($request)` then `refresh()`; never trust the query string alone

## Examples

- onboarding controller: create the transaction for `auth()->user()` and redirect away to the onboarding URL
- listener: on `TransactionUpdated`, mark the user verified when approved, or notify them with `reject_message` when rejected

## Anti-patterns

- do not call the gateway with your own `ref_id` after creation; the stored `ref_id` is the Xendity-prefixed value
- do not store or log base64 images from detail mode
- do not treat `refId`/`status`/`result` redirect query values as verified results
- do not use `env()` outside config; read `config('xendity-ekyc.*')`
