<?php

namespace App\Extensions\ChatbotReview\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotConversation;

class ChatbotReviewService
{
    public function requestReview(ChatbotConversation $conversation, string $reason): ChatbotConversation
    {
        $conversation->loadMissing('chatbot');

        if (! $conversation->chatbot?->getAttribute('is_review_enabled')) {
            return $conversation;
        }

        if ($conversation->getAttribute('review_requested_at')) {
            return $conversation;
        }

        $conversation->forceFill([
            'review_requested_at'   => now(),
            'review_request_reason' => $reason,
        ])->save();

        return $conversation;
    }

    public function submitReview(ChatbotConversation $conversation, string $message, ?string $selectedResponse = null): ChatbotConversation
    {
        $conversation->forceFill([
            'review_message'           => $message,
            'review_selected_response' => $selectedResponse,
            'review_submitted_at'      => now(),
        ])->save();

        return $conversation;
    }
}
