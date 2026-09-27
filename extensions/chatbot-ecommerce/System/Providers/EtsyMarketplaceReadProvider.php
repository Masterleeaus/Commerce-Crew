<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Providers;
final class EtsyMarketplaceReadProvider extends AbstractMarketplaceReadProvider
{
    public function key(): string { return 'etsy'; }
    protected function operations(): array { return ['search'=>'listings.search','get'=>'listings.get','inventory'=>'inventory.get','orders'=>'receipts.list']; }
}
