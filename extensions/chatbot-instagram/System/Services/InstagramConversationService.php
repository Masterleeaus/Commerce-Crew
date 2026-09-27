<?php

namespace App\Extensions\ChatbotInstagram\System\Services;

use App\Extensions\Chatbot\System\Enums\InteractionType;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotChannel;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;
use App\Extensions\Chatbot\System\Services\GeneratorService;
use App\Extensions\ChatbotAgent\System\Services\ChatbotForPanelEventAbly;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class InstagramConversationService
{
    protected ?ChatbotConversation $conversation = null;

    protected ?ChatbotHistory $history = null;

    protected ?Chatbot $chatbot = null;

    protected string $humanAgentCommand = 'humanagent';

    protected int $chatbotId;

    protected int $channelId;

    protected ?string $ipAddress = null;

    protected ?array $payload = null;

    protected bool $existMessage = false;

    protected array $incomingMessage = [
        'body'         => '',
        'type'         => 'text',
        'content_type' => 'text',
        'message_id'   => null,
    ];

    public function handle(): void
    {
        $channel = ChatbotChannel::findOrFail($this->channelId);

        $instagram = app(InstagramService::class)->setChatbotChannel($channel);

        $recipient = data_get($this->payload, 'sender.id');
        $messageType = $this->incomingMessage['type'];
        $messageBody = $this->incomingMessage['body'];

        $conversation = $this->conversation;
        $chatbot = $conversation->chatbot;

        if ($conversation->connect_agent_at) {
            if ($conversation->last_activity_at->diffInMinutes() > 10) {
                $this->closeInactiveConversation($conversation, $instagram, $recipient);

                return;
            }

            return;
        }

        $conversation->update(['last_activity_at' => now()]);

        if ($messageBody !== '') {
            $this->processIncomingMessage($messageBody, $messageType, $conversation, $chatbot, $instagram, $recipient);
        } else {
            $this->sendUnsupportedMessageType($conversation, $chatbot, $instagram, $recipient);
        }
    }

    protected function closeInactiveConversation(ChatbotConversation $conversation, InstagramService $instagram, string $recipient): void
    {
        $conversation->update(['connect_agent_at' => null]);
        $message = trans('The conversation has been closed due to inactivity.');
        $this->insertMessage($conversation, $message, 'assistant', $conversation->chatbot->ai_model);
        $instagram->sendText($message, $recipient);
    }

    protected function processIncomingMessage(string $messageBody, string $messageType, ChatbotConversation $conversation, Chatbot $chatbot, InstagramService $instagram, string $recipient): void
    {
        if ($messageType === 'text' && $this->isHumanAgentCommand($chatbot, $messageBody)) {
            $this->connectToHumanAgent($chatbot, $conversation, $instagram, $recipient);

            return;
        }

        $response = $this->generateResponse($messageBody) ?? trans("Sorry, I can't answer right now.");

        if (! $conversation->connect_agent_at && $chatbot->interaction_type === InteractionType::SMART_SWITCH && MarketplaceHelper::isRegistered('chatbot-agent')) {
            $response .= "\n\n\nTo speak with a live support agent, please enter the #{$this->humanAgentCommand} command.";
        }

        $instagram->sendText($response, $recipient);
        $this->insertMessage($conversation, $response, 'assistant', $chatbot->ai_model);
    }

    protected function sendUnsupportedMessageType(ChatbotConversation $conversation, Chatbot $chatbot, InstagramService $instagram, string $recipient): void
    {
        $message = trans('The chatbot does not support the type of message you are sending.');
        $this->insertMessage($conversation, $message, 'assistant', $chatbot->ai_model);
        $instagram->sendText($message, $recipient);
    }

    protected function connectToHumanAgent(Chatbot $chatbot, ChatbotConversation $conversation, InstagramService $instagram, string $recipient): void
    {
        $conversation->update(['connect_agent_at' => now()]);

        if ($connectMessage = $chatbot->connect_message) {
            $chatbotHistory = $this->insertMessage($conversation, $connectMessage, 'assistant', $chatbot->ai_model, true);
            $instagram->sendText($connectMessage, $recipient);
            $this->dispatchAgentEvent($chatbot, $conversation, $chatbotHistory);
        }
    }

    protected function dispatchAgentEvent(Chatbot $chatbot, ChatbotConversation $conversation, ?ChatbotHistory $chatbotHistory): void
    {
        if (MarketplaceHelper::isRegistered('chatbot-agent')) {
            try {
                ChatbotForPanelEventAbly::dispatch($chatbot, $conversation->load('lastMessage'), $chatbotHistory);
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }

    protected function isHumanAgentCommand(Chatbot $chatbot, string $message): bool
    {
        return str_contains($message, $this->humanAgentCommand) && $chatbot->interaction_type === InteractionType::SMART_SWITCH;
    }

    protected function generateResponse(string $prompt): ?string
    {
        return app(GeneratorService::class)
            ->setChatbot($this->conversation->chatbot)
            ->setConversation($this->conversation)
            ->setPrompt($prompt)
            ->generate();
    }

    public function insertMessage(ChatbotConversation $conversation, string $message, string $role, string $model, bool $forcePanelEvent = false)
    {
        $chatbot = $conversation->getAttribute('chatbot');

        $isUserMessage = $role === 'user';

        $chatbotHistory = ChatbotHistory::query()->create([
            'chatbot_id'      => $conversation->getAttribute('chatbot_id'),
            'conversation_id' => $conversation->getAttribute('id'),
            'message_id'      => $isUserMessage ? ($this->incomingMessage['message_id'] ?? data_get($this->payload, 'message.mid')) : null,
            'role'            => $role,
            'model'           => Helper::setting('openai_default_model'),
            'message'         => $message,
            'message_type'    => $isUserMessage ? ($this->incomingMessage['type'] ?? 'text') : 'text',
            'content_type'    => $isUserMessage ? ($this->incomingMessage['content_type'] ?? 'text') : 'text',
            'created_at'      => now(),
            'read_at'         => $conversation->getAttribute('connect_agent_at') ? null : now(),
        ]);

        $this->history = $chatbotHistory;

        $sendEvent = $conversation->getAttribute('connect_agent_at') && $chatbot->getAttribute('interaction_type') !== InteractionType::AUTOMATIC_RESPONSE && $role === 'user';

        if ($sendEvent || $forcePanelEvent) {
            $conversation->touch();
            if (MarketplaceHelper::isRegistered('chatbot-agent')) {
                try {
                    ChatbotForPanelEventAbly::dispatch(
                        $chatbot,
                        $conversation->load('lastMessage'),
                        $chatbotHistory
                    );
                } catch (Exception $e) {
                    Log::error($e->getMessage());
                }
            }
        }

        return $chatbotHistory;
    }

    public function storeHistory(Builder|Model|null $conversation = null): void
    {
        $conversation ??= $this->conversation;

        $this->existMessage = ChatbotHistory::query()
            ->where('conversation_id', $conversation->getKey())
            ->exists();

        $this->history = ChatbotHistory::create([
            'chatbot_id'      => $conversation->getAttribute('chatbot_id'),
            'conversation_id' => $conversation->getKey(),
            'message_id'      => $this->incomingMessage['message_id'] ?? data_get($this->payload, 'message.mid'),
            'role'            => 'user',
            'model'           => Helper::setting('openai_default_model'),
            'message'         => $this->incomingMessage['body'] ?? data_get($this->payload, 'message.text', ''),
            'message_type'    => $this->incomingMessage['type'] ?? data_get($this->payload, 'message.type') ?? 'text',
            'content_type'    => $this->incomingMessage['content_type'] ?? data_get($this->payload, 'message.type') ?? 'text',
            'read_at'         => $conversation->getAttribute('connect_agent_at') ? null : now(),
            'created_at'      => now(),
        ]);
    }

    public function storeConversation(): Builder|Model|ChatbotConversation
    {
        $this->chatbot = Chatbot::find($this->chatbotId);

        $this->conversation = ChatbotConversation::firstOrCreate([
            'chatbot_id'          => $this->chatbotId,
            'chatbot_channel'     => 'instagram',
            'chatbot_channel_id'  => $this->channelId,
            'customer_channel_id' => $this->getCustomerChannelId(),
        ], [
            'session_id'        => md5(uniqid(mt_rand(), true)),
            'conversation_name' => data_get($this->payload, 'sender.id'),
            'ip_address'        => $this->ipAddress,
            'connect_agent_at'  => $this->chatbot->getAttribute('interaction_type') === InteractionType::HUMAN_SUPPORT ? now() : null,
            'last_activity_at'  => now(),
            'customer_payload'  => [
                'From' => $this->getCustomerChannelId(),
            ],
        ]);

        $this->existMessage = ChatbotHistory::query()
            ->where('conversation_id', $this->conversation->getKey())
            ->exists();

        $this->conversation->setRelation('chatbot', $this->chatbot);

        return $this->conversation;
    }

    public function getCustomerChannelId(): ?string
    {
        return data_get($this->payload, 'sender.id');
    }

    public function setChatbotId(int $chatbotId): self
    {
        $this->chatbotId = $chatbotId;

        return $this;
    }

    public function setChannelId(int $channelId): self
    {
        $this->channelId = $channelId;

        return $this;
    }

    public function setIpAddress(?int $ipAddress = null): self
    {
        if ($ipAddress) {
            $this->ipAddress = $ipAddress;
        } else {
            $this->ipAddress = request()?->header('cf-connecting-ip') ?? request()?->ip();
        }

        return $this;
    }

    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;
        $this->incomingMessage = $this->resolveIncomingMessage($payload);

        return $this;
    }

    public function getChatbot(): Model|Builder|Chatbot|null
    {
        return $this->chatbot;
    }

    protected function resolveIncomingMessage(?array $payload): array
    {
        $message = [
            'body'         => '',
            'type'         => 'text',
            'content_type' => 'text',
            'message_id'   => data_get($payload, 'message.mid'),
        ];

        if (! $payload) {
            return $message;
        }

        if ($text = data_get($payload, 'message.text')) {
            $message['body'] = $text;

            return $message;
        }

        if ($quickReply = data_get($payload, 'message.quick_reply.payload')) {
            $message['body'] = $quickReply;
            $message['type'] = 'quick_reply';
            $message['content_type'] = 'quick_reply';

            return $message;
        }

        if ($attachment = data_get($payload, 'message.attachments.0')) {
            $type = data_get($attachment, 'type', 'attachment');
            $url = data_get($attachment, 'payload.url');
            $description = strtoupper($type);
            $body = "[{$description}]";
            if ($url) {
                $body .= ' ' . $url;
            }

            $message['body'] = $body;
            $message['type'] = $type;
            $message['content_type'] = $type;

            return $message;
        }

        if ($stickerId = data_get($payload, 'message.sticker_id')) {
            $message['body'] = '[STICKER] ' . $stickerId;
            $message['type'] = 'sticker';
            $message['content_type'] = 'sticker';

            return $message;
        }

        return $message;
    }
}
