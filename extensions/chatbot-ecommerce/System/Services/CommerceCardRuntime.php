<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceOrder;
use App\Extensions\ChatbotEcommerce\System\Support\CommerceUiFallback;
use Illuminate\Support\Collection;

final class CommerceCardRuntime
{
    /** @param iterable<mixed> $products @return array{schema:array<string,mixed>,fallback_text:string} */
    public function products(iterable $products): array
    {
        $items = collect($products)->map(function ($product): array {
            $data = is_array($product) ? $product : $product->toArray();
            $price = (int) ($data['price'] ?? 0);
            return [
                'id' => (int) ($data['id'] ?? 0),
                'title' => (string) ($data['name'] ?? 'Product'),
                'description' => (string) ($data['short_description'] ?? $data['description'] ?? ''),
                'price_minor' => $price,
                'currency' => (string) ($data['currency'] ?? 'USD'),
                'image' => (array) ($data['images'] ?? []),
                'url' => (string) (($data['metadata']['url'] ?? null) ?: ''),
                'actions' => [['type' => 'select_product', 'product_id' => (int) ($data['id'] ?? 0)]],
            ];
        })->values();

        $fallback = CommerceUiFallback::numberedChoices($items->map(fn (array $item): array => [
            'title' => $item['title'],
            'price' => $item['currency'] . ' ' . number_format($item['price_minor'] / 100, 2),
            'url' => $item['url'],
        ])->all());

        return ['schema' => ['type' => 'commerce.product_carousel', 'version' => 1, 'items' => $items->all()], 'fallback_text' => $fallback];
    }

    /** @return array{schema:array<string,mixed>,fallback_text:string} */
    public function cart(ChatbotCart $cart): array
    {
        $cart->loadMissing('cartLines');
        $lines = $cart->cartLines->map(fn ($line): array => [
            'uuid' => $line->uuid,
            'title' => trim($line->name . ($line->variant_name ? ' — ' . $line->variant_name : '')),
            'quantity' => (int) $line->quantity,
            'line_total' => (int) $line->line_total,
            'currency' => $line->currency,
        ])->values()->all();
        $fallback = collect($lines)->map(fn (array $line): string => sprintf('%dx %s — %s %.2f', $line['quantity'], $line['title'], $line['currency'], $line['line_total'] / 100))->implode("\n");
        $fallback .= sprintf("\nTotal: %s %.2f", $cart->currency, ((int) $cart->total) / 100);

        return ['schema' => ['type' => 'commerce.cart_summary', 'version' => 1, 'cart_uuid' => $cart->uuid, 'lines' => $lines, 'total' => (int) $cart->total, 'currency' => $cart->currency], 'fallback_text' => $fallback];
    }

    /** @return array{schema:array<string,mixed>,fallback_text:string} */
    public function approval(string $actionUuid, string $actionType, array $summary, ?string $approvalToken = null): array
    {
        $schema = ['type' => 'commerce.approval', 'version' => 1, 'action_uuid' => $actionUuid, 'action_type' => $actionType, 'summary' => $summary, 'actions' => ['approve', 'reject']];
        if ($approvalToken !== null) {
            $schema['private'] = ['approval_token' => $approvalToken];
        }
        return [
            'schema' => $schema,
            'fallback_text' => 'Approval required: ' . ($summary['message'] ?? str_replace('_', ' ', $actionType)) . '. Reply APPROVE to continue or REJECT to cancel.',
        ];
    }

    /** @return array{schema:array<string,mixed>,fallback_text:string} */
    public function order(CommerceOrder $order): array
    {
        $order->loadMissing('items');
        return [
            'schema' => ['type' => 'commerce.order_status', 'version' => 1, 'order_number' => $order->order_number, 'status' => $order->status, 'fulfillment_status' => $order->fulfillment_status, 'total' => (int) $order->total, 'currency' => $order->currency, 'items' => $order->items->toArray()],
            'fallback_text' => sprintf('Order %s is %s. Fulfilment: %s. Total: %s %.2f.', $order->order_number, $order->status, $order->fulfillment_status, $order->currency, $order->total / 100),
        ];
    }
}
