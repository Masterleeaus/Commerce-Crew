<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Policies;

use App\Extensions\Chatbot\System\Models\Chatbot;

final class ExtensionAccessPolicy
{
    public function before(mixed $user, string $ability): ?bool
    {
        if ($user !== null && method_exists($user, 'isAdmin') && (bool) $user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function use(mixed $user, Chatbot $chatbot): bool
    {
        return $this->owns($user, $chatbot) || $this->permitted($user, 'chatbot-ecommerce.read');
    }

    public function administer(mixed $user, Chatbot $chatbot, string $permission = 'chatbot-ecommerce.read'): bool
    {
        return $this->owns($user, $chatbot) || $this->permitted($user, $permission);
    }

    public function manageCredentials(mixed $user, Chatbot $chatbot): bool
    {
        return $this->administer($user, $chatbot, 'chatbot-ecommerce.manage-credentials');
    }

    public function managePayments(mixed $user, Chatbot $chatbot): bool
    {
        return $this->administer($user, $chatbot, 'chatbot-ecommerce.manage-payments');
    }

    public function manageMarketplace(mixed $user, Chatbot $chatbot): bool
    {
        return $this->administer($user, $chatbot, 'chatbot-ecommerce.manage-marketplace-connections');
    }

    private function owns(mixed $user, Chatbot $chatbot): bool
    {
        return $user !== null
            && (int) $chatbot->getAttribute('user_id') === (int) $user->getAuthIdentifier();
    }

    private function permitted(mixed $user, string $permission): bool
    {
        return $user !== null && method_exists($user, 'can') && (bool) $user->can($permission);
    }
}
