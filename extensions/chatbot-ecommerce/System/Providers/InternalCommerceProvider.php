<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Providers;

use App\Extensions\ChatbotEcommerce\System\Contracts\CommerceProvider;
use App\Extensions\ChatbotEcommerce\System\Models\Product;
use App\Extensions\ChatbotEcommerce\System\Services\PricingRuntime;

final class InternalCommerceProvider implements CommerceProvider
{
    public function __construct(private readonly PricingRuntime $pricing) {}

    public function key(): string { return 'internal'; }

    public function searchProducts(array $filters = []): iterable
    {
        $chatbotId = (int) ($filters['chatbot_id'] ?? 0);
        return Product::query()
            ->with(['variants.inventory', 'category'])
            ->where('active', true)
            ->where('chatbot_id', $chatbotId > 0 ? $chatbotId : -1)
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where(function ($nested) use ($value) {
                $nested->where('name', 'like', "%{$value}%")
                    ->orWhere('description', 'like', "%{$value}%")
                    ->orWhere('search_keywords', 'like', "%{$value}%");
            }))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('category_id', $value))
            ->orderByDesc('recommendation_score')
            ->paginate(min(max((int) ($filters['per_page'] ?? 24), 1), 100));
    }

    public function findProduct(string|int $id): mixed
    {
        return Product::query()->with(['variants.inventory', 'category'])->where('active', true)->findOrFail($id);
    }

    public function findProductForChatbot(int $chatbotId, string|int $id): Product
    {
        return Product::query()->with(['variants.inventory', 'category'])
            ->where('active', true)
            ->where('chatbot_id', $chatbotId)
            ->findOrFail($id);
    }

    public function quote(array $lines, ?string $couponCode = null): array
    {
        return $this->pricing->quote($lines, $couponCode);
    }
}
