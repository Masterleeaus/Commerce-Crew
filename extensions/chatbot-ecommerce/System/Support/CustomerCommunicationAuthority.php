<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class CustomerCommunicationAuthority
{
    /** @param array<string,mixed> $context @return array{level:string,allowed:bool,escalate:bool,reason:string} */
    public static function decision(string $action, array $context = []): array
    {
        $action = strtolower(trim($action));
        $confidence = max(0.0, min(1.0, (float) ($context['confidence'] ?? 0.0)));
        $identityVerified = (bool) ($context['identity_verified'] ?? false);

        if (in_array($action, ['legal_threat', 'fraud_suspected', 'chargeback_dispute', 'safety_incident', 'regulatory_complaint'], true)) {
            return self::result('human_only', false, true, 'This action requires a trained human decision-maker.');
        }

        if (in_array($action, ['answer_product_question', 'explain_policy', 'check_stock', 'explain_bnpl'], true)) {
            return $confidence >= 0.85
                ? self::result('answer_automatically', true, false, 'High-confidence factual answer.')
                : self::result('prepare_automatically', false, false, 'The answer should be reviewed because confidence is below the automatic threshold.');
        }

        if (in_array($action, ['get_order_status', 'get_tracking', 'get_refund_status', 'get_return_status', 'get_payment_status'], true)) {
            return $identityVerified && $confidence >= 0.80
                ? self::result('answer_automatically', true, false, 'Verified customer and factual commerce record.')
                : self::result('require_approval', false, ! $identityVerified, 'Customer identity or factual confidence is insufficient.');
        }

        if (in_array($action, ['prepare_cart_recovery', 'prepare_return', 'prepare_exchange', 'prepare_replacement', 'prepare_address_correction'], true)) {
            return self::result('prepare_automatically', true, false, 'The action may be prepared but not committed without the required approval.');
        }

        if (in_array($action, ['issue_refund', 'offer_discount', 'issue_store_credit', 'replace_item', 'cancel_order'], true)) {
            $amount = max(0, (int) ($context['amount'] ?? 0));
            $limitKey = match ($action) {
                'issue_refund' => 'automatic_refund_limit',
                'offer_discount' => 'automatic_discount_limit',
                'issue_store_credit' => 'automatic_store_credit_limit',
                'replace_item' => 'automatic_replacement_limit',
                default => 'automatic_cancellation_limit',
            };
            $limit = max(0, (int) ($context[$limitKey] ?? 0));

            if ($identityVerified && $limit > 0 && $amount > 0 && $amount <= $limit) {
                return self::result('execute_within_limits', true, false, 'The action is inside an explicitly configured automatic limit.');
            }

            return self::result('require_approval', false, false, 'The action creates a financial or fulfilment obligation and requires approval.');
        }

        if ($action === 'correct_address') {
            return $identityVerified
                ? self::result('execute_within_limits', true, false, 'Verified minor address correction.')
                : self::result('require_approval', false, true, 'Identity verification is required before changing an address.');
        }

        if ($action === 'handoff_to_human') {
            return self::result('human_only', true, true, 'The conversation was explicitly escalated.');
        }

        return self::result('require_approval', false, false, 'Unknown actions fail closed.');
    }

    /** @return array{level:string,allowed:bool,escalate:bool,reason:string} */
    private static function result(string $level, bool $allowed, bool $escalate, string $reason): array
    {
        return compact('level', 'allowed', 'escalate', 'reason');
    }
}
