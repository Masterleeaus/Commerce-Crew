<?php

namespace App\Extensions\ChatbotTelegram\System\Services\Telegram;

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
use Illuminate\Support\Fluent;

class TelegramConversationService
{
    protected string $humanAgentCommand = 'humanagent';

    protected bool $existMessage = false;

    protected int $channelId;

    protected ?Chatbot $chatbot = null;

    protected ?ChatbotConversation $conversation = null;

    protected ?ChatbotHistory $history = null;

    protected ?Fluent $payload = null;

    protected ?string $ipAddress = null;

    public function handleTelegram(): void
    {
        $telegram = app(TelegramService::class)->setChannel(ChatbotChannel::find($this->channelId));
        $conversation = $this->conversation;
        $chatbot = $conversation->chatbot;
        $customerChannelId = $this->getCustomerChannelId();

        if ($conversation->connect_agent_at) {
            if ($conversation->last_activity_at->diffInMinutes() > 10) {
                $this->closeInactiveConversation($conversation, $telegram, $customerChannelId);

                return;
            }

            return;
        }

        $conversation->update(['last_activity_at' => now()]);

        $messageBody = data_get($this->payload, 'message.text');

        if (is_string($messageBody)) {
            $this->processTextMessage($messageBody, $conversation, $chatbot, $telegram, $customerChannelId);
        } else {
            $this->sendUnsupportedMessageType($conversation, $chatbot, $telegram, $customerChannelId);
        }
    }

    protected function processTextMessage(string $messageBody, ChatbotConversation $conversation, Chatbot $chatbot, TelegramService $telegram, $customerChannelId): void
    {
        if (! $this->existMessage) {
            $this->sendWelcomeMessage($chatbot, $conversation, $telegram, $customerChannelId);

            return;
        }

        if ($this->isHumanAgentCommand($chatbot, $messageBody)) {
            $this->connectToHumanAgent($chatbot, $conversation, $telegram, $customerChannelId);

            return;
        }

        $response = $this->generateResponse($messageBody) ?? trans("Sorry, I can't answer right now.");

        if (! $conversation->connect_agent_at && $chatbot->interaction_type === InteractionType::SMART_SWITCH && MarketplaceHelper::isRegistered('chatbot-agent')) {
            $response .= "\n\n\nTo speak with a live support agent, please enter the #{$this->humanAgentCommand} command.";
        }

        $telegram->sendText($response, $customerChannelId);

        $this->insertMessage($conversation, $response, 'assistant', $chatbot->ai_model);
    }

    protected function closeInactiveConversation(ChatbotConversation $conversation, TelegramService $telegram, $customerChannelId): void
    {
        $conversation->update(['connect_agent_at' => null]);
        $message = trans('The conversation has been closed due to inactivity.');
        $this->insertMessage($conversation, $message, 'assistant', $conversation->chatbot->ai_model);
        $telegram->sendText($message, $customerChannelId);
    }

    protected function sendWelcomeMessage(Chatbot $chatbot, ChatbotConversation $conversation, TelegramService $telegram, $customerChannelId): void
    {
        if ($welcomeMessage = $chatbot->welcome_message) {
            $this->insertMessage($conversation, $welcomeMessage, 'assistant', $chatbot->ai_model);
            $telegram->sendText($welcomeMessage, $customerChannelId);
        }
    }

    protected function sendUnsupportedMessageType(ChatbotConversation $conversation, Chatbot $chatbot, TelegramService $telegram, $customerChannelId): void
    {
        $message = trans('The chatbot does not support the type of message you are sending.');
        $this->insertMessage($conversation, $message, 'assistant', $chatbot->ai_model);
        $telegram->sendText($message, $customerChannelId);
    }

    protected function connectToHumanAgent(Chatbot $chatbot, ChatbotConversation $conversation, TelegramService $telegram, $customerChannelId): void
    {
        $conversation->update(['connect_agent_at' => now()]);

        if ($connectMessage = $chatbot->connect_message) {
            $chatbotHistory = $this->insertMessage($conversation, $connectMessage, 'assistant', $chatbot->ai_model, true);
            $telegram->sendText($connectMessage, $customerChannelId);

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

    protected function generateResponse(string $prompt): ?string
    {
        return app(GeneratorService::class)
            ->setChatbot($this->conversation->chatbot)
            ->setConversation($this->conversation)
            ->setPrompt($prompt)
            ->generate();
    }

    protected function isHumanAgentCommand(Chatbot $chatbot, string $message): bool
    {
        return str_contains($message, $this->humanAgentCommand) && $chatbot->getAttribute('interaction_type') === InteractionType::SMART_SWITCH;
    }

    public function insertMessage(ChatbotConversation $conversation, string $message, string $role, string $model, bool $forcePanelEvent = false)
    {
        $chatbot = $conversation->getAttribute('chatbot');

        $chatbotHistory = ChatbotHistory::create([
            'chatbot_id'      => $conversation->getAttribute('chatbot_id'),
            'conversation_id' => $conversation->getAttribute('id'),
            'message_id'      => data_get($this->payload, 'update_id'),
            'role'            => $role,
            'model'           => Helper::setting('openai_default_model'),
            'message'         => $message,
            'message_type'    => 'text',
            'content_type'    => 'text',
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

    public function storeConversation(): Builder|Model|ChatbotConversation|null
    {
        $this->chatbot = Chatbot::find($this->chatbotId);

        if (! $this->chatbot) {
            return null;
        }

        $customer_channel_id = $this->getCustomerChannelId();

        $this->conversation = ChatbotConversation::firstOrCreate([
            'chatbot_id'          => $this->chatbotId,
            'chatbot_channel'     => 'telegram',
            'chatbot_channel_id'  => $this->channelId,
            'customer_channel_id' => $customer_channel_id,
        ], [
            'session_id'        => md5(uniqid(mt_rand(), true)),
            'conversation_name' => data_get($this->payload, 'message.chat.first_name') . ' ' . data_get($this->payload, 'message.chat.last_name'),
            'ip_address'        => $this->ipAddress,
            'connect_agent_at'  => $this->chatbot?->getAttribute('interaction_type') === InteractionType::HUMAN_SUPPORT ? now() : null,
            'customer_payload'  => [
                'From'       => $customer_channel_id,
            ],
            'last_activity_at' => now(),
        ]);

        $this->existMessage = ChatbotHistory::query()
            ->where('conversation_id', $this->conversation->getKey())
            ->exists();

        $this->conversation->setRelation('chatbot', $this->chatbot);

        return $this->conversation;
    }

    public function getCustomerChannelId()
    {
        return data_get($this->payload, 'message.chat.id');
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

    public function setChatbot(Model|Builder|Chatbot|null $chatbot): self
    {
        $this->chatbot = $chatbot;

        return $this;
    }

    public function getChatbot(): Model|Builder|Chatbot|null
    {
        return $this->chatbot;
    }

    public function setConversation(Model|ChatbotConversation|Builder|null $conversation): self
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function setHistory(Model|ChatbotHistory|Builder|null $history): self
    {
        $this->history = $history;

        return $this;
    }

    public function setChatbotId(int $chatbotId): self
    {
        $this->chatbotId = $chatbotId;

        return $this;
    }

    public function getChatbotId(): int
    {
        return $this->chatbotId;
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

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getPayload(): null|array|Fluent
    {
        return $this->payload;
    }

    public function setPayload(?array $payload = null): self
    {
        $this->payload = new Fluent($payload ?: []);

        return $this;
    }
}
