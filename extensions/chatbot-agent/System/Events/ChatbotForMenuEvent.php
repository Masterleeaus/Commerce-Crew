<?php

namespace App\Extensions\ChatbotAgent\System\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatbotForMenuEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    private string $eventChannel = 'panel-menu-user-';

    public int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->eventChannel .= $userId;
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
