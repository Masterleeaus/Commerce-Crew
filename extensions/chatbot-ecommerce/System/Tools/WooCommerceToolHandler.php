<?php

namespace App\Extensions\ChatbotEcommerce\System\Tools;

use Automattic\WooCommerce\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Handles interactions with the WooCommerce REST API.
 * This class is intended to be used as a tool by a chatbot or other system
 * component to perform specific e-commerce operations, now including
 * fetching full product variant details, generating dynamic checkout URLs,
 * and retrieving shipping class information, and fetching product reviews.
 */
class WooCommerceToolHandler
{
    private Client $client;

    /**
     * @var string|null the cached currency code fetched from the API
     */
    private ?string $currencyCode = null;

    /**
     * Initializes the handler and sets up the WooCommerce Client.
     *
     * @param  string  $url  The shop URL (e.g., https://yourdomain.com)
     * @param  string  $ck  WooCommerce Consumer Key
     * @param  string  $cs  WooCommerce Consumer Secret
     * @param  array  $options  Optional client configuration options
     */
    public function __construct(
        // Use PHP 8+ constructor property promotion for clean initialization
        private string $url,
        private string $ck,
        private string $cs,
        private array $options = [
            'wp_api'  => true,
            'version' => 'wc/v3',
        ]
    ) {
        // Ensure the base URL does not have a trailing slash for signature consistency
        $sanitizedUrl = rtrim($this->url, '/');

        // Initialize the client with the sanitized URL
        $this->client = new Client($sanitizedUrl, $this->ck, $this->cs, $this->options);
    }

    /**
     * Makes a generic request to the WooCommerce API and handles errors.
     *
     * @param  string  $endpoint  The API endpoint (e.g., 'products', 'orders/123')
     * @param  string  $method  The HTTP method (e.g., 'GET', 'POST', 'PUT')
     * @param  array  $data  The data payload for POST/PUT requests (used as query for GET)
     *
     * @return array|null The response data array or null on failure
     */
    private function request(string $endpoint, string $method = 'GET', array $data = []): ?array
    {
        $method = strtoupper($method);

        try {
            $response = match ($method) {
                // Pass $data (query parameters) to the get() method
                'GET'    => $this->client->get($endpoint, $data),
                'POST'   => $this->client->post($endpoint, $data),
                'PUT'    => $this->client->put($endpoint, $data),
                'DELETE' => $this->client->delete($endpoint),
                default  => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            // FIX: The library often returns nested stdClass objects. We use json_encode/decode
            // for robust, deep conversion to an associative array structure, ensuring array access is safe.
            return json_decode(json_encode($response), true);
        } catch (Exception $e) {
            // Log the exception
            Log::error('WooCommerce API Error: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'method'   => $method,
                'code'     => $e->getCode(),
            ]);

            return null;
        }
    }

    /**
     * Lazily retrieves the store's base currency code from the WooCommerce API.
     * * This method fetches the currency code once and caches it.
     *
     * * @return string The currency code (e.g., 'USD', 'EUR'), defaults to 'USD' on failure.
     */
    private function getCurrencyCode(): string
    {
        if ($this->currencyCode !== null) {
            return $this->currencyCode;
        }

        // 1. Try to fetch currency from System Status endpoint
        $status = $this->request('system_status', 'GET');

        if (isset($status['settings']['currency'])) {
            $this->currencyCode = $status['settings']['currency'];

            return $this->currencyCode;
        }

        // 2. If system_status fails or doesn't have it, try general settings by ID
        // Endpoint: settings/{group}/{id}
        $currencySetting = $this->request('settings/general/woocommerce_currency', 'GET');

        if (isset($currencySetting['value'])) {
            $this->currencyCode = $currencySetting['value'];

            return $this->currencyCode;
        }

        // 3. Fallback
        Log::warning('Could not dynamically fetch WooCommerce currency. Defaulting to USD.');
        $this->currencyCode = 'USD';

        return $this->currencyCode;
    }

