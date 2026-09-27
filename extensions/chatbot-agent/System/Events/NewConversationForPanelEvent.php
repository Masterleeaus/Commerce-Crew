<?php

namespace App\Extensions\ChatbotAgent\System\Events;

use App\Extensions\Chatbot\System\Http\Resources\Admin\ChatbotConversationResource;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewConversationForPanelEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $chatbotConversation;

    private string $eventChannel = 'panel-new-conversation-';

    public function __construct(Chatbot $chatbot, ChatbotConversation $chatbotConversation)
    {
        $this->eventChannel .= $chatbot->getAttribute('user_id');

        $this->chatbotConversation = ChatbotConversationResource::make($chatbotConversation)->jsonSerialize();
    }

    public function broadcastOn(): Channel|array
    {
        return new Channel($this->eventChannel);
    }

    public function broadcastAs(): string
    {
        return 'new-conversation';
    }
}
