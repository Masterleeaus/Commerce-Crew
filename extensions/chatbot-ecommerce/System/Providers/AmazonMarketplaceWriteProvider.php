<?php

declare(strict_types=1);
namespace App\Extensions\ChatbotEcommerce\System\Providers;
final class AmazonMarketplaceWriteProvider extends AbstractMarketplaceWriteProvider
{
    public function key(): string { return 'amazon'; }
    protected function operations(): array { return ['update_listing'=>'listings.patch','update_price'=>'pricing.update','update_inventory'=>'inventory.update','pause_listing'=>'listings.pause','resume_listing'=>'listings.resume']; }
}