    /**
     * Retrieves the variations for a specific variable product.
     *
     * @param  int  $productId  the ID of the variable product
     *
     * @return array the list of variations
     */
    private function getVariationsForProduct(int $productId): array
    {
        $variations = $this->request("products/{$productId}/variations", 'GET', ['per_page' => '100']);

        return $variations ?? [];
    }

    /**
     * Retrieves a list of products based on query parameters,
     * and enriches the response by fetching all variants for variable products.
     *
     * Note: Non-standard WooCommerce query parameters like 'search_fields' are ignored
     * before being passed to the API to ensure signature validity.
     *
     * @param  array  $query  An array of standard WooCommerce query parameters
     *
     * @return array the list of enriched products, or an empty array on failure
     */
    public function getProducts(array $query = []): array
    {
        // Remove non-standard WooCommerce query parameters before making the request
        $apiQuery = $query;
        unset($apiQuery['search_fields']);

        // 1. Fetch initial products list
        $products = $this->request('products', 'GET', $apiQuery);

        if (empty($products)) {
            return [];
        }

        $enrichedProducts = [];

        // 2. Iterate and enrich with full variant data
        foreach ($products as $product) {
            // Ensure product is an array for safe access (though deep conversion is done in request, keeping for safety)
            $product = (array) $product;
            $productId = $product['id'];

            $variants = [];

            // Check if the product is 'variable' to fetch variations
            if (isset($product['type']) && $product['type'] === 'variable') {
                $rawVariations = $this->getVariationsForProduct($productId);

                // Map raw variations to the expected 'variants' structure
                foreach ($rawVariations as $variant) {
                    $variant = (array) $variant;

                    // Construct a readable title for the variant (e.g., 'Gray / S / Rubber')
                    $variantTitleParts = [];
                    // Ensure attributes is an array/object before mapping
                    $rawAttributes = (array) ($variant['attributes'] ?? []);

                    foreach ($rawAttributes as $attr) {
                        // All objects are now guaranteed to be arrays by the 'request' method.
                        // We use $attr directly as an array.
                        $variantTitleParts[] = $attr['option'] ?? '';
                    }

                    $variantTitle = implode(' / ', array_filter($variantTitleParts));

                    $variants[] = [
                        'id' => (string) $variant['id'],
                        // Use the price field, and append the dynamic currency code from the API
                        'price' => ($variant['price'] ?? $variant['regular_price']) . ' ' . $this->getCurrencyCode(),
                        'title' => empty($variantTitle) ? $product['name'] . ' Variant' : $variantTitle,
                        'stock' => $variant['stock_quantity'] ?? null,
                    ];
                }
            }

            // 3. Map WooCommerce Product to the user's expected structure
            $enrichedProducts[] = [
                'id' => (string) $productId,
                // Map 'name' to 'title'
                'title' => $product['name'] ?? '',
                // Remove HTML tags from the description
                'description' => strip_tags($product['description'] ?? ''),
                // Construct a placeholder URL assuming standard permalink structure /product/{slug}
                'url' => rtrim($this->url, '/') . '/product/' . ($product['slug'] ?? $productId),
                // Extract image source URLs
                'images' => array_map(fn ($img) => $img['src'], $product['images'] ?? []),
                // Insert the fetched and mapped variants
                'variants' => $variants,
                // Map WooCommerce attributes (options for the product) to user's 'options'
                'options' => array_map(function ($attr) {
                    $attr = (array) $attr;

                    return [
                        'name' => $attr['name'],
                        // WooCommerce uses 'options' key for possible attribute values
                        'values' => $attr['options'] ?? [],
                    ];
                }, $product['attributes'] ?? []),
            ];
        }

        return $enrichedProducts;
    }

