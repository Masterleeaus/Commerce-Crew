<?php

namespace App\Extensions\ChatbotEcommerce\System\Tools;

use Illuminate\Support\Facades\Log;

// This class acts as a helper for an AI chatbot, handling the execution of
// Shopify-related "tool" functions requested by the OpenAI API.
//
// It is designed to be called from a separate class that manages the
// overall conversation flow and makes the direct OpenAI API requests.
//
// Prerequisites:
// 1. You will need to make cURL requests to the Shopify GraphQL API.
// 2. Obtain your Shopify Storefront API access token from your Shopify Admin dashboard.
// 3. Update the configuration constants below.

class ShopifyToolHandler
{
    private string $shopifyApiEndpoint;

    private string $shopifyStorefrontAccessToken;

    private string $shopifyDomain;

    public function __construct(
        string $shopifyDomain,
        string $shopifyStorefrontAccessToken,
        string $shopifyApiVersion = '2023-07'
    ) {
        // Initialize Shopify client with configuration.
        $this->shopifyApiEndpoint = "https://{$shopifyDomain}/api/{$shopifyApiVersion}/graphql.json";
        $this->shopifyStorefrontAccessToken = $shopifyStorefrontAccessToken;
        $this->shopifyDomain = $shopifyDomain;
    }

    /**
     * Executes a specific tool function based on a tool call from the OpenAI API.
     *
     * @param  string  $functionName  The name of the function to execute (e.g., 'getProducts').
     * @param  array  $functionArgs  the arguments for the function
     *
     * @return array the result of the function call, to be sent back to OpenAI
     */
    public function handleToolCall(string $functionName, array $functionArgs): array
    {
        if (method_exists($this, $functionName)) {
            // Use the spread operator to pass the arguments dynamically.
            return $this->{$functionName}(...$functionArgs);
        } else {
            return ['error' => 'The requested tool is not available.'];
        }
    }

