<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Providers;
final class GenericMarketplaceReadProvider extends AbstractMarketplaceReadProvider
{
    public function key(): string { return 'generic'; }
    protected function operations(): array { return ['search'=>'listings.search','get'=>'listings.get','inventory'=>'inventory.get','orders'=>'orders.list']; }
}
