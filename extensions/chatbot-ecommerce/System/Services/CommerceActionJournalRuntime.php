<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Events\ExtensionEvent;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceActionJournal;
use App\Extensions\ChatbotEcommerce\System\Models\Product;
use App\Extensions\ChatbotEcommerce\System\Support\Metrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommerceActionJournalRuntime
{
    private const PRODUCT_FIELDS = ['name', 'description', 'short_description', 'price', 'compare_at_price', 'active', 'published_at', 'metadata'];

    public function executeProductUpdate(Product $product, int $chatbotId, array $changes, ?string $idempotencyKey, array $approval = []): CommerceActionJournal
    {
        $changes = array_intersect_key($changes, array_flip(self::PRODUCT_FIELDS));
        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => 'No supported product changes were supplied.']);
        }

        if ($idempotencyKey !== null && ($existing = CommerceActionJournal::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        return DB::transaction(function () use ($product, $chatbotId, $changes, $idempotencyKey, $approval): CommerceActionJournal {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(array_keys($changes));
            $locked->forceFill($changes)->save();
            $after = $locked->fresh()->only(array_keys($changes));

            $journal = CommerceActionJournal::query()->create([
                'chatbot_id' => $chatbotId,
                'action_type' => 'native.product.update',
                'subject_type' => Product::class,
                'subject_id' => (string) $locked->id,
                'status' => 'executed',
                'idempotency_key' => $idempotencyKey,
                'before_state' => $before,
                'after_state' => $after,
                'rollback_state' => $before,
                'approval_snapshot' => $approval,
                'executed_at' => now(),
            ]);

            event(new ExtensionEvent('commerce.action.executed', ['action_uuid' => $journal->uuid, 'action_type' => $journal->action_type]));
            Metrics::increment('commerce.action.executed', ['action_type' => $journal->action_type]);

            return $journal;
        });
    }

    public function rollback(CommerceActionJournal $journal): CommerceActionJournal
    {
        return DB::transaction(function () use ($journal): CommerceActionJournal {
            $locked = CommerceActionJournal::query()->whereKey($journal->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status === 'rolled_back') {
                return $locked;
            }
            if ((string) $locked->status !== 'executed' || (string) $locked->action_type !== 'native.product.update') {
                throw ValidationException::withMessages(['action' => 'This action cannot be rolled back automatically.']);
            }

            $product = Product::query()->whereKey((int) $locked->subject_id)->lockForUpdate()->firstOrFail();
            $current = $product->only(array_keys((array) $locked->rollback_state));
            if ($locked->after_state && $current !== (array) $locked->after_state) {
                throw ValidationException::withMessages(['action' => 'The product changed after this action. Review the differences before rollback.']);
            }
            $product->forceFill((array) $locked->rollback_state)->save();
            $locked->forceFill(['status' => 'rolled_back', 'rolled_back_at' => now(), 'metadata' => array_replace_recursive((array) $locked->metadata, ['rollback_result' => $product->fresh()->only(array_keys((array) $locked->rollback_state))])])->save();

            event(new ExtensionEvent('commerce.action.rolled_back', ['action_uuid' => $locked->uuid]));
            Metrics::increment('commerce.action.rolled_back', ['action_type' => $locked->action_type]);

            return $locked;
        });
    }
}
