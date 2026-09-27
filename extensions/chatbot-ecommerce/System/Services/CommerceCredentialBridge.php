<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;

final class CommerceCredentialBridge
{
    private static bool $registered = false;

    /** @var list<string> */
    private const LEGACY_FIELDS = ['shopify_access_token', 'woocommerce_consumer_key', 'woocommerce_consumer_secret'];

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered = true;

        Chatbot::retrieved(static function (Chatbot $chatbot): void {
            $chatbot->makeHidden(self::LEGACY_FIELDS);
            foreach (self::LEGACY_FIELDS as $field) {
                $chatbot->setAttribute($field, null);
            }
        });

        Chatbot::saving(static function (Chatbot $chatbot): void {
            $chatbot->makeHidden(self::LEGACY_FIELDS);
            $pending = [];
            foreach (self::LEGACY_FIELDS as $field) {
                $value = trim((string) $chatbot->getAttribute($field));
                if ($value !== '') {
                    $pending[$field] = $value;
                }
                $chatbot->setAttribute($field, null);
            }
            if ($pending !== []) {
                $chatbot->setRelation('__commerce_pending_credentials', $pending);
            }
        });

        Chatbot::saved(static function (Chatbot $chatbot): void {
            $chatbot->makeHidden(self::LEGACY_FIELDS);
            $pending = $chatbot->getRelation('__commerce_pending_credentials') ?? [];
            $chatbot->unsetRelation('__commerce_pending_credentials');
            if ($pending !== []) {
                app(CommerceCredentialRuntime::class)->storeFromLegacy($chatbot, $pending);
            }
        });
    }
}
