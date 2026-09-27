<?php

namespace App\Extensions\Chatbot\System\Contracts;

use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;

interface MessageRuntimeInterface
{
    public function create(ChatbotConversation $conversation, array $payload): ChatbotHistory;
    public function retry(ChatbotHistory $message): ChatbotHistory;
    public function markSent(ChatbotHistory $message, ?string $providerId = null): ChatbotHistory;
    public function markDelivered(ChatbotHistory $message, ?string $providerId = null): ChatbotHistory;
    public function markRead(ChatbotHistory $message): ChatbotHistory;
    public function markFailed(ChatbotHistory $message, string $reason, array $payload = []): ChatbotHistory;
}
