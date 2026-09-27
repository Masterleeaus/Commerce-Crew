<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceSpendLimit;
use App\Extensions\ChatbotEcommerce\System\Support\BudgetLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BudgetLockRuntime
{
    /** @return array<string,mixed> */
    public function evaluate(int $chatbotId, ?int $customerIdentityId, string $sessionId, int $amount, string $currency): array
    {
        $limit = CommerceSpendLimit::query()
            ->where('chatbot_id', $chatbotId)
            ->where('enabled', true)
            ->where(function ($query) use ($customerIdentityId, $sessionId): void {
                if ($customerIdentityId !== null) {
                    $query->where('customer_identity_id', $customerIdentityId);
                } else {
                    $query->whereNull('customer_identity_id');
                }
                $query->where(function ($nested) use ($sessionId): void {
                    $nested->whereNull('session_id')->orWhere('session_id', $sessionId);
                });
            })
            ->orderByDesc('session_id')
            ->first();

        if (! $limit) {
            return ['allowed' => true, 'reason' => null, 'remaining_daily' => null];
        }

        $todaySpend = CommerceOrder::query()
            ->where('chatbot_id', $chatbotId)
            ->when($customerIdentityId !== null, fn ($query) => $query->where('customer_identity_id', $customerIdentityId))
            ->whereDate('placed_at', today())
            ->whereNotIn('status', ['cancelled', 'payment_failed'])
            ->sum(DB::raw('total - refunded_total'));

        return BudgetLock::evaluate($amount, $currency, [
            'max_order_value' => $limit->max_order_value,
            'daily_spend_limit' => $limit->daily_spend_limit,
            'spent_today' => max((int) $limit->spent_today, (int) $todaySpend),
            'currency' => $limit->currency,
        ]);
    }

    public function assertAllowed(int $chatbotId, ?int $customerIdentityId, string $sessionId, int $amount, string $currency): void
    {
        $result = $this->evaluate($chatbotId, $customerIdentityId, $sessionId, $amount, $currency);
        if (! $result['allowed']) {
            throw ValidationException::withMessages(['budget' => 'Purchase blocked by ' . str_replace('_', ' ', (string) $result['reason']) . '.']);
        }
    }
}
