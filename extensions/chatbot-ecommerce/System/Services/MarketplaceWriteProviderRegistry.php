<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceWriteProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\AmazonMarketplaceWriteProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\EbayMarketplaceWriteProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\EtsyMarketplaceWriteProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\GenericMarketplaceWriteProvider;
use InvalidArgumentException;

final class MarketplaceWriteProviderRegistry
{
    /** @var array<string,class-string<MarketplaceWriteProvider>> */
    private const PROVIDERS = [
        'amazon' => AmazonMarketplaceWriteProvider::class,
        'ebay' => EbayMarketplaceWriteProvider::class,
        'etsy' => EtsyMarketplaceWriteProvider::class,
        'generic' => GenericMarketplaceWriteProvider::class,
    ];

    public function get(string $key): MarketplaceWriteProvider
    {
        $key = strtolower(trim($key));
        $class = self::PROVIDERS[$key] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException("Unsupported marketplace write provider: {$key}");
        }

        return app($class);
    }
}
