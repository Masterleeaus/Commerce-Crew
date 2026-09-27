<?php

namespace App\Extensions\ChatbotInstagram\System\Http\Controllers\Webhook;

use App\Extensions\Chatbot\System\Models\ChatbotChannel;
use App\Extensions\Chatbot\System\Models\ChatbotChannelWebhook;
use App\Extensions\ChatbotInstagram\System\Services\InstagramConversationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChatbotInstagramWebhookController extends Controller
{
    public function __construct(
        public InstagramConversationService $service
    ) {}

    /**
     * Global webhook endpoint for Instagram.
     * GET: subscription verification. POST: resolve channel from payload and process.
     */
    public function handleGlobal(Request $request): JsonResponse|Response
    {
        if ($request->isMethod('get')) {
            return $this->verifySubscription($request);
        }

        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $instagramId = $request->input('entry.0.id');

        if (! $instagramId) {
            return response()->json(['error' => 'Missing entry ID'], 400);
        }

        $channel = $this->resolveChannelByInstagramId((string) $instagramId);

        if (! $channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }

        return $this->processWebhook(
            $channel->getAttribute('chatbot_id'),
            $channel->getKey(),
            $request
        );
    }

    /**
     * Legacy per-chatbot/channel webhook endpoint (backward compatibility).
     */
    public function handle(int $chatbotId, int $channelId, Request $request): JsonResponse|Response
    {
        if ($response = $this->verifyWebhookLegacy($request, $channelId)) {
            return $response;
        }

        return $this->processWebhook($chatbotId, $channelId, $request);
    }

    /**
     * Shared webhook processing logic.
     */
    protected function processWebhook(int $chatbotId, int $channelId, Request $request): JsonResponse
    {
        $payload = $request->input('entry.0.messaging.0');

        if (! $payload) {
            return response()->json(['status' => false]);
        }

        ChatbotChannelWebhook::query()->create([
            'chatbot_id'         => $chatbotId,
            'chatbot_channel_id' => $channelId,
            'payload'            => $request->all(),
            'created_at'         => now(),
        ]);

        $this->service
            ->setIpAddress()
            ->setChatbotId($chatbotId)
            ->setChannelId($channelId)
            ->setPayload($payload);

        $conversation = $this->service->storeConversation();

        $chatbot = $this->service->getChatbot();

        $this->service->insertMessage(
            conversation: $conversation,
            message: $payload['message']['text'] ?? '',
            role: 'user',
            model: $chatbot->getAttribute('ai_model')
        );

        $this->service->handle();

        return response()->json(['status' => 'processed']);
    }

    /**
     * Verify GET subscription using global verify token from settings.
     */
    protected function verifySubscription(Request $request): Response
    {
        $verifyToken = setting('INSTAGRAM_VERIFY_TOKEN');

        if ($request->get('hub_mode') === 'subscribe' && $request->get('hub_verify_token') === $verifyToken) {
            return response($request->get('hub_challenge'), 200);
        }

        return response('Token invalid', 403);
    }

    /**
     * Verify X-Hub-Signature-256 HMAC on POST requests.
     * Returns true if signature is valid or if no app secret is configured (graceful degradation).
     */
    protected function verifySignature(Request $request): bool
    {
        $appSecret = setting('INSTAGRAM_APP_SECRET');

        if (! $appSecret) {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $expectedHash = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedHash, $signature);
    }

    /**
     * Find the ChatbotChannel matching the Instagram Business Account ID from the payload.
     */
    protected function resolveChannelByInstagramId(string $instagramId): ?ChatbotChannel
    {
        return ChatbotChannel::query()
            ->where('channel', 'instagram')
            ->whereJsonContains('credentials->instagram_id', $instagramId)
            ->first();
    }

    /**
     * Legacy GET verification using per-channel verify token.
     */
    private function verifyWebhookLegacy(Request $request, int $channelId): ?Response
    {
        $channel = ChatbotChannel::query()->find($channelId);

        if (! $channel) {
            if ($request->isMethod('get')) {
                return response('Channel not found', 404);
            }

            return response('Channel not found', 404);
        }

        $verifyToken = data_get($channel->credentials, 'verify_token');

        if ($request->isMethod('get')) {
            if ($request->get('hub_mode') === 'subscribe' && $request->get('hub_verify_token') === $verifyToken) {
                return response($request->get('hub_challenge'), 200);
            }

            return response('Token invalid', 403);
        }

        return null;
    }
}
