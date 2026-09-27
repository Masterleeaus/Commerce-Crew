<?php

namespace App\Extensions\ChatbotMessenger\System\Services;

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

class MessengerConversationService
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

    public function handle(): void
    {
        $messenger = app(MessengerService::class)
            ->setChatbotChannel(ChatbotChannel::find($this->channelId));

        $recipient = data_get($this->payload, 'sender.id');
        $messageType = data_get($this->payload, 'MessageType') ?: 'text';
        $messageBody = data_get($this->payload, 'message.text');

        $conversation = $this->conversation;
        $chatbot = $conversation->chatbot;

        if ($conversation->connect_agent_at) {
            if ($conversation->last_activity_at->diffInMinutes() > 10) {
                $this->closeInactiveConversation($conversation, $messenger, $recipient);

                return;
            }

            return;
        }

        $conversation->update(['last_activity_at' => now()]);

        if ($messageType === 'text' && is_string($messageBody)) {
            $this->processTextMessage($messageBody, $conversation, $chatbot, $messenger, $recipient);
        } else {
            $this->sendUnsupportedMessageType($conversation, $chatbot, $messenger, $recipient);
        }
    }

    protected function closeInactiveConversation(ChatbotConversation $conversation, MessengerService $messenger, string $recipient): void
    {
        $conversation->update(['connect_agent_at' => null]);
        $message = trans('The conversation has been closed due to inactivity.');
        $this->insertMessage($conversation, $message, 'assistant', $conversation->chatbot->ai_model);
        $messenger->sendText($message, $recipient);
    }

    protected function processTextMessage(string $messageBody, ChatbotConversation $conversation, Chatbot $chatbot, MessengerService $messenger, string $recipient): void
    {
        if ($this->isHumanAgentCommand($chatbot, $messageBody)) {
            $this->connectToHumanAgent($chatbot, $conversation, $messenger, $recipient);

            return;
        }

        $response = $this->generateResponse($messageBody) ?? trans("Sorry, I can't answer right now.");

        if (! $conversation->connect_agent_at && $chatbot->interaction_type === InteractionType::SMART_SWITCH && MarketplaceHelper::isRegistered('chatbot-agent')) {
            $response .= "\n\n\nTo speak with a live support agent, please enter the #{$this->humanAgentCommand} command.";
        }

        $messenger->sendText($response, $recipient);
        $this->insertMessage($conversation, $response, 'assistant', $chatbot->ai_model);
    }

    protected function sendUnsupportedMessageType(ChatbotConversation $conversation, Chatbot $chatbot, MessengerService $messenger, string $recipient): void
    {
        $message = trans('The chatbot does not support the type of message you are sending.');
        $this->insertMessage($conversation, $message, 'assistant', $chatbot->ai_model);
        $messenger->sendText($message, $recipient);
    }

    protected function connectToHumanAgent(Chatbot $chatbot, ChatbotConversation $conversation, MessengerService $messenger, string $recipient): void
    {
        $conversation->update(['connect_agent_at' => now()]);

        if ($connectMessage = $chatbot->connect_message) {
            $chatbotHistory = $this->insertMessage($conversation, $connectMessage, 'assistant', $chatbot->ai_model, true);
            $messenger->sendText($connectMessage, $recipient);
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

        $chatbotHistory = ChatbotHistory::query()->create([
            'chatbot_id'      => $conversation->getAttribute('chatbot_id'),
            'conversation_id' => $conversation->getAttribute('id'),
            'message_id'      => data_get($this->payload, 'message.mid'),
            'role'            => $role,
            'model'           => Helper::setting('openai_default_model'),
            'message'         => $message,
            'message_type'    => data_get($this->payload, 'MessageType') ?? 'text',
            'content_type'    => data_get($this->payload, 'MediaContentType0') ?? 'text',
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
            ->where('conversation_id', $conversation?->getKey())
            ->exists();

        $this->history = ChatbotHistory::create([
            'chatbot_id'      => $conversation?->getAttribute('chatbot_id'),
            'conversation_id' => $conversation?->getKey(),
            'message_id'      => data_get($this->payload, 'message.mid'),
            'role'            => 'user',
            'model'           => Helper::setting('openai_default_model'),
            'message'         => data_get($this->payload, 'message.text', ''),
            'message_type'    => data_get($this->payload, 'MessageType'),
            'content_type'    => data_get($this->payload, 'MediaContentType0') ?? 'text',
            'read_at'         => $conversation?->getAttribute('connect_agent_at') ? null : now(),
            'created_at'      => now(),
        ]);
    }

    public function storeConversation(): Builder|Model|ChatbotConversation
    {
        $this->chatbot = Chatbot::find($this->chatbotId);

        $this->conversation = ChatbotConversation::firstOrCreate([
            'chatbot_id'          => $this->chatbotId,
            'chatbot_channel'     => 'messenger',
            'chatbot_channel_id'  => $this->channelId,
            'customer_channel_id' => $this->getCustomerChannelId(),
        ], [
            'session_id'        => md5(uniqid(mt_rand(), true)),
            'conversation_name' => data_get($this->payload, 'sender.id'),
            'ip_address'        => $this->ipAddress,
            'connect_agent_at'  => $this->chatbot->getAttribute('interaction_type') === InteractionType::HUMAN_SUPPORT ? now() : null,
            'last_activity_at'  => now(),
            'customer_payload'  => [
                'From'       => $this->getCustomerChannelId(),
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

    public function getChatbotId(): int
    {
        return $this->chatbotId;
    }

    public function setChatbotId(int $chatbotId): self
    {
        $this->chatbotId = $chatbotId;

        return $this;
    }

    public function getChannelId(): int
    {
        return $this->channelId;
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

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getChatbot(): Model|Builder|Chatbot|null
    {
        return $this->chatbot;
    }
}