    /**
     * Retrieves structured details for a single sellable item (Product or Variant).
     *
     * This function efficiently fetches product details using the Product ID or Variant ID.
     * If a Variant ID is passed and the API returns the variant details (which have a parent_id),
     * it fetches the parent product's title and uses the parent's URL/image.
     *
     * @param  int  $id  the ID of the product (simple, variable) or a specific variant
     *
     * @return array|null the structured product/variant details array, or null on failure/not found
     */
    public function getProductDetailsById(int $id): ?array
    {
        $item = $this->request("products/{$id}", 'GET');

        if (empty($item)) {
            return null;
        }

        $currency = $this->getCurrencyCode();

        // 1. Identify if the item is a variant or a main product
        $parentId = $item['parent_id'] ?? 0;
        $isVariant = $parentId > 0;

        $product = $item; // Default to the item itself for parent info
        $variantId = (string) $id;
        $productId = (string) $id;

        // 2. If it's a variant, fetch the parent product for the main title, URL, and Image
        if ($isVariant) {
            $parentProduct = $this->request("products/{$parentId}", 'GET');
            if ($parentProduct) {
                $product = $parentProduct;
                $productId = (string) $parentId;
            } else {
                // If parent fetch fails, use the variant's details as the fallback product details
                Log::warning("Parent product ID {$parentId} for variant {$id} not found. Using variant data as product fallback.");
            }
        }

        // 3. Determine the output structure values

        // Product Title: Always use the main product's name
        $productTitle = $product['name'] ?? 'Product';

        // Product URL: Based on the main product's slug/ID
        $productUrl = rtrim($this->url, '/') . '/product/' . ($product['slug'] ?? $productId);

        // Product Image: Primary image of the main product
        $primaryImage = $product['images'][0]['src'] ?? null;

        // Variant Title: Use the variant's name (often empty) or construct a title
        $variantTitle = $item['name'] ?? '';
        if ($isVariant) {
            // If WooCommerce doesn't provide a good variant name, construct a title from attributes
            $variantTitleParts = [];
            foreach (($item['attributes'] ?? []) as $attr) {
                $variantTitleParts[] = $attr['option'] ?? '';
            }
            $variantTitle = implode(' / ', array_filter($variantTitleParts)) ?: $productTitle . ' Variant';
        }

        // Variant Price: Use the specific price of the item (whether simple product or variant)
        $variantPrice = 'Price not available';
        if (isset($item['price']) && $item['price'] !== '') {
            $variantPrice = $item['price'];
        } elseif (isset($item['min_price']) && isset($item['max_price'])) {
            // This case handles variable parent products passed directly
            if ($item['min_price'] !== $item['max_price']) {
                $variantPrice = "{$item['min_price']} - {$item['max_price']} {$currency}";
            } else {
                $variantPrice = "{$item['min_price']}";
            }
            // Overwrite variant title for clarity if it's the base product
            $variantTitle = $productTitle . ' (Base Product)';
        }

        return [
            'productId'    => $productId,
            'productTitle' => $productTitle,
            'productUrl'   => $productUrl,
            'productImage' => $primaryImage,
            'variantId'    => $variantId,
            'variantTitle' => $variantTitle,
            'variantPrice' => $variantPrice,
            'currencyCode' => $currency,
        ];
    }

    /**
     * Finds the ID of a specific product variation based on its attributes.
     *
     * * @param int $productId The ID of the variable product
     * @param  array  $attributes  Associative array of attributes to match (e.g., ['Color' => 'Red', 'Size' => 'Large']).
     *
     * @return int|null the ID of the matching variant, or null if no match is found
     */
    public function getVariantIdByAttributes(int $productId, array $attributes): ?int
    {
        if (empty($attributes)) {
            return null;
        }

        $variations = $this->getVariationsForProduct($productId);

        if (empty($variations)) {
            return null;
        }

        // Iterate through all variations to find a match
        foreach ($variations as $variation) {
            $variation = (array) $variation;
            $rawAttributes = (array) ($variation['attributes'] ?? []);

            $isMatch = true;

            // 1. Check if the number of attributes matches the number of attributes defined in the variation
            if (count($attributes) !== count($rawAttributes)) {
                // If the counts don't match, this variation cannot be a full match.
                continue;
            }

            // 2. Check if all required input attributes match the variation's attributes
            foreach ($attributes as $inputName => $inputValue) {
                $foundAttribute = false;

                // Compare the input attribute against all attributes of the current variation
                foreach ($rawAttributes as $varAttr) {
                    // Use case-insensitive comparison for robustness
                    if (
                        (strtolower($varAttr['name']) === strtolower($inputName)) &&
                        (strtolower($varAttr['option']) === strtolower($inputValue))
                    ) {
                        $foundAttribute = true;

                        break; // Match found for this specific attribute pair
                    }
                }

                if (! $foundAttribute) {
                    $isMatch = false;

                    break; // This variation does not match all required attributes
                }
            }

            if ($isMatch) {
                // If all attributes matched, return the variant ID
                return (int) $variation['id'];
            }
        }

        return null;
    }

