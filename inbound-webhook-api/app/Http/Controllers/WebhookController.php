<?php

namespace App\Http\Controllers;

use App\Services\Sns\SnsPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generic inbound webhook receiver.
 *
 * Flow: verify signature -> publish to SNS -> return 200 immediately.
 * Shopify requires a 200 within ~5s or it retries and eventually drops the
 * subscription, so we do no heavy work here — SNS fan-out + SQS handles that.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $source, SnsPublisher $publisher): JsonResponse
    {
        $config = config("sns.sources.{$source}");

        if (! $config) {
            return response()->json(['error' => "Unknown webhook source [{$source}]."], 404);
        }

        $rawBody = $request->getContent();

        // 1. Verify the signature over the RAW body (never the parsed JSON).
        if ($source === 'shopify' && ! ($config['skip_verification'] ?? false)) {
            if (! $this->verifyShopify($rawBody, $request->header('X-Shopify-Hmac-SHA256'), $config['secret'] ?? '')) {
                return response()->json(['error' => 'Invalid signature.'], 401);
            }
        }

        // 2. Pull routing context from headers.
        $attributes = $this->attributesFor($source, $request);

        // 3. Publish to SNS (fans out to the SQS chain). Keep the handler fast.
        try {
            $messageId = $publisher->publish(
                payload: [
                    'source' => $source,
                    'received_at' => now()->toIso8601String(),
                    'headers' => $attributes,
                    'body' => json_decode($rawBody, true) ?? $rawBody,
                ],
                attributes: $attributes,
            );
        } catch (Throwable $e) {
            // Return 5xx so the sender retries rather than dropping the event.
            Log::error('Webhook publish to SNS failed', ['source' => $source, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Upstream publish failed.'], 502);
        }

        return response()->json(['status' => 'accepted', 'message_id' => $messageId], 200);
    }

    /**
     * Constant-time verification of Shopify's base64 HMAC-SHA256 over the body.
     */
    private function verifyShopify(string $rawBody, ?string $hmacHeader, string $secret): bool
    {
        if (empty($hmacHeader) || empty($secret)) {
            return false;
        }

        $computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($computed, $hmacHeader);
    }

    /**
     * @return array<string, string>
     */
    private function attributesFor(string $source, Request $request): array
    {
        if ($source === 'shopify') {
            return array_filter([
                'source' => 'shopify',
                'topic' => (string) $request->header('X-Shopify-Topic', ''),
                'shop_domain' => (string) $request->header('X-Shopify-Shop-Domain', ''),
                'webhook_id' => (string) $request->header('X-Shopify-Webhook-Id', ''),
                'api_version' => (string) $request->header('X-Shopify-API-Version', ''),
            ]);
        }

        return ['source' => $source];
    }
}
