<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use XendityEkyc\XendityEkyc\Exceptions\InvalidWebhookException;
use XendityEkyc\XendityEkyc\Exceptions\XendityEkycException;
use XendityEkyc\XendityEkyc\Gateway;
use XendityEkyc\XendityEkyc\XendityEkyc;

class WebhookController
{
    public function __invoke(Request $request, Gateway $gateway, XendityEkyc $ekyc): JsonResponse
    {
        try {
            $body = $request->getContent();

            if (json_decode($body) === null && $request->request->count() > 0) {
                $body = (string) json_encode($request->request->all());
            }

            $payload = $gateway->decode($body) ?? throw XendityEkycException::invalidPayload('body');

            $ekyc->handleWebhook($payload);
        } catch (InvalidWebhookException $exception) {
            return new JsonResponse(['success' => false, 'message' => $exception->getMessage()], 403);
        } catch (XendityEkycException $exception) {
            return new JsonResponse(['success' => false, 'message' => $exception->getMessage()], 400);
        }

        return new JsonResponse(['success' => true]);
    }
}
