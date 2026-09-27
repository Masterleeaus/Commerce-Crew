<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Providers;
final class AmazonMarketplaceReadProvider extends AbstractMarketplaceReadProvider
{
    public function key(): string { return 'amazon'; }
    protected function operations(): array { return ['search'=>'catalog.search','get'=>'catalog.get','inventory'=>'inventory.get','orders'=>'orders.list']; }
}
