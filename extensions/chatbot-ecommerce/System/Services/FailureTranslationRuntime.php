<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\CommerceErrorMapping;
use App\Extensions\ChatbotEcommerce\System\Support\ErrorLexicon;
use App\Extensions\ChatbotEcommerce\System\Support\ExtensionLogger;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class FailureTranslationRuntime
{
    /** @return array<string,mixed> */
    public function translate(string $provider, string|int|null $code, ?string $rawMessage = null, ?int $chatbotId = null): array
    {
        $entries = $this->defaults();
        $mapping = Schema::hasTable('ext_chatbot_commerce_error_lexicon')
            ? CommerceErrorMapping::query()
                ->where('active', true)
                ->where('provider', strtolower($provider))
                ->where('error_code', (string) $code)
                ->where(function ($query) use ($chatbotId): void {
                    $query->whereNull('chatbot_id');
                    if ($chatbotId !== null) {
                        $query->orWhere('chatbot_id', $chatbotId);
                    }
                })
                ->orderByDesc('chatbot_id')
                ->first()
            : null;
        if ($mapping) {
            $entries[strtolower($provider) . ':' . (string) $code] = [
                'message' => $mapping->message,
                'remediation' => $mapping->remediation,
                'retryable' => (bool) $mapping->retryable,
                'severity' => $mapping->severity,
            ];
        }

        $result = (new ErrorLexicon($entries))->translate($provider, $code, $rawMessage);
        ExtensionLogger::error('Commerce provider action failed', [
            'provider' => $provider,
            'code' => (string) $code,
            'raw_message_hash' => $rawMessage !== null ? hash('sha256', $rawMessage) : null,
            'translated_message' => $result['message'],
        ]);
        Metrics::increment('commerce.failure.translated', ['provider' => strtolower($provider), 'code' => (string) $code]);

        return $result;
    }

    /** @return array<string,mixed> */
    public function fromThrowable(Throwable $exception, string $provider = 'internal', ?int $chatbotId = null): array
    {
        return $this->translate($provider, (string) $exception->getCode(), $exception->getMessage(), $chatbotId);
    }

    /** @return array<string,array<string,mixed>> */
    private function defaults(): array
    {
        return [
            'ebay:8542' => ['message' => 'The barcode is not valid for this category.', 'remediation' => 'Check the UPC/GTIN or remove it when the category permits.', 'retryable' => false],
            'amazon:QUOTA_EXCEEDED' => ['message' => 'Amazon is temporarily limiting requests.', 'remediation' => 'Wait briefly and retry the action.', 'retryable' => true],
            'shopify:429' => ['message' => 'Shopify is temporarily limiting requests.', 'remediation' => 'Wait briefly and retry the action.', 'retryable' => true],
            'woocommerce:401' => ['message' => 'The WooCommerce connection is no longer authorised.', 'remediation' => 'Reconnect the store credentials.', 'retryable' => false],
            'internal:budget_locked' => ['message' => 'This purchase exceeds the configured spending limit.', 'remediation' => 'Reduce the order or ask an authorised user to change the limit.', 'retryable' => false],
            'internal:ambiguous_reference' => ['message' => 'I need you to identify the exact product or variant before I change the cart.', 'remediation' => 'Choose one of the numbered options.', 'retryable' => false],
        ];
    }
}
