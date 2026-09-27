<?php

namespace App\Extensions\ChatbotAgent\System\Events;

use App\Extensions\Chatbot\System\Http\Resources\Api\ChatbotHistoryResource;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatbotForPanelEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $conversationId;

    public array $history;

    private string $eventChannel = 'panel-conversation-';

    public function __construct(ChatbotHistory $history, Chatbot $chatbot)
    {
        $this->conversationId = $history->getAttribute('conversation_id');
        $this->eventChannel .= $chatbot->getAttribute('user_id');
        $this->history = ChatbotHistoryResource::make($history)->jsonSerialize();
    }

    public function broadcastOn(): Channel|array
    {
        return new Channel($this->eventChannel);
    }

    public function broadcastAs(): string
    {
        return 'new-message';
    }
}
