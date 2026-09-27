<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Providers;
final class EbayMarketplaceWriteProvider extends AbstractMarketplaceWriteProvider
{
    public function key(): string { return 'ebay'; }
    protected function operations(): array { return ['update_listing'=>'offer.update','update_price'=>'offer.update_price','update_inventory'=>'inventory.update','pause_listing'=>'offer.withdraw','resume_listing'=>'offer.publish']; }
}
