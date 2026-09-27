<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceReadProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\AmazonMarketplaceReadProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\EbayMarketplaceReadProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\EtsyMarketplaceReadProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\GenericMarketplaceReadProvider;
use InvalidArgumentException;

final class MarketplaceProviderRegistry
{
    /** @var array<string,class-string<MarketplaceReadProvider>> */
    private const PROVIDERS = [
        'amazon' => AmazonMarketplaceReadProvider::class,
        'ebay' => EbayMarketplaceReadProvider::class,
        'etsy' => EtsyMarketplaceReadProvider::class,
        'generic' => GenericMarketplaceReadProvider::class,
    ];

    public function get(string $key): MarketplaceReadProvider
    {
        $key = strtolower(trim($key));
        $class = self::PROVIDERS[$key] ?? null;
        if ($class === null) { throw new InvalidArgumentException("Unsupported marketplace provider: {$key}"); }
        return app($class);
    }


    /** @return array<int,string> */
    public function allCapabilities(string $key): array
    {
        return array_values(array_unique(array_merge(
            $this->get($key)->capabilities(),
            app(MarketplaceWriteProviderRegistry::class)->get($key)->capabilities()
        )));
    }

    /** @return array<string,array<int,string>> */
    public function capabilities(): array
    {
        $result = [];
        foreach (array_keys(self::PROVIDERS) as $key) { $result[$key] = $this->allCapabilities($key); }
        return $result;
    }
}
