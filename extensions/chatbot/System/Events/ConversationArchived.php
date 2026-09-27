<?php
namespace App\Extensions\Chatbot\System\Events;
class ConversationArchived { public readonly int $conversationId; public function __construct(int $conversationId) { $this->conversationId=$conversationId; } }
