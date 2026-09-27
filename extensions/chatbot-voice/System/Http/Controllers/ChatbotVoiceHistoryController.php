<?php

namespace App\Extensions\ChatbotVoice\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\ChatbotVoice\System\Http\Requests\VoiceChatHistoryStoreRequest;
use App\Extensions\ChatbotVoice\System\Http\Resources\ChatbotConversationHistoryResource;
use App\Extensions\ChatbotVoice\System\Models\ExtVoicechabotConversation;
use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use App\Http\Controllers\Controller;
use App\Models\Usage;
use App\Services\Ai\ElevenLabsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatbotVoiceHistoryController extends Controller
{
    /**
     * load conversation history with pagination
     */
    public function loadConversationWithPaginate(): AnonymousResourceCollection
    {
        $voiceChatbots = ExtVoiceChatbot::where('user_id', auth()->id())
            ->pluck('uuid')->toArray();

        $inProgressConvs = ExtVoicechabotConversation::where('status', 'in-progress')
            ->whereIn('chatbot_uuid', $voiceChatbots)
            ->orWhere('status', 'processing')
            ->get();

        foreach ($inProgressConvs as $conv) {
            self::storeTranscripts($conv);
        }

        return ChatbotConversationHistoryResource::collection(
            ExtVoicechabotConversation::where('status', 'done')
                ->whereIn('chatbot_uuid', $voiceChatbots)
                ->with('chat_histories')
                ->paginate()
        );
    }

    /**
     * store new conversation
     */
    public function storeConversation(string $uuid, VoiceChatHistoryStoreRequest $request): JsonResponse
    {
        $chatbot = ExtVoiceChatbot::whereUuid($uuid)->first();

        if (empty($chatbot)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid uuid',
            ], 404);
        }

        try {
            $conversation = $chatbot->conversations()->where('conversation_id')->first();
            if (! $conversation) {
                $conversation = $chatbot->conversations()->create([
                    'conversation_id' => $request->validated()['conversation_id'],
                ]);
            } else {
                $conversation->chat_histories()->delete();
            }

            self::storeTranscripts($conversation);

            return response()->json([
                'status' => 'success',
            ]);
        } catch (Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'create conversation failed',
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * fetch transcripts from elevenlabs and store it on server database
     *
     * @param  ExtVoicechabotConversation  $conversation  server database conversation
     */
    public static function storeTranscripts(ExtVoicechabotConversation $conversation): void
    {
        $service = new ElevenLabsService;
        $res = $service->getConversationDetail($conversation->conversation_id);

        $chatbotUUID = $conversation->chatbot_uuid;
        $chatbot = ExtVoiceChatbot::whereUuid($chatbotUUID)->first();
        if (! empty($chatbot)) {
            $cost = data_get($res->original, 'resData.metadata.cost');
            $cost = $cost ?? 0;
            $chars = Str::random($cost);
            $user = $chatbot->user;
            $driver = Entity::driver(EntityEnum::ELEVENLABS_VOICE_CHATBOT)->forUser($user);
            $driver->input($chars)->calculateCredit()->decreaseCredit();
            Usage::getSingle()->updateWordCounts($driver->calculate());
        }

        if ($res->getData()->status == 'error') {
            return;
        }

        try {
            $resData = $res->getData()->resData;

            $conversation->status = $resData->status;
            $conversation->save();

            if ($resData->status != 'in-progress' && $resData->status != 'processing') {
                $transcripts = $resData->transcript;

                foreach ($transcripts as $transcript) {
                    $conversation->chat_histories()->create([
                        'role'    => $transcript->role,
                        'message' => $transcript->message,
                    ]);
                }
            }
        } catch (Throwable $th) {
            Log::error('store chat history error: ', [$th->getMessage()]);
        }
    }
}
