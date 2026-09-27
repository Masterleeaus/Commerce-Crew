<?php
namespace App\Extensions\Chatbot\System\Events;
class MessageCreated { public readonly int $messageId; public readonly int $conversationId; public function __construct(int $messageId, int $conversationId) { $this->messageId=$messageId; $this->conversationId=$conversationId; } }
