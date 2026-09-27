<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Models\ChatbotCart;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceCredentialRuntime;
use App\Extensions\ChatbotEcommerce\System\Tools\ShopifyToolHandler;
use App\Extensions\ChatbotEcommerce\System\Tools\WooCommerceToolHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotEcommerceApiController extends Controller
{
    public function productAddToCart(Chatbot $chatbot, string $sessionId, Request $request): JsonResponse
    {
        $shop_source = $chatbot->getAttribute('shop_source') ?? 'shopify';
        $request->validate([
            'productID'       => 'integer|required',
            'selectedOptions' => $shop_source === 'shopify' ? 'array|required' : 'array|sometimes',
        ]);

        $cart = ChatbotCart::query()->where('session_id', $sessionId)
            ->where('chatbot_id', $chatbot->getAttribute('id'))
            ->first();

        if (! $cart) {
            $cart = ChatbotCart::create([
                'session_id'          => $sessionId,
                'chatbot_customer_id' => $chatbot->getAttribute('user_id'),
                'chatbot_id'          => $chatbot->getAttribute('id'),
                'products'            => [],
                'product_data'        => [],
                'product_source'      => $shop_source,
            ]);
        }

        $products = $cart->products ?? [];
        if (! is_array($products)) {
            $products = json_decode($products, true) ?: [];
        }

        $productData = $cart->product_data ?? [];
        if (! is_array($productData)) {
            $productData = json_decode($productData, true) ?: [];
        }

        if (! empty($products) && $cart->product_source !== $shop_source) {
            $products = [];
            $productData = [];
        }

        // Shopify
        if ($shop_source === 'shopify') {
            $shopifyCredentials = app(CommerceCredentialRuntime::class)->resolve($chatbot, 'shopify');
            $shopifyToolHandler = new ShopifyToolHandler(
                (string) $chatbot->shopify_domain,
                (string) $shopifyCredentials['access_token']
            );

            $result = $shopifyToolHandler->getVariantIdByOptions(
                $request->get('productID'),
                $request->get('selectedOptions')
            );

            if (! isset($result['variantId'])) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Product not found.',
                ]);
            }

            $variantID = $result['variantId'];

            $products[] = $variantID;

            $result = $shopifyToolHandler->getProductDataByVariantId($variantID);
            $productData[$variantID] = $result;
        }

        // WooCommerce
        if ($shop_source === 'woocommerce') {
            $wooCredentials = app(CommerceCredentialRuntime::class)->resolve($chatbot, 'woocommerce');
            $wooToolHandler = new WooCommerceToolHandler(
                (string) $chatbot->woocommerce_domain,
                (string) $wooCredentials['consumer_key'],
                (string) $wooCredentials['consumer_secret']
            );

            $productID = $request->get('productID');
            $selectedOptions = $request->get('selectedOptions', []);
            $formattedOptions = [];

            if (! empty($selectedOptions)) {
                foreach ($selectedOptions as $option) {
                    [$key, $value] = explode(':', $option);
                    $formattedOptions[$key] = $value;
                }

                $variantID = $wooToolHandler->getVariantIdByAttributes((int) $productID, $formattedOptions);
                if (empty($variantID)) {
                    return response()->json([
                        'error'   => true,
                        'message' => 'Product variant not found.',
                    ]);
                }
                $variantID = (string) $variantID;
                $products[] = $variantID;
                $result = $wooToolHandler->getProductDetailsById((int) $variantID);
                $productData[$variantID] = $result;
            } else {
                $products[] = $productID;
                $result = $wooToolHandler->getProductDetailsById((int) $productID);
                $productData[$productID] = $result;
            }
        }

        $cart->update([
            'products'       => $products,
            'product_data'   => $productData,
            'product_source' => $shop_source,
        ]);

        return response()->json([
            'message'   => 'Added product to the cart.',
            'variantID' => $cart->products,
            'cart'      => [
                'products'     => $cart->products,
                'product_data' => $cart->product_data,
            ],
        ]);
    }

    public function productUpdateQuantity(Chatbot $chatbot, string $sessionId, Request $request): JsonResponse
    {
        $request->validate([
            'productID' => 'integer|required',
            'type'      => 'string|required',
        ]);

        $update_type = $request->get('type');
        $cart = ChatbotCart::query()->where('session_id', $sessionId)
            ->where('chatbot_id', $chatbot->getAttribute('id'))
            ->firstOrFail();
        $message = '';

        $products = $cart->products ?? [];
        if (! is_array($products)) {
            $products = json_decode($products, true) ?: [];
        }

        if ($update_type === 'removeAll') {
            $products = array_filter($products, function ($item) use ($request) {
                return $item != $request->get('productID');
            });

            $products = array_values($products);
        } elseif ($update_type === 'remove') {
            $key = array_search($request->get('productID'), $products);

            if ($key !== false) {
                unset($products[$key]);
                $products = array_values($products);
            }
        } elseif ($update_type === 'add') {
            $products[] = $request->get('productID');
        }

        $cart->update([
            'products' => $products,
        ]);

        switch ($update_type) {
            case 'add':
                $message = 'Added product to the cart.';

                break;
            case 'remove':
                $message = 'Removed product from the cart.';

                break;
            case 'removeAll':
                $message = 'Removed all instances of product from the cart.';

                break;
        }

        return response()->json([
            'message'   => $message,
            'variantID' => $cart->products,
            'cart'      => [
                'products'     => $cart->products,
                'product_data' => $cart->product_data,
            ],
        ]);
    }

    public function productCartCheckout(Chatbot $chatbot, string $sessionId): JsonResponse
    {
        $shop_source = $chatbot->getAttribute('shop_source') ?? 'shopify';

        $cart = ChatbotCart::query()->where('session_id', $sessionId)
            ->where('chatbot_id', $chatbot->getAttribute('id'))
            ->first();

        if (! $cart) {
            return response()->json([
                'error'   => true,
                'status'  => 'not_found',
                'message' => 'Cart not found.',
            ]);
        }

        $products = $cart->products ?? [];
        if (! is_array($products)) {
            $products = json_decode($products, true) ?: [];
        }

        if (empty($products)) {
            return response()->json([
                'error'   => true,
                'message' => 'Cart is empty.',
            ]);
        }

        $variantIds = [];
        $quantities = [];
        $productCounts = array_count_values($products);
        $productAndQuantities = [];
        foreach ($productCounts as $productId => $qty) {
            $variantIds[] = $productId;
            $quantities[] = $qty;
            $productAndQuantities[$productId] = $qty;
        }

        // Shopify
        if ($shop_source === 'shopify') {
            $shopifyCredentials = app(CommerceCredentialRuntime::class)->resolve($chatbot, 'shopify');
            $shopifyToolHandler = new ShopifyToolHandler(
                (string) $chatbot->shopify_domain,
                (string) $shopifyCredentials['access_token']
            );

            $result = $shopifyToolHandler->createCheckoutByVariantIds(
                $variantIds,
                $quantities
            );

            if (isset($result['error'])) {
                return response()->json([
                    'error'   => true,
                    'message' => $result['error'],
                ]);
            }

            if (isset($result['checkoutUrl'])) {
                return response()->json([
                    'checkoutUrl' => $result['checkoutUrl'],
                    'message'     => 'Redirecting to checkout...',
                ]);
            }
        }

        // WooCommerce
        if ($shop_source === 'woocommerce') {
            $wooCredentials = app(CommerceCredentialRuntime::class)->resolve($chatbot, 'woocommerce');
            $wooToolHandler = new WooCommerceToolHandler(
                (string) $chatbot->woocommerce_domain,
                (string) $wooCredentials['consumer_key'],
                (string) $wooCredentials['consumer_secret']
            );

            $result = $wooToolHandler->getCheckoutUrl($productAndQuantities);

            if (empty($result)) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Checkout URL not found.',
                ]);
            }

            if (! empty($result)) {
                return response()->json([
                    'checkoutUrl' => $result,
                    'message'     => 'Redirecting to checkout...',
                ]);
            }
        }

        return response()->json([
            'error'   => true,
            'message' => 'Checkout URL not found.',
        ]);
    }

    public function productGetCart(Chatbot $chatbot, string $sessionId, Request $request): JsonResponse
    {
        $cart = [];
        $cartQuery = ChatbotCart::query()
            ->where('chatbot_id', $chatbot->getAttribute('id'))
            ->where('session_id', $sessionId)
            ->first();

        if (! $cartQuery) {
            return response()->json([
                'error'   => true,
                'status'  => 'not_found',
                'message' => 'Cart not found.',
            ]);
        }

        $products = $cartQuery->products ?? [];
        if (! is_array($products)) {
            $products = json_decode($products, true) ?: [];
        }

        $productData = $cartQuery->product_data ?? [];
        if (! is_array($productData)) {
            $productData = json_decode($productData, true) ?: [];
        }

        $cart['products'] = $products;
        $cart['product_data'] = $productData;
        $cart['wrapper'] = $request->get('wrapper', false);

        if ($cartQuery->product_source != $chatbot->shop_source) {
            $cart['products'] = [];
            $cart['product_data'] = [];
        }

        return response()->json([
            'cart' => $cart,
        ]);
    }
}
