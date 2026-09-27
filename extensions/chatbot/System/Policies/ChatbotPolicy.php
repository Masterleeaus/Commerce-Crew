<?php

namespace App\Extensions\Chatbot\System\Policies;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Models\User;

class ChatbotPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Chatbot $chatbot): bool
    {
        return $user->id === $chatbot->user_id;
    }

    public function train(User $user, Chatbot $chatbot): bool
    {
        return $user->id === $chatbot->user_id;
    }

    public function update(User $user, Chatbot $chatbot): bool
    {
        return $user->id === $chatbot->user_id;
    }

    public function delete(User $user, Chatbot $chatbot): bool
    {
        return $user->id === $chatbot->user_id;
    }
}
