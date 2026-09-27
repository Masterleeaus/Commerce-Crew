<?php
namespace App\Extensions\Chatbot\System\Contracts;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
interface ConversationRuntimeInterface { public function create(array $attributes): ChatbotConversation; public function archive(ChatbotConversation $conversation): ChatbotConversation; public function merge(ChatbotConversation $target, ChatbotConversation $source): ChatbotConversation; public function summarize(ChatbotConversation $conversation, bool $force = false): string; public function search(array $filters); }
