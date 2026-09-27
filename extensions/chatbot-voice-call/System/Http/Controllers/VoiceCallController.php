<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotVoiceCall\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\Introduction;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Ai\ElevenLabsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoiceCallController extends Controller
{
    public function start(Request $request, Chatbot $chatbot, string $sessionId): JsonResponse
    {
        $isAdminOwned = $chatbot->user?->isAdmin();

        if (! $isAdminOwned && ! $chatbot->voice_call_enabled) {
            return response()->json(['error' => __('Voice call is not enabled for this chatbot.')], 403);
        }

        // Plan check: chatbot owner must have ext_voice_call feature enabled
        $ownerPlan = $chatbot->user?->relationPlan;

        if (! $isAdminOwned && $ownerPlan && ! $ownerPlan->checkOpenAiItem(Introduction::AI_EXT_VOICE_CALL->value)) {
            return response()->json(['error' => __('Voice call is not available in your current plan.')], 403);
        }

        // Per-plan seconds limit check
        $remainingSeconds = null;

        if ($ownerPlan) {
            $secondsLimit = (int) ($ownerPlan->voice_call_seconds_limit ?? -1);

            if ($secondsLimit === 0) {
                return response()->json(['error' => __('Voice call is not available in your current plan.')], 403);
            }

            if ($secondsLimit > 0) {
                $usedSeconds = (int) ChatbotHistory::query()
                    ->whereHas('conversation', function ($q) use ($chatbot) {
                        $q->where('chatbot_id', $chatbot->getKey());
                    })
                    ->where('role', 'voice-call-ended')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('voice_call_duration');

                if ($usedSeconds >= $secondsLimit) {
                    return response()->json(['error' => __('Monthly voice call limit reached.')], 403);
                }

                $remainingSeconds = $secondsLimit - $usedSeconds;
            }
        }

        // Demo mode check
        $isDemo = Helper::appIsDemo();

        if ($isDemo) {
            $demoSecondsLimit = (int) setting('voice_call_demo_seconds_limit', 30);

            $demoUsedSeconds = (int) ChatbotHistory::query()
                ->whereHas('conversation', function ($q) use ($sessionId) {
                    $q->where('session_id', $sessionId);
                })
                ->where('role', 'voice-call-ended')
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('voice_call_duration');

            if ($demoUsedSeconds >= $demoSecondsLimit) {
                return response()->json([
                    'error' => __('You have reached the daily voice call limit for demo mode.'),
                ], 429);
            }

            $demoRemaining = $demoSecondsLimit - $demoUsedSeconds;
            $remainingSeconds = $remainingSeconds !== null
                ? min($remainingSeconds, $demoRemaining)
                : $demoRemaining;
        }

        // Credit balance check for chatbot owner
        $provider = setting('voice_call_provider', 'openai_realtime');
        $entityEnum = $provider === 'openai_realtime'
            ? EntityEnum::GPT_4_O_REALTIME_PREVIEW
            : EntityEnum::ELEVENLABS_VOICE_CHATBOT;

        $driver = Entity::driver($entityEnum)->forUser($chatbot->user);

        if (! $driver->hasCreditBalance()) {
            return response()->json(['error' => __('Insufficient credits for voice call.')], 403);
        }

        $conversation = ChatbotConversation::query()
            ->where('chatbot_id', $chatbot->getKey())
            ->where('session_id', $sessionId)
            ->latest()
            ->first();

        if (! $conversation) {
            return response()->json(['error' => __('No active conversation found.')], 404);
        }

        ChatbotHistory::query()->create([
            'chatbot_id'      => $chatbot->getKey(),
            'conversation_id' => $conversation->getKey(),
            'role'            => 'voice-call-started',
            'message'         => __('Voice call started'),
            'created_at'      => now(),
        ]);

        $credentials = [];

        try {
            if ($provider === 'openai_realtime') {
                $credentials = $this->getOpenAiCredentials();
            } elseif ($provider === 'elevenlabs') {
                $credentials = $this->getElevenLabsCredentials($chatbot);
            } else {
                return response()->json(['error' => __('Invalid voice call provider.')], 422);
            }
        } catch (Exception $e) {
            Log::error('Voice call start error: ' . $e->getMessage());

            return response()->json(['error' => __('Failed to initialize voice call.')], 500);
        }

        $response = [
            'provider'        => $provider,
            'credentials'     => $credentials,
            'conversation_id' => $conversation->getKey(),
            'first_message'   => $chatbot->voice_call_first_message,
        ];

        if ($remainingSeconds !== null) {
            $response['remaining_seconds'] = $remainingSeconds;
        }

        return response()->json($response);
    }

    public function end(Request $request, Chatbot $chatbot, string $sessionId): JsonResponse
    {
        $conversation = ChatbotConversation::query()
            ->where('chatbot_id', $chatbot->getKey())
            ->where('session_id', $sessionId)
            ->latest()
            ->first();

        if (! $conversation) {
            return response()->json(['error' => __('No active conversation found.')], 404);
        }

        $duration = (int) $request->input('duration', 0);

        // Demo mode: cap duration to remaining demo limit
        if (Helper::appIsDemo()) {
            $demoSecondsLimit = (int) setting('voice_call_demo_seconds_limit', 30);

            $demoUsedSeconds = (int) ChatbotHistory::query()
                ->whereHas('conversation', function ($q) use ($sessionId) {
                    $q->where('session_id', $sessionId);
                })
                ->where('role', 'voice-call-ended')
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('voice_call_duration');

            $demoRemaining = max(0, $demoSecondsLimit - $demoUsedSeconds);
            $duration = min($duration, $demoRemaining);
        }

        ChatbotHistory::query()->create([
            'chatbot_id'          => $chatbot->getKey(),
            'conversation_id'     => $conversation->getKey(),
            'role'                => 'voice-call-ended',
            'message'             => __('Voice call ended'),
            'voice_call_duration' => $duration,
            'created_at'          => now(),
        ]);

        // Collect transcripts from this voice call session for credit deduction
        $transcripts = ChatbotHistory::query()
            ->where('conversation_id', $conversation->getKey())
            ->whereIn('role', ['voice-transcript-user', 'voice-transcript-assistant'])
            ->where('created_at', '>=', function ($query) use ($conversation) {
                $query->select('created_at')
                    ->from('ext_chatbot_histories')
                    ->where('conversation_id', $conversation->getKey())
                    ->where('role', 'voice-call-started')
                    ->latest()
                    ->limit(1);
            })
            ->pluck('message')
            ->implode(' ');

        if ($transcripts !== '') {
            $provider = setting('voice_call_provider', 'openai_realtime');
            $entityEnum = $provider === 'openai_realtime'
                ? EntityEnum::GPT_4_O_REALTIME_PREVIEW
                : EntityEnum::ELEVENLABS_VOICE_CHATBOT;

            Entity::driver($entityEnum)
                ->forUser($chatbot->user)
                ->input($transcripts)
                ->calculateCredit()
                ->decreaseCredit();
        }

        return response()->json(['success' => true]);
    }

    public function transcript(Request $request, Chatbot $chatbot, string $sessionId): JsonResponse
    {
        if (Helper::appIsDemo()) {
            $demoSecondsLimit = (int) setting('voice_call_demo_seconds_limit', 30);

            $demoUsedSeconds = (int) ChatbotHistory::query()
                ->whereHas('conversation', function ($q) use ($sessionId) {
                    $q->where('session_id', $sessionId);
                })
                ->where('role', 'voice-call-ended')
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('voice_call_duration');

            if ($demoUsedSeconds >= $demoSecondsLimit) {
                return response()->json([
                    'error'    => __('You have reached the daily voice call limit for demo mode.'),
                    'exceeded' => true,
                ], 429);
            }
        }

        $request->validate([
            'role'    => ['required', 'string', 'in:user,assistant'],
            'message' => ['required', 'string'],
        ]);

        $conversation = ChatbotConversation::query()
            ->where('chatbot_id', $chatbot->getKey())
            ->where('session_id', $sessionId)
            ->latest()
            ->first();

        if (! $conversation) {
            return response()->json(['error' => __('No active conversation found.')], 404);
        }

        $role = $request->input('role') === 'user'
            ? 'voice-transcript-user'
            : 'voice-transcript-assistant';

        $history = ChatbotHistory::query()->create([
            'chatbot_id'      => $chatbot->getKey(),
            'conversation_id' => $conversation->getKey(),
            'role'            => $role,
            'message'         => $request->input('message'),
            'created_at'      => now(),
        ]);

        return response()->json([
            'success'    => true,
            'history_id' => $history->getKey(),
        ]);
    }

    /**
     * @return array{ephemeral_key: string, model: string}
     */
    private function getOpenAiCredentials(): array
    {
        $apiKey = ApiHelper::setOpenAiKey();
        $model = 'gpt-realtime';

        $response = Http::withToken($apiKey)
            ->post('https://api.openai.com/v1/realtime/client_secrets', [
                'session' => [
                    'type'  => 'realtime',
                    'model' => $model,
                    'audio' => [
                        'output' => [
                            'voice' => 'verse',
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to create ephemeral token: ' . $response->body());
        }

        $data = $response->json();
        $ephemeralKey = $data['value'] ?? $data['client_secret']['value'] ?? null;

        if (! $ephemeralKey) {
            throw new Exception('Ephemeral token not found in OpenAI response.');
        }

        return [
            'ephemeral_key' => $ephemeralKey,
            'model'         => $model,
        ];
    }

    /**
     * @return array{agent_id: string, signed_url: string|null}
     */
    private function getElevenLabsCredentials(Chatbot $chatbot): array
    {
        $service = app(ElevenLabsService::class);

        $agentId = $chatbot->voice_call_agent_id;

        if (! $agentId) {
            $agentId = $this->createElevenLabsAgent($chatbot, $service);
        }

        $signedUrlResponse = $service->getSignedUrl($agentId);
        $signedUrlData = $signedUrlResponse->getData(true);
        $signedUrl = $signedUrlData['resData']['signed_url'] ?? null;

        return [
            'agent_id'   => $agentId,
            'signed_url' => $signedUrl,
        ];
    }

    private function createElevenLabsAgent(Chatbot $chatbot, ElevenLabsService $service): string
    {
        $firstMessage = $chatbot->voice_call_first_message ?: 'Hello! How can I help you today?';
        $voiceId = setting('voice_call_voice_id');

        $conversationConfig = [
            'agent' => [
                'first_message' => $firstMessage,
                'prompt'        => [
                    'prompt' => $firstMessage,
                ],
            ],
            'tts' => [
                'model_id' => ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH,
            ],
        ];

        if ($voiceId) {
            $conversationConfig['tts']['voice_id'] = $voiceId;
        }

        $response = $service->createAgent(
            conversation_config: $conversationConfig,
            name: $chatbot->title . ' - Voice Call'
        );

        $data = $response->getData(true);
        $agentId = $data['resData']['agent_id'] ?? '';

        $chatbot->update(['voice_call_agent_id' => $agentId]);

        return $agentId;
    }
}