    /**
     * Finds products in the Shopify store based on a query, returning comprehensive data.
     *
     * @param  string  $query  the search query for products
     * @param  string  $orderby  The field to sort by (e.g., 'price', 'popularity', 'date').
     * @param  string  $order  the sort direction ('asc' or 'desc')
     *
     * @return array the list of products found with detailed information
     */
    private function getProducts(string $query, string $orderby = 'best_selling', string $order = 'desc'): array
    {
        $sortKey = $this->mapOrderByToShopifySortKey($orderby);
        // 'reverse' maps to DESC. If order is 'desc', reverse is TRUE.
        $reverse = strtolower($order) === 'desc';

        // Updated GraphQL query to include sortKey and reverse variables in the products connection.
        $graphQLQuery = <<< 'GRAPHQL'
            query products($query: String!, $sortKey: ProductSortKeys!, $reverse: Boolean!) {
                products(first: 5, query: $query, sortKey: $sortKey, reverse: $reverse) {
                    edges {
                        node {
                            id
                            title
                            handle
                            description(truncateAt: 80)
                            images(first: 5) {
                                edges {
                                    node {
                                        originalSrc
                                    }
                                }
                            }
                            variants(first: 5) {
                                edges {
                                    node {
                                        id
                                        title
                                        price {
                                            amount
                                            currencyCode
                                        }
                                        quantityAvailable
                                    }
                                }
                            }
                            options {
                                name
                                values
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        $variables = [
            'query'   => $query,
            'sortKey' => $sortKey,
            'reverse' => $reverse,
        ];

        $response = $this->sendShopifyGraphQLRequest($graphQLQuery, $variables);

        $products = [];
        if (isset($response['data']['products']['edges'])) {
            foreach ($response['data']['products']['edges'] as $edge) {
                $node = $edge['node'];

                // Process variants
                $variants = [];
                if (isset($node['variants']['edges'])) {
                    foreach ($node['variants']['edges'] as $variantEdge) {
                        $variantNode = $variantEdge['node'];
                        $variants[] = [
                            // Note: Returning the raw numeric ID for simplicity, matching existing parser
                            'id'    => $this->parseProductID($variantNode['id']),
                            'title' => $variantNode['title'],
                            'price' => $variantNode['price']['amount'] . ' ' . $variantNode['price']['currencyCode'],
                            'stock' => $variantNode['quantityAvailable'],
                        ];
                    }
                }

                // Process options
                $options = [];
                if (isset($node['options'])) {
                    foreach ($node['options'] as $option) {
                        $options[] = [
                            'name'   => $option['name'],
                            'values' => $option['values'],
                        ];
                    }
                }

                // Process images
                $images = [];
                if (isset($node['images']['edges'])) {
                    foreach ($node['images']['edges'] as $imageEdge) {
                        $images[] = $imageEdge['node']['originalSrc'];
                    }
                }

                $products[] = [
                    // Note: Returning the raw numeric ID for simplicity, matching existing parser
                    'id'          => $this->parseProductID($node['id']),
                    'title'       => $node['title'],
                    'description' => $node['description'],
                    'url'         => "https://{$this->shopifyDomain}/products/{$node['handle']}",
                    'images'      => $images,
                    'variants'    => $variants,
                    'options'     => $options,
                ];
            }
        }

        return ['products' => $products];
    }

    public function createCheckoutByVariantIds(array $variantIds, array $quantities): array
    {
        if (count($variantIds) !== count($quantities)) {
            return ['error' => 'Variant IDs and quantities arrays must have the same number of elements.'];
        }

        // NEW LOGIC: Construct a Cart Permalink URL instead of using GraphQL mutation to ensure reliability.
        $lineItemsUrlSegments = [];
        foreach ($variantIds as $index => $rawVariantId) {
            $quantity = (int) $quantities[$index];
            // Format: {variant_id}:{quantity}
            $lineItemsUrlSegments[] = "{$rawVariantId}:{$quantity}";
        }

        $lineItemsString = implode(',', $lineItemsUrlSegments);
        // Final URL format: https://{shop_domain}/cart/{variant_id}:{quantity},{variant_id}:{quantity},...
        $checkoutUrl = "https://{$this->shopifyDomain}/cart/{$lineItemsString}";

        return ['checkoutUrl' => $checkoutUrl];
    }

    /**
     * Finds the specific variant ID for a product based on a list of selected options.
     *
     * @param  string  $productIdRaw  the raw numeric ID of the product
     * @param  array  $selectedOptions  An ordered array of option values (e.g., ['Medium', 'Black']).
     *
     * @return array the variant ID or an error message
     */
    public function getVariantIdByOptions(string $productIdRaw, array $selectedOptions): array
    {
        // 1. Convert the raw product ID to the Global ID format for the GraphQL query.
        $globalProductId = $this->createProductGlobalID($productIdRaw);

        // 2. GraphQL query to fetch all variants and their selected options for the product.
        $graphQLQuery = <<< 'GRAPHQL'
            query getProductVariants($id: ID!) {
                product(id: $id) {
                    variants(first: 50) {
                        edges {
                            node {
                                id
                                selectedOptions {
                                    value
                                }
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        $variables = ['id' => $globalProductId];
        $response = $this->sendShopifyGraphQLRequest($graphQLQuery, $variables);

        $variants = $response['data']['product']['variants']['edges'] ?? [];

        if (empty($variants)) {
            return ['error' => 'No variants found for product ID: ' . $productIdRaw];
        }

        $selectedOptionValues = array_map('strtolower', $selectedOptions);

        // 3. Iterate through variants to find a match
        foreach ($variants as $edge) {
            $variantNode = $edge['node'];
            $variantOptions = [];

            // Extract the option values for the current variant
            foreach ($variantNode['selectedOptions'] as $option) {
                $variantOptions[] = strtolower($option['value']);
            }

            // Check if the variant's options exactly match the user's selected options (order matters)
            if ($variantOptions === $selectedOptionValues) {
                // Return the full Global ID of the matching variant
                return ['variantId' => $this->parseProductID($variantNode['id'])];
            }
        }

        // 4. No matching variant found
        return ['error' => 'No variant found matching options: ' . implode(', ', $selectedOptions) . ' for product ID: ' . $productIdRaw];
    }

    public function getProductDataByVariantId(string $variantIdRaw): array
    {
        // 1. Convert the raw variant ID to the Global ID format for the GraphQL query.
        $globalVariantId = $this->createVariantGlobalID($variantIdRaw);

        // 2. GraphQL query to fetch the variant and its parent product details
        $graphQLQuery = <<< 'GRAPHQL'
            query getVariantAndProduct($id: ID!) {
                node(id: $id) {
                    ... on ProductVariant {
                        id
                        title
                        price {
                            amount
                            currencyCode
                        }
                        product {
                            id
                            title
                            handle
                            images(first: 1) {
                                edges {
                                    node {
                                        originalSrc
                                    }
                                }
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        $variables = ['id' => $globalVariantId];
        $response = $this->sendShopifyGraphQLRequest($graphQLQuery, $variables);

        $variantNode = $response['data']['node'] ?? null;

        if (is_null($variantNode)) {
            return ['error' => 'Variant not found for ID: ' . $variantIdRaw];
        }

        $productNode = $variantNode['product'] ?? null;

        // 3. Extract and structure the data
        $productImage = $productNode['images']['edges'][0]['node']['originalSrc'] ?? null;

        // Extract Price
        $variantPrice = $variantNode['price']['amount'];

        return [
            'productId'    => $this->parseProductID($productNode['id']),
            'productTitle' => $productNode['title'],
            'productUrl'   => "https://{$this->shopifyDomain}/products/{$productNode['handle']}",
            'productImage' => $productImage,
            'variantId'    => $this->parseProductID($variantNode['id']),
            'variantTitle' => $variantNode['title'],
            'variantPrice' => $variantPrice,
            'currencyCode' => $variantNode['price']['currencyCode'],
        ];
    }

    /**
     * Retrieves available payment gateways from the Shopify store.
     *
     * @param  string  $paymentMethod  the specific payment method to check for, or 'all' to list all
     *
     * @return array the list of gateways or a confirmation string
     */
    public function getPaymentGateways(string $paymentMethod): array
    {
        $graphQLQuery = <<< 'GRAPHQL'
            query {
                shop {
                    paymentSettings {
                        acceptedCardBrands
                        supportedDigitalWallets
                        currencyCode
                    }
                }
            }
        GRAPHQL;

        $response = $this->sendShopifyGraphQLRequest($graphQLQuery, []);

        if (isset($response['errors'])) {
            Log::error('Shopify API Error in getPaymentGateways: ' . json_encode($response['errors']));

            return ['error' => 'Failed to retrieve payment gateways due to an API error.'];
        }

        $settings = $response['data']['shop']['paymentSettings'] ?? null;

        if (is_null($settings)) {
            return ['error' => 'Could not retrieve payment settings.'];
        }

        $cardBrands = $settings['acceptedCardBrands'] ?? [];
        $digitalWallets = $settings['supportedDigitalWallets'] ?? [];

        // Combine and format the payment method names
        $allMethods = array_merge($cardBrands, $digitalWallets);
        $formattedMethods = array_map(function ($method) {
            // Converts VISA to Visa, SHOPIFY_PAY to Shopify Pay
            return ucwords(strtolower(str_replace('_', ' ', $method)));
        }, $allMethods);

        if (strtolower($paymentMethod) === 'all') {
            if (empty($formattedMethods)) {
                return ['message' => 'There are currently no active payment methods specified.'];
            }
            $gatewayList = implode(', ', $formattedMethods);

            return ['message' => "We accept the following payment methods: {$gatewayList}."];
        }

        // Case-insensitive search for the specific payment method
        foreach ($formattedMethods as $name) {
            if (stripos($name, $paymentMethod) !== false) {
                return ['message' => "Yes, you can pay with {$name}."];
            }
        }

        return ['message' => "Sorry, {$paymentMethod} is not an available payment method."];
    }

    /**
     * Sends a GraphQL request to the Shopify Storefront API.
     */
    private function sendShopifyGraphQLRequest(string $query, array $variables): array
    {
        $payload = json_encode(['query' => $query, 'variables' => $variables]);

        $ch = curl_init($this->shopifyApiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Shopify-Storefront-Access-Token: ' . $this->shopifyStorefrontAccessToken,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Log::error("Shopify API Error: HTTP Code {$httpCode}, Response: {$response}");

            return ['errors' => ['message' => 'Shopify API request failed.']];
        }

        return json_decode($response, true);
    }

    /**
     * Converts a raw numeric ID to the required Shopify Product Global ID format.
     *
     * * @param string $rawId The raw numeric ID (e.g., "1234567890").
     *
     * @return string The Global ID (e.g., "gid://shopify/Product/1234567890").
     */
    private function createProductGlobalID(string $rawId): string
    {
        return "gid://shopify/Product/{$rawId}";
    }

    /**
     * Converts a raw numeric ID to the required Shopify Variant Global ID format.
     *
     * * @param string $rawId The raw numeric ID (e.g., "1234567890").
     *
     * @return string The Global ID (e.g., "gid://shopify/ProductVariant/1234567890").
     */
    private function createVariantGlobalID(string $rawId): string
    {
        return "gid://shopify/ProductVariant/{$rawId}";
    }

    /**
     * Extracts the raw numeric ID from a Shopify Global ID.
     *
     * * @param string $url The Global ID (e.g., "gid://shopify/Product/1234567890").
     *
     * @return string The raw numeric ID (e.g., "1234567890").
     */
    private function parseProductID(string $url): string
    {
        $parts = explode('/', $url);

        return end($parts);
    }

    /**
     * Maps the chatbot's generic 'orderby' values to Shopify's specific ProductSortKeys.
     *
     * @param  string  $orderby  the generic sort key requested by the chatbot
     *
     * @return string the corresponding Shopify ProductSortKeys enum value
     */
    private function mapOrderByToShopifySortKey(string $orderby): string
    {
        // Lowercase for case-insensitive matching
        return match (strtolower($orderby)) {
            // 'date' maps to when the product was first created
            'date' => 'CREATED_AT',
            // 'modified' maps to the last time the product was updated
            'modified' => 'UPDATED_AT',
            'id'       => 'ID',
            'title'    => 'TITLE',
            'price'    => 'PRICE',
            // 'popularity' and 'rating' don't have direct, structured fields
            // in the storefront API for arbitrary product search. BEST_SELLING
            // is the closest proxy for general relevance/popularity.
            'popularity', 'rating' => 'BEST_SELLING',
            // Default to BEST_SELLING if an unknown or null sort key is provided
            default => 'BEST_SELLING',
        };
    }

    public function parseProducts(array $products): \Illuminate\Contracts\View\View
    {
        return view('chatbot-ecommerce::frontend-ui.components.product-carousel', [
            'products'    => $products,
            'shop_source' => 'shopify',
        ]);
    }
}