    /**
     * Parses the product data into a view (unchanged from original).
     */
    public function parseProducts(array $products): \Illuminate\Contracts\View\View
    {
        return view('chatbot-ecommerce::frontend-ui.components.product-carousel', [
            'products'    => $products,
            'shop_source' => 'woocommerce',
        ]);
    }

    /**
     * Generates a WooCommerce URL that adds specific products/variants to the cart
     * and redirects the user to the checkout page using a custom single-parameter format.
     *
     * Example: https://shop.com/?magicai-add-to-cart=111:2,222:3,333:1
     *
     * @param  array<int|string, int>  $items  associative array of [product_id => quantity]
     *
     * @return string the generated checkout URL
     */
    public function getCheckoutUrl(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $itemStrings = [];

        // Collect IDs and Quantities formatted as "ID:QTY"
        foreach ($items as $id => $quantity) {
            // Ensure ID is cast to string and quantity is a valid integer >= 1
            $id = (string) $id;
            $qty = max(1, (int) $quantity);

            // Format as ID:QTY
            $itemStrings[] = "{$id}:{$qty}";
        }

        // Construct the query string in the custom single-parameter format
        $itemsString = implode(',', $itemStrings);

        // Use the custom query parameter key 'magicai-add-to-cart'
        $queryString = "?magicai-add-to-cart={$itemsString}";

        // Construct the full URL
        $checkoutUrl = rtrim($this->url, '/') . $queryString;

        return $checkoutUrl;
    }

    /**
     * Retrieves available payment gateways and returns a list or a confirmation message
     * based on the specified method.
     *
     * @param  string  $method  The payment gateway ID (e.g., 'paypal', 'bacs') or 'all' to list all enabled gateways.
     *
     * @return array|string a list of enabled gateways (array) or a confirmation/denial string
     */
    public function getPaymentGateways(string $method): array|string
    {
        // Fetch all payment gateways configured in WooCommerce
        $allGateways = $this->request('payment_gateways', 'GET');

        if (empty($allGateways)) {
            return 'Could not retrieve payment gateway information from the store.';
        }

        // Normalize the input method for comparison
        $normalizedMethod = strtolower(trim($method));

        // 1. Case: List all enabled gateways (now returns a string)
        if ($normalizedMethod === 'all') {
            $titles = [];
            foreach ($allGateways as $gateway) {
                // The API response ensures $gateway is an array here
                if (($gateway['enabled'] ?? false) === true) {
                    if ($gateway['title'] != 'Link') {
                        $titles[] = $gateway['title'];
                    }
                }
            }
            if (empty($titles)) {
                return 'The store currently has no payment gateways enabled.';
            }

            // Format the list of titles into a readable string
            $count = count($titles);
            if ($count === 1) {
                return "The only available payment gateway is: **{$titles[0]}**.";
            } elseif ($count === 2) {
                return "The two available payment gateways are: **{$titles[0]}** and **{$titles[1]}**.";
            } else {
                // List all but the last one separated by comma, and the last one with 'and'
                $lastTitle = array_pop($titles);
                $list = implode(', ', $titles);

                return "The available payment gateways are: **{$list}**, and **{$lastTitle}**.";
            }
        }

        // 2. Case: Check for a specific gateway
        $foundGateway = null;
        foreach ($allGateways as $gateway) {
            if (strtolower($gateway['id'] ?? '') === $normalizedMethod) {
                $foundGateway = $gateway;

                break;
            }
        }

        if ($foundGateway === null) {
            return "The payment method '{$method}' is not recognized or configured in this store.";
        }

        if (($foundGateway['enabled'] ?? false) === true) {
            return "Yes, you can pay with **{$foundGateway['title']}**. The gateway is currently enabled.";
        } else {
            return "No, the payment method '{$foundGateway['title']}' is configured but currently disabled on this store.";
        }
    }

