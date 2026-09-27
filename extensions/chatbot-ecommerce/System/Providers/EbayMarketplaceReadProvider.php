<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Providers;
final class EbayMarketplaceReadProvider extends AbstractMarketplaceReadProvider
{
    public function key(): string { return 'ebay'; }
    protected function operations(): array { return ['search'=>'browse.search','get'=>'browse.get','inventory'=>'inventory.get','orders'=>'fulfillment.orders']; }
}
