<?php

namespace App\Extensions\Chatbot\System\Policies;

use App\Extensions\Chatbot\System\Models\ChatbotKnowledgeBaseArticle;
use App\Models\User;

class ChatbotKnowledgeBaseArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChatbotKnowledgeBaseArticle $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function edit(User $user, ChatbotKnowledgeBaseArticle $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function update(User $user, ChatbotKnowledgeBaseArticle $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function delete(User $user, ChatbotKnowledgeBaseArticle $item): bool
    {
        return $user->id === $item->user_id;
    }
}
