<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\ChatbotEcommerce\System\Jobs\ProcessPaymentWebhook;
use App\Extensions\ChatbotEcommerce\System\Services\PaymentRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PaymentWebhookController extends Controller
{
    public function handle(string $provider, Request $request, PaymentRuntime $payments): JsonResponse
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['payload' => 'The payment webhook payload must be valid JSON.']);
        }

        $event = $payments->receiveWebhook(
            strtolower(trim($provider)),
            $raw,
            $payload,
            $request->header('X-Payment-Signature'),
            $request->header('X-Payment-Timestamp'),
        );

        if ((string) $event->status !== 'processed') {
            $event->forceFill(['status' => 'queued', 'queued_at' => now()])->save();
            ProcessPaymentWebhook::dispatch((int) $event->id);
        }

        return response()->json([
            'received' => true,
            'event_uuid' => $event->uuid,
            'status' => (string) $event->fresh()->status,
        ], 202);
    }
}
