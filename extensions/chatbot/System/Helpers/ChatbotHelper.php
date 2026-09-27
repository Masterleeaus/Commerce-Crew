<?php

namespace App\Extensions\Chatbot\System\Helpers;

use App\Helpers\Classes\MarketplaceHelper;
use App\Models\Plan;

class ChatbotHelper
{
    private const CHANNELS = [
        'telegram'  => 'chatbot-telegram',
        'whatsapp'  => 'chatbot-whatsapp',
        'messenger' => 'chatbot-messenger',
        'instagram' => 'chatbot-instagram',
    ];

    public static function existChannels(): bool
    {
        return count(self::allowedChannelKeys()) > 0;
    }

    public static function channels(?Plan $plan = null): array
    {
        return self::allowedChannelKeys($plan);
    }

    public static function installedChannelKeys(): array
    {
        return collect(self::CHANNELS)
            ->filter(fn ($slug) => MarketplaceHelper::isRegistered($slug))
            ->keys()
            ->toArray();
    }

    public static function channelIsInstalled(string $channel): bool
    {
        return isset(self::CHANNELS[$channel]) && MarketplaceHelper::isRegistered(self::CHANNELS[$channel]);
    }

    public static function allowedChannelKeys(?Plan $plan = null): array
    {
        return collect(self::installedChannelKeys())
            ->filter(fn ($channel) => self::planAllowsChannel($channel, $plan))
            ->values()
            ->toArray();
    }

    public static function channelEnabledForAuthUser(string $channel): bool
    {
        return self::channelIsInstalled($channel) && self::planAllowsChannel($channel);
    }

    public static function planAllowsChannel(string $channel, ?Plan $plan = null): bool
    {
        if (! isset(self::CHANNELS[$channel])) {
            return false;
        }

        $plan = $plan ?: auth()->user()?->activePlan();

        if (! $plan) {
            return true;
        }

        $channels = $plan->chatbot_channels ?? [];

        if ($channels === []) {
            return true;
        }

        if (! array_key_exists($channel, $channels)) {
            return true;
        }

        return (bool) $channels[$channel];
    }

    public static function planAllowsHumanAgent(?Plan $plan = null): bool
    {
        $user = auth()->user();

        if (! $user) {
            return true;
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $plan = $plan ?: $user->relationPlan;

        if (! $plan) {
            return false;
        }

        if (is_null($plan->chatbot_human_agent)) {
            return true;
        }

        return (bool) $plan->chatbot_human_agent;
    }
}