    /**
     * Retrieves a detailed list of all active shipping methods grouped by zone.
     *
     * @return string a detailed list of all active shipping methods
     */
    public function getShippingMethods(): string
    {
        // Fetch all shipping zones
        $zones = $this->request('shipping/zones', 'GET');

        if (empty($zones)) {
            return 'Could not retrieve shipping zone information from the store.';
        }

        $zoneMethods = [];
        $currency = $this->getCurrencyCode();

        // 1. Iterate through zones and collect methods
        foreach ($zones as $zone) {
            $zone = (array) $zone;
            $zoneName = $zone['name'] ?? 'Unknown Zone';
            $zoneId = $zone['id'];

            // Fetch methods for the specific zone
            $methods = $this->request("shipping/zones/{$zoneId}/methods", 'GET');

            $availableMethods = [];

            if (! empty($methods)) {
                foreach ($methods as $method) {
                    $method = (array) $method;

                    if (($method['enabled'] ?? false) === true) {
                        $methodTitle = $method['title'] ?? 'Unnamed Method';

                        // Collect method details for the full list
                        $availableMethods[] = "{$methodTitle}";
                    }
                }
            }

            if (! empty($availableMethods)) {
                $zoneMethods[$zoneName] = $availableMethods;
            }
        }

        // 2. List all available methods grouped by zone
        if (empty($zoneMethods)) {
            return 'No shipping methods are currently available in any zone.';
        }

        $output = "Currently available shipping methods, grouped by zone:\n\n";

        foreach ($zoneMethods as $zoneName => $methods) {
            $output .= "**{$zoneName}**:\n";
            foreach ($methods as $methodDetail) {
                $output .= "- {$methodDetail}\n";
            }
            $output .= "\n";
        }

        return trim($output);
    }

    /**
     * Retrieves a list of all active and usable coupons currently configured in the store.
     *
     * @return string a string listing active coupons with key details (code, amount, expiry), or a message if none are found
     */
    public function getCoupons(): string
    {
        // Fetch all coupons, including status and expiry dates
        $coupons = $this->request('coupons', 'GET');

        if (empty($coupons)) {
            return 'No coupons are currently configured in the store.';
        }

        // We will store the ready-to-print single-line strings here
        $activeCouponsStrings = [];
        // Using time() is generally safer than relying on server time in the API response
        $now = time();
        $currency = $this->getCurrencyCode();

        foreach ($coupons as $coupon) {
            $coupon = (array) $coupon;

            // Check Expiry Date
            $isExpired = false;
            $expiryDate = $coupon['date_expires'] ?? null;
            $expiryString = '';

            if (! empty($expiryDate)) {
                try {
                    // Convert date string to timestamp for comparison
                    $expiryTimestamp = strtotime($expiryDate);

                    // Consider expired if the expiry time is in the past
                    if ($expiryTimestamp && $expiryTimestamp < $now) {
                        $isExpired = true;
                    }
                    $expiryString = date('Y-m-d', $expiryTimestamp);
                    $expiryString = ", expires {$expiryString}";
                } catch (Exception $e) {
                    // If date parsing fails, treat as non-expiring for robustness
                    $expiryString = 'Date parsing error (Check API)';
                }
            }

            // Check Usage Limit
            $usageLimit = $coupon['usage_limit'] ?? 0;
            $usageCount = $coupon['usage_count'] ?? 0;
            $isUsedUp = $usageLimit > 0 && $usageCount >= $usageLimit;

            // Check Status (e.g., 'publish')
            $isPublished = ($coupon['status'] ?? 'publish') === 'publish';

            // A coupon is considered "active and usable" if it's published, not expired, and not used up.
            if ($isPublished && ! $isExpired && ! $isUsedUp) {

                // 1. Build a human-readable description of the discount
                $amount = $coupon['amount'] ?? '0';
                $discountDescription = 'Unknown Discount';
                // Format amount to two decimal places for currency display
                $formattedAmount = number_format((float) $amount, 2, '.', '');

                switch ($coupon['discount_type']) {
                    case 'percent':
                        $discountDescription = "{$amount}% off";

                        break;
                    case 'fixed_cart':
                        $discountDescription = "{$currency}{$formattedAmount} off cart";

                        break;
                    case 'fixed_product':
                        $discountDescription = "{$currency}{$formattedAmount} off product";

                        break;
                    default:
                        // Use the description if it exists, otherwise the raw amount/type
                        $desc = strip_tags($coupon['description'] ?? '');
                        if (! empty($desc)) {
                            $discountDescription = $desc;
                        } else {
                            $discountDescription = "{$amount} ({$coupon['discount_type']})";
                        }
                }

                // 2. Construct the single-line string: `code` discount_details, expires_date
                $activeCouponsStrings[] = "* **{$coupon['code']}**, {$discountDescription}{$expiryString}";
            }
        }

        if (empty($activeCouponsStrings)) {
            return 'No currently active and usable coupons were found in the store.';
        }

        $output = 'We have **' . count($activeCouponsStrings) . "** active coupon(s):\n\n";
        // Join with a newline for clean, single-line output
        $output .= implode("\n", $activeCouponsStrings);

        return trim($output);
    }

