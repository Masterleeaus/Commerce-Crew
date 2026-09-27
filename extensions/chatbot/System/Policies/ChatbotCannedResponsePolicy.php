<?php

namespace App\Extensions\Chatbot\System\Policies;

use App\Extensions\Chatbot\System\Models\ChatbotCannedResponse;
use App\Models\User;

class ChatbotCannedResponsePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChatbotCannedResponse $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function edit(User $user, ChatbotCannedResponse $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function update(User $user, ChatbotCannedResponse $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function delete(User $user, ChatbotCannedResponse $item): bool
    {
        return $user->id === $item->user_id;
    }
}
