<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Providers;
final class EtsyMarketplaceWriteProvider extends AbstractMarketplaceWriteProvider
{
    public function key(): string { return 'etsy'; }
    protected function operations(): array { return ['update_listing'=>'listing.update','update_price'=>'inventory.price.update','update_inventory'=>'inventory.quantity.update','pause_listing'=>'listing.deactivate','resume_listing'=>'listing.activate']; }
}
