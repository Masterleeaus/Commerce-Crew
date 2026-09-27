<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Providers;
final class GenericMarketplaceWriteProvider extends AbstractMarketplaceWriteProvider
{
    public function key(): string { return 'generic'; }
    protected function operations(): array { return ['update_listing'=>'listing.update','update_price'=>'listing.price.update','update_inventory'=>'listing.inventory.update','pause_listing'=>'listing.pause','resume_listing'=>'listing.resume']; }
}
