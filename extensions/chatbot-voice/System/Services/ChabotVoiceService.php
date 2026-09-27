<?php

namespace App\Extensions\ChatbotVoice\System\Services;

use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use App\Extensions\ChatbotVoice\System\Models\ExtVoicechatbotAvatar;
use App\Extensions\ChatbotVoice\System\Models\ExtVoicechatbotTrain;
use App\Services\Ai\ElevenLabsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChabotVoiceService
{
    public function __construct(protected ElevenLabsService $service) {}

    /**
     * return query for ExtVoiceChatbot
     */
    public function query(): Builder
    {
        return ExtVoiceChatbot::query();
    }

    /**
     * voice chabot avatars
     *
     * @return Collection|static[]
     */
    public function avatars(): Collection|array
    {
        return ExtVoicechatbotAvatar::query()
            ->where(function (Builder $query) {
                return $query->where('user_id', Auth::id())->orWhereNull('user_id');
            })
            ->get();
    }

    /**
     * create agent
     *
     * @param  mixed  $args
     */
    public function createAgent($args): JsonResponse
    {
        $conversation_config = [
            'agent' => [
                'first_message' => $args['welcome_message'],
                'prompt'        => [
                    'prompt' => $args['instructions'],
                ],
            ],
            'tts' => [
                'model_id' => ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH,
            ],
        ];

        if (isset($args['language']) && $args['language'] != null && $args['language'] != 'auto') {
            $conversation_config['agent']['language'] = $args['language'];
            if ($args['language'] != 'en') {
                $conversation_config['tts']['model_id'] = ElevenLabsService::DEFAULT_ELEVENLABS_MODEL;
            }
        }

        return $this->service->createAgent(conversation_config: $conversation_config, name: $args['title']);
    }

    /**
     * update agent
     *
     * @return JsonResponse|void
     */
    public function updateAgent(string|int $agent_id)
    {
        $agent = ExtVoiceChatbot::find($agent_id);
        if (empty($agent)) {
            return;
        }

        $conversation_config = [
            'agent' => [
                'first_message' => $agent->welcome_message,
                'prompt'        => [
                    'prompt' => $agent->instructions,
                ],
            ],
            'tts' => [
                'voice_id' => $agent->voice_id,
                'model_id' => ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH,
            ],
        ];

        if (isset($agent->language) && $agent->language != null && $agent->language != 'auto') {
            $conversation_config['agent']['language'] = $agent->language;
            if ($agent->language != 'en') {
                $conversation_config['tts']['model_id'] = ElevenLabsService::DEFAULT_ELEVENLABS_MODEL;
            }
        }

        $this->updateAgentWithKnowledgebase($agent_id);

        return $this->service->updateAgent(agent_id: $agent->agent_id, conversation_config: $conversation_config, name: $agent->name);
    }

    /**
     * delete agent
     */
    public function deleteAgent(string|int $agent_id): JsonResponse
    {
        return $this->service->deleteAgent($agent_id);
    }

    /**
     * get voices from elevenlabs
     */
    public function getVoices(): array
    {
        $res = $this->service->getListOfVoices(page_size: 100);
        if ($res->getData()->status == 'success') {
            return $res->getData()->resData->voices;
        }

        return [];
    }

    /**
     * add knowledgebase to elevenlabs
     *
     * @param  mixed  $content
     */
    public function addKnowledgebase(
        string $type,
        $content,
        ?string $name = null
    ): JsonResponse {
        return match ($type) {
            'text' => $this->service->createKnowledgebaseDocFromText(text: $content, name: $name),
            'url'  => $this->service->createKnowledgebaseDocFromUrl(url: $content, name: $name),
            'file' => $this->service->createKnowledgebaseDocFromFile(file: $content, name: $name),
        };
    }

    /**
     * delete knowledgebase from elevenlabes
     */
    public function deleteKnowledgebase(string $doc_id): JsonResponse
    {
        return $this->service->deleteKnowledgebaseDocument($doc_id);
    }

    /**
     * update the agent with knowledgebase by trianing the content
     */
    public function updateAgentWithKnowledgebase(string|int $chatbot_id): void
    {
        $trains = ExtVoicechatbotTrain::query()
            ->whereNotNull('trained_at')
            ->whereNotNull('doc_id')
            ->where('chatbot_id', $chatbot_id)
            ->get();

        $trainedKnowledges = $trains->map(fn ($train) => [
            'id' 	  => $train->doc_id,
            'name' 	=> $train->name,
            'type' 	=> $train->type,
        ])->all();

        if (empty($trainedKnowledges)) {
            return;
        }

        $config = [
            'agent' => [
                'prompt' => [
                    'knowledge_base' => $trainedKnowledges,
                ],
            ],
        ];

        $agent_id = ExtVoiceChatbot::findOrFail($chatbot_id)?->agent_id;

        if ($agent_id) {
            $this->service->updateAgent(agent_id: $agent_id, conversation_config: $config);
        }
    }
}