    /**
     * Retrieves reviews for a specific product ID.
     *
     * @param  int  $productId  the ID of the product to fetch reviews for
     *
     * @return string a summary of the reviews (count, average rating, and recent reviews), or a message if none are found
     */
    public function getProductReviews(int $productId): string
    {
        // 1. Fetch Product Details (Title, Image, URL) for context
        $productDetails = $this->getProductDetailsById($productId);

        if (empty($productDetails)) {
            // Fallback if product details cannot be fetched
            return "Could not find product details for ID **{$productId}**. Cannot retrieve reviews.";
        }

        $productTitle = $productDetails['productTitle'] ?? 'Unnamed Product';
        $productImage = $productDetails['productImage'] ?? null;

        // 2. Fetch Reviews (fetching up to 100 for a more accurate average calculation)
        $query = [
            'product'  => $productId,
            'per_page' => 100, // Fetch up to 100 reviews for calculation
            'status'   => 'approved', // Only fetch approved reviews
            'order'    => 'desc', // Get the newest reviews first
            'orderby'  => 'date',
        ];

        $allReviews = $this->request('products/reviews', 'GET', $query);

        // 3. Prepare the introductory output (Title and Image)
        $output = "Reviews for **{$productTitle}**\n";

        // Render the image as Markdown and skip the Product ID
        if ($productImage) {
            // Using Markdown image syntax: ![Alt Text](URL)
            $output .= "![{$productTitle} image]({$productImage})\n\n";
        } else {
            $output .= "*(No image available)*\n\n";
        }

        $output .= "---\n\n";

        if (empty($allReviews)) {
            $output .= 'No approved reviews were found for this product.';

            return trim($output);
        }

        // 4. Calculate Statistics
        $totalReviews = count($allReviews);
        $totalRatingSum = 0;

        foreach ($allReviews as $review) {
            $totalRatingSum += (int) ($review['rating'] ?? 0);
        }

        $averageRating = $totalReviews > 0 ? number_format($totalRatingSum / $totalReviews, 1) : 'N/A';

        // 5. Add statistics to output
        $output .= "Found **{$totalReviews}** approved review(s).\n";
        $output .= "The **average rating** is **{$averageRating}** out of 5.\n\n";

        // 6. List top 3 most recent reviews
        $output .= "**Recent Reviews**:\n";
        $count = 0;

        foreach ($allReviews as $review) {
            if ($count >= 3) {
                break;
            }
            $reviewContent = strip_tags($review['review'] ?? 'No content provided');
            $reviewerName = $review['reviewer'] ?? 'Anonymous';
            $rating = $review['rating'] ?? 0;

            $output .= "Rating: **{$rating}/5** by **{$reviewerName}**\n";
            $output .= "> {$reviewContent}\n\n";

            $count++;
        }

        // Remove trailing newlines and extra spaces
        return trim($output);
    }
}
