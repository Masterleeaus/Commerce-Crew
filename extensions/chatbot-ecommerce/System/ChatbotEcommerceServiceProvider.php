<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\Chatbot\System\Http\Middleware\LanguageMiddleware;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpireBnplOffers;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpireCarts;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpireCheckoutSessions;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpireCommerceContexts;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpireCouponUsages;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpireInventoryReservations;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpirePaymentIntents;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpireShippingQuotes;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ExpireRentalHirePaymentRequests;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\GenerateRentalHireCharges;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ImportMarketplaceOrdersCommand;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ScanMarketplaceInventoryConflictsCommand;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\RetryPaymentWebhooks;
use App\Extensions\ChatbotEcommerce\System\Console\Commands\ManageCommerceLifecycle;
use App\Extensions\ChatbotEcommerce\System\Contracts\CommerceProvider;
use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceTransport;
use App\Extensions\ChatbotEcommerce\System\Contracts\MarketplaceWriteTransport;
use App\Extensions\ChatbotEcommerce\System\Contracts\PaymentProvider;
use App\Extensions\ChatbotEcommerce\System\Contracts\ShippingProvider;
use App\Extensions\ChatbotEcommerce\System\Contracts\TaxProvider;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\BnplAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\BnplApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\ChatbotEcommerceApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\CheckoutApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\CommerceCredentialAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\CommerceSessionAuthorityApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\ConversationalCommerceApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\CommerceRoleApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\CustomerCommunicationApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\CustomerCommunicationAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\FulfillmentApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\InventoryApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\MarketplaceApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\MarketplaceBulkAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\ListingIntelligenceAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\MarketplaceAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\MarketplaceWriteAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\MarketplaceInventoryAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\NativeCommerceApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\NativeOrderApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\PaymentAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\PaymentApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\PaymentWebhookController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\PricingAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\RentalHireAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\RentalHireApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\ShippingAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\ShippingApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\TaxAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api\UnifiedOrderWorkbenchAdminApiController;
use App\Extensions\ChatbotEcommerce\System\Http\Controllers\ChatbotEcommerceController;
use App\Extensions\ChatbotEcommerce\System\Http\Middleware\EnsureCommerceAdminAccess;
use App\Extensions\ChatbotEcommerce\System\Http\Middleware\RequireCommerceSessionAuthority;
use App\Extensions\ChatbotEcommerce\System\Http\Middleware\EnsureCommerceExtensionEnabled;
use App\Extensions\ChatbotEcommerce\System\Policies\ExtensionAccessPolicy;
use App\Extensions\ChatbotEcommerce\System\Providers\InternalCommerceProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\InternalPaymentProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\InternalShippingProvider;
use App\Extensions\ChatbotEcommerce\System\Providers\InternalTaxProvider;
use App\Extensions\ChatbotEcommerce\System\Jobs\ExecuteMarketplaceWrite;
use App\Extensions\ChatbotEcommerce\System\Jobs\ProcessPaymentWebhook;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceCredentialBridge;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceLifecycleRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceScheduleRegistrar;
use App\Extensions\ChatbotEcommerce\System\Services\CommerceTenantContext;
use App\Extensions\ChatbotEcommerce\System\Services\ProviderCircuitBreakerRuntime;
use App\Extensions\ChatbotEcommerce\System\Services\GatewayMarketplaceTransport;
use App\Extensions\ChatbotEcommerce\System\Services\GatewayMarketplaceWriteTransport;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotEcommerceServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/platform-quality.php', 'chatbot-ecommerce');
        $this->app->bind(CommerceProvider::class, InternalCommerceProvider::class);
        $this->app->bind(PaymentProvider::class, InternalPaymentProvider::class);
        $this->app->bind(ShippingProvider::class, InternalShippingProvider::class);
        $this->app->bind(TaxProvider::class, InternalTaxProvider::class);
        $this->app->bind(MarketplaceTransport::class, GatewayMarketplaceTransport::class);
        $this->app->bind(MarketplaceWriteTransport::class, GatewayMarketplaceWriteTransport::class);
        $this->app->singleton(CommerceTenantContext::class);
        $this->app->singleton(CommerceLifecycleRuntime::class);
        $this->app->singleton(ProviderCircuitBreakerRuntime::class);
        $this->app->singleton(CommerceScheduleRegistrar::class);
    }

    public function boot(Kernel $kernel): void
    {
        Gate::policy(Chatbot::class, ExtensionAccessPolicy::class);
        CommerceCredentialBridge::register();
        $this->app->make(CommerceLifecycleRuntime::class)->ensureInstalled('4.9.0');

        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets();

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireInventoryReservations::class, ExpireCarts::class, ExpireCouponUsages::class, ExpireCheckoutSessions::class, ExpireShippingQuotes::class, GenerateRentalHireCharges::class, ExpireRentalHirePaymentRequests::class, ExpirePaymentIntents::class, ExpireBnplOffers::class, ExpireCommerceContexts::class, ImportMarketplaceOrdersCommand::class, ScanMarketplaceInventoryConflictsCommand::class, RetryPaymentWebhooks::class, ManageCommerceLifecycle::class]);
            $this->registerSchedules();
        }
    }

    public function publishAssets(): static
    {
        $this->publishes([], 'extension');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'chatbot-ecommerce');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'chatbot-ecommerce');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'web', 'auth', EnsureCommerceAdminAccess::class],
            ], function (Router $router): void {
                $router
                    ->controller(ChatbotEcommerceController::class)
                    ->prefix('dashboard/chatbot-ecommerce')
                    ->name('dashboard.chatbot-ecommerce.')
                    ->group(function (Router $router): void {
                        $router->get('', 'index')->name('index');
                    });
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:30,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.session-authority.',
                'controller' => CommerceSessionAuthorityApiController::class,
            ], function (Router $router): void {
                $router->post('{chatbot:uuid}/session-authorities', 'issue')->name('issue');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v2/chatbot',
                'as' => 'api.v2.chatbot.',
                'controller' => ChatbotEcommerceApiController::class,
            ], function (Router $router): void {
                $router->post('{chatbot:uuid}/session/{sessionId}/productAddToCart', 'productAddToCart')->name('product.addToCart');
                $router->post('{chatbot:uuid}/session/{sessionId}/productUpdateQuantity', 'productUpdateQuantity')->name('product.UpdateQuantity');
                $router->post('{chatbot:uuid}/session/{sessionId}/productCartCheckout', 'productCartCheckout')->name('product.cartCheckout');
                $router->post('{chatbot:uuid}/session/{sessionId}/productGetCart', 'productGetCart')->name('product.getCart');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.',
                'controller' => NativeCommerceApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/session/{sessionId}/products', 'products')->name('products.index');
                $router->get('{chatbot:uuid}/session/{sessionId}/products/{product}', 'product')->name('products.show');
                $router->get('{chatbot:uuid}/session/{sessionId}/cart', 'cart')->name('cart.show');
                $router->post('{chatbot:uuid}/session/{sessionId}/cart/lines', 'addLine')->name('cart.lines.store');
                $router->put('{chatbot:uuid}/session/{sessionId}/cart/line', 'putLine')->name('cart.line.put');
                $router->patch('{chatbot:uuid}/session/{sessionId}/cart/lines/{line:uuid}', 'updateLine')->name('cart.lines.update');
                $router->delete('{chatbot:uuid}/session/{sessionId}/cart/lines/{line:uuid}', 'removeLine')->name('cart.lines.destroy');
                $router->delete('{chatbot:uuid}/session/{sessionId}/cart/lines', 'clear')->name('cart.lines.clear');
                $router->post('{chatbot:uuid}/session/{sessionId}/cart/recalculate', 'recalculate')->name('cart.recalculate');
                $router->put('{chatbot:uuid}/session/{sessionId}/cart/coupon', 'coupon')->name('cart.coupon.put');
                $router->delete('{chatbot:uuid}/session/{sessionId}/cart/coupon', 'removeCoupon')->name('cart.coupon.destroy');
                $router->post('{chatbot:uuid}/session/{sessionId}/cart/merge', 'merge')->name('cart.merge');
                $router->post('{chatbot:uuid}/session/{sessionId}/cart/abandon', 'abandon')->name('cart.abandon');
                $router->post('{chatbot:uuid}/session/{sessionId}/cart/recover', 'recover')->name('cart.recover');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.conversation.',
                'controller' => ConversationalCommerceApiController::class,
            ], function (Router $router): void {
                $router->get('commerce/tools', 'definitions')->name('tools.index');
                $router->get('{chatbot:uuid}/session/{sessionId}/commerce/context', 'context')->name('context.show');
                $router->patch('{chatbot:uuid}/session/{sessionId}/commerce/context', 'updateContext')->name('context.update');
                $router->post('{chatbot:uuid}/session/{sessionId}/commerce/tools/execute', 'execute')->name('tools.execute');
                $router->post('{chatbot:uuid}/session/{sessionId}/commerce/actions/{actionUuid}/execute', 'executeApproved')->name('actions.execute');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.roles.',
                'controller' => CommerceRoleApiController::class,
            ], function (Router $router): void {
                $router->get('commerce/roles', 'definitions')->name('index');
                $router->post('commerce/roles/resolve', 'resolve')->name('resolve');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.support.',
                'controller' => CustomerCommunicationApiController::class,
            ], function (Router $router): void {
                $router->get('support/tools', 'tools')->name('tools');
                $router->post('{chatbot:uuid}/session/{sessionId}/support/messages', 'ingest')->name('messages.store');
                $router->get('{chatbot:uuid}/session/{sessionId}/support/threads/{thread:uuid}/context', 'context')->name('threads.context');
                $router->post('{chatbot:uuid}/session/{sessionId}/support/threads/{thread:uuid}/draft', 'draft')->name('threads.draft');
                $router->post('{chatbot:uuid}/session/{sessionId}/support/threads/{thread:uuid}/actions', 'prepareAction')->name('actions.store');
                $router->post('{chatbot:uuid}/session/{sessionId}/support/threads/{thread:uuid}/actions/{actionUuid}/execute', 'executeAction')->name('actions.execute');
                $router->post('{chatbot:uuid}/session/{sessionId}/support/threads/{thread:uuid}/escalations', 'escalate')->name('escalations.store');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.orders.',
                'controller' => NativeOrderApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/session/{sessionId}/orders', 'index')->name('index');
                $router->get('{chatbot:uuid}/session/{sessionId}/orders/{order:uuid}', 'show')->name('show');
                $router->post('{chatbot:uuid}/session/{sessionId}/checkouts/{checkout:uuid}/orders', 'materialize')->name('materialize');
                $router->post('{chatbot:uuid}/session/{sessionId}/orders/{order:uuid}/returns', 'requestReturn')->name('returns.store');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.',
                'controller' => CheckoutApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/session/{sessionId}/checkout', 'show')->name('checkout.show');
                $router->put('{chatbot:uuid}/session/{sessionId}/checkout/customer', 'customer')->name('checkout.customer');
                $router->put('{chatbot:uuid}/session/{sessionId}/checkout/delivery', 'delivery')->name('checkout.delivery');
                $router->post('{chatbot:uuid}/session/{sessionId}/checkout/prepare', 'prepare')->name('checkout.prepare');
                $router->post('{chatbot:uuid}/session/{sessionId}/checkout/approve', 'approve')->name('checkout.approve');
                $router->post('{chatbot:uuid}/session/{sessionId}/checkout/cancel', 'cancel')->name('checkout.cancel');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.payments.',
                'controller' => PaymentApiController::class,
            ], function (Router $router): void {
                $router->post('{chatbot:uuid}/session/{sessionId}/checkout/payment-intents', 'createCheckout')->name('checkout.store');
                $router->get('{chatbot:uuid}/session/{sessionId}/checkout/payment-intents/{intent:uuid}', 'showCheckout')->name('checkout.show');
                $router->post('{chatbot:uuid}/rental-hire/accounts/{account:uuid}/payments/{payment:uuid}/payment-intents', 'createRental')->name('rental-hire.store');
                $router->get('{chatbot:uuid}/rental-hire/accounts/{account:uuid}/payments/{payment:uuid}/payment-intents/{intent:uuid}', 'showRental')->name('rental-hire.show');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.bnpl.',
                'controller' => BnplApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/session/{sessionId}/checkout/bnpl/offers', 'checkoutOffers')->name('checkout.offers');
                $router->post('{chatbot:uuid}/session/{sessionId}/checkout/bnpl/offers', 'selectCheckout')->name('checkout.select');
                $router->get('{chatbot:uuid}/rental-hire/accounts/{account:uuid}/payments/{payment:uuid}/bnpl/offers', 'rentalOffers')->name('rental-hire.offers');
                $router->post('{chatbot:uuid}/rental-hire/accounts/{account:uuid}/payments/{payment:uuid}/bnpl/offers', 'selectRental')->name('rental-hire.select');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'throttle:120,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.payment-webhooks.',
                'controller' => PaymentWebhookController::class,
            ], function (Router $router): void {
                $router->post('payment-webhooks/{provider}', 'handle')->name('handle');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.',
                'controller' => ShippingApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/session/{sessionId}/shipping-options', 'options')->name('shipping.options');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.',
                'controller' => InventoryApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/session/{sessionId}/inventory/{variant}', 'show')->whereNumber('variant')->name('inventory.show');
                $router->post('{chatbot:uuid}/session/{sessionId}/inventory/reservations', 'reserve')->name('inventory.reservations.store');
                $router->delete('{chatbot:uuid}/session/{sessionId}/inventory/reservations/{reservation:uuid}', 'release')->name('inventory.reservations.release');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1', RequireCommerceSessionAuthority::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.marketplaces.',
                'controller' => MarketplaceApiController::class,
            ], function (Router $router): void {
                $router->post('{chatbot:uuid}/session/{sessionId}/marketplaces/searches', 'store')->name('searches.store');
                $router->get('{chatbot:uuid}/session/{sessionId}/marketplaces/searches/{search:uuid}', 'show')->name('searches.show');
                $router->get('{chatbot:uuid}/session/{sessionId}/marketplaces/listings/{provider}/{externalListingId}', 'listing')->name('listings.show');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class, 'throttle:20,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.credentials.',
                'controller' => CommerceCredentialAdminApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/commerce/credentials', 'index')->name('index');
                $router->get('{chatbot:uuid}/commerce/credentials/{provider}', 'show')->name('show');
                $router->put('{chatbot:uuid}/commerce/credentials/{provider}', 'store')->name('store');
                $router->post('{chatbot:uuid}/commerce/credentials/{provider}/rotate', 'rotate')->name('rotate');
                $router->post('{chatbot:uuid}/commerce/credentials/{provider}/test', 'test')->name('test');
                $router->delete('{chatbot:uuid}/commerce/credentials/{provider}', 'revoke')->name('revoke');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.marketplaces.',
                'controller' => MarketplaceAdminApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/marketplace-connections', 'connections')->name('connections.index');
                $router->post('{chatbot:uuid}/marketplace-connections', 'storeConnection')->name('connections.store');
                $router->patch('{chatbot:uuid}/marketplace-connections/{connection:uuid}', 'updateConnection')->name('connections.update');
                $router->post('{chatbot:uuid}/marketplace-connections/{connection:uuid}/orders/import', 'importOrders')->name('orders.import');
                $router->get('{chatbot:uuid}/marketplace-orders', 'orders')->name('orders.index');
                $router->get('{chatbot:uuid}/marketplace-sync-runs', 'syncRuns')->name('sync-runs.index');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class, 'throttle:10,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.marketplace-bulk-',
                'controller' => MarketplaceBulkAdminApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/marketplace-bulk-batches', 'index')->name('batches.index');
                $router->get('{chatbot:uuid}/marketplace-bulk-batches/{batch:uuid}', 'show')->name('batches.show');
                $router->post('{chatbot:uuid}/marketplace-connections/{connection:uuid}/marketplace-bulk-batches/preview', 'preview')->name('batches.preview');
                $router->post('{chatbot:uuid}/marketplace-bulk-batches/{batch:uuid}/approve', 'approve')->name('batches.approve');
                $router->post('{chatbot:uuid}/marketplace-bulk-batches/{batch:uuid}/execute', 'execute')->name('batches.execute');
                $router->post('{chatbot:uuid}/marketplace-bulk-batches/{batch:uuid}/rollback', 'rollback')->name('batches.rollback');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class, 'throttle:30,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.marketplace-inventory.',
                'controller' => MarketplaceInventoryAdminApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/marketplace-inventory/policies', 'policies')->name('policies.index');
                $router->post('{chatbot:uuid}/marketplace-inventory/policies', 'storePolicy')->name('policies.store');
                $router->patch('{chatbot:uuid}/marketplace-inventory/policies/{policy:uuid}', 'updatePolicy')->name('policies.update');
                $router->get('{chatbot:uuid}/marketplace-inventory/mappings', 'mappings')->name('mappings.index');
                $router->post('{chatbot:uuid}/marketplace-inventory/mappings', 'storeMapping')->name('mappings.store');
                $router->patch('{chatbot:uuid}/marketplace-inventory/mappings/{mapping:uuid}', 'updateMapping')->name('mappings.update');
                $router->get('{chatbot:uuid}/marketplace-inventory/scans', 'scans')->name('scans.index');
                $router->post('{chatbot:uuid}/marketplace-connections/{connection:uuid}/inventory-scan', 'scan')->name('scans.store');
                $router->get('{chatbot:uuid}/marketplace-inventory/conflicts', 'conflicts')->name('conflicts.index');
                $router->get('{chatbot:uuid}/marketplace-inventory/conflicts/{conflict:uuid}', 'showConflict')->name('conflicts.show');
                $router->post('{chatbot:uuid}/marketplace-inventory/conflicts/{conflict:uuid}/acknowledge', 'acknowledge')->name('conflicts.acknowledge');
                $router->post('{chatbot:uuid}/marketplace-inventory/conflicts/{conflict:uuid}/ignore', 'ignore')->name('conflicts.ignore');
                $router->post('{chatbot:uuid}/marketplace-inventory/conflicts/{conflict:uuid}/prepare-correction', 'prepareCorrection')->name('conflicts.prepare-correction');
                $router->post('{chatbot:uuid}/marketplace-inventory/conflicts/{conflict:uuid}/refresh', 'refreshConflict')->name('conflicts.refresh');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class, 'throttle:30,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.listing-intelligence.',
                'controller' => ListingIntelligenceAdminApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/brand-voice-profiles', 'brandVoices')->name('brand-voices.index');
                $router->post('{chatbot:uuid}/brand-voice-profiles', 'storeBrandVoice')->name('brand-voices.store');
                $router->patch('{chatbot:uuid}/brand-voice-profiles/{brandVoice:uuid}', 'updateBrandVoice')->name('brand-voices.update');
                $router->get('{chatbot:uuid}/product-content-profiles', 'productProfiles')->name('product-profiles.index');
                $router->post('{chatbot:uuid}/product-content-profiles', 'storeProductProfile')->name('product-profiles.store');
                $router->patch('{chatbot:uuid}/product-content-profiles/{productProfile:uuid}', 'updateProductProfile')->name('product-profiles.update');
                $router->get('{chatbot:uuid}/listing-compliance-rules', 'rules')->name('rules.index');
                $router->post('{chatbot:uuid}/listing-compliance-rules', 'storeRule')->name('rules.store');
                $router->get('{chatbot:uuid}/listing-intelligence-runs', 'runs')->name('runs.index');
                $router->get('{chatbot:uuid}/listing-intelligence-runs/{run:uuid}', 'show')->name('runs.show');
                $router->post('{chatbot:uuid}/marketplace-connections/{connection:uuid}/listings/{externalListingId}/listing-intelligence-runs', 'analyse')->name('runs.analyse');
                $router->post('{chatbot:uuid}/listing-intelligence-runs/{run:uuid}/rewrite-proposals', 'generateRewrite')->name('rewrite-proposals.store');
                $router->post('{chatbot:uuid}/marketplace-connections/{connection:uuid}/rewrite-proposals/{rewrite:uuid}/prepare-write', 'prepareWrite')->name('rewrite-proposals.prepare-write');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class, 'throttle:30,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.marketplace-write-',
                'controller' => MarketplaceWriteAdminApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/marketplace-write-proposals', 'index')->name('proposals.index');
                $router->get('{chatbot:uuid}/marketplace-write-proposals/{proposal:uuid}', 'show')->name('proposals.show');
                $router->post('{chatbot:uuid}/marketplace-connections/{connection:uuid}/listings/{externalListingId}/marketplace-write-proposals', 'prepare')->name('proposals.store');
                $router->post('{chatbot:uuid}/marketplace-write-proposals/{proposal:uuid}/approve', 'approve')->name('approve');
                $router->post('{chatbot:uuid}/marketplace-write-proposals/{proposal:uuid}/execute', 'execute')->name('execute');
                $router->post('{chatbot:uuid}/marketplace-write-proposals/{proposal:uuid}/rollback', 'rollback')->name('rollback');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.',
                'controller' => PricingAdminApiController::class,
            ], function (Router $router): void {
                $router->get('pricing/rules', 'rules')->name('pricing.rules.index');
                $router->post('pricing/rules', 'storeRule')->name('pricing.rules.store');
                $router->patch('pricing/rules/{rule}', 'updateRule')->name('pricing.rules.update');
                $router->delete('pricing/rules/{rule}', 'destroyRule')->name('pricing.rules.destroy');
                $router->get('pricing/coupons', 'coupons')->name('pricing.coupons.index');
                $router->post('pricing/coupons', 'storeCoupon')->name('pricing.coupons.store');
                $router->patch('pricing/coupons/{coupon}', 'updateCoupon')->name('pricing.coupons.update');
                $router->delete('pricing/coupons/{coupon}', 'destroyCoupon')->name('pricing.coupons.destroy');
                $router->get('pricing/snapshots/{cart:uuid}', 'snapshots')->name('pricing.snapshots.index');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.',
                'controller' => TaxAdminApiController::class,
            ], function (Router $router): void {
                $router->get('tax/zones', 'zones')->name('tax.zones.index');
                $router->post('tax/zones', 'storeZone')->name('tax.zones.store');
                $router->patch('tax/zones/{zone:uuid}', 'updateZone')->name('tax.zones.update');
                $router->delete('tax/zones/{zone:uuid}', 'destroyZone')->name('tax.zones.destroy');
                $router->get('tax/rates', 'rates')->name('tax.rates.index');
                $router->post('tax/rates', 'storeRate')->name('tax.rates.store');
                $router->patch('tax/rates/{rate:uuid}', 'updateRate')->name('tax.rates.update');
                $router->delete('tax/rates/{rate:uuid}', 'destroyRate')->name('tax.rates.destroy');
                $router->get('tax/exemptions', 'exemptions')->name('tax.exemptions.index');
                $router->post('tax/exemptions', 'storeExemption')->name('tax.exemptions.store');
                $router->patch('tax/exemptions/{exemption:uuid}', 'updateExemption')->name('tax.exemptions.update');
                $router->delete('tax/exemptions/{exemption:uuid}', 'destroyExemption')->name('tax.exemptions.destroy');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.',
                'controller' => ShippingAdminApiController::class,
            ], function (Router $router): void {
                $router->get('shipping/zones', 'zones')->name('shipping.zones.index');
                $router->post('shipping/zones', 'storeZone')->name('shipping.zones.store');
                $router->patch('shipping/zones/{zone:uuid}', 'updateZone')->name('shipping.zones.update');
                $router->delete('shipping/zones/{zone:uuid}', 'destroyZone')->name('shipping.zones.destroy');
                $router->get('shipping/methods', 'methods')->name('shipping.methods.index');
                $router->post('shipping/methods', 'storeMethod')->name('shipping.methods.store');
                $router->patch('shipping/methods/{method:uuid}', 'updateMethod')->name('shipping.methods.update');
                $router->delete('shipping/methods/{method:uuid}', 'destroyMethod')->name('shipping.methods.destroy');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.support.',
                'controller' => CustomerCommunicationAdminApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/support/threads', 'threads')->name('threads.index');
                $router->get('{chatbot:uuid}/support/threads/{thread:uuid}', 'show')->name('threads.show');
                $router->patch('{chatbot:uuid}/support/threads/{thread:uuid}', 'updateThread')->name('threads.update');
                $router->get('{chatbot:uuid}/support/policies', 'policy')->name('policies.show');
                $router->put('{chatbot:uuid}/support/policies', 'updatePolicy')->name('policies.update');
                $router->post('{chatbot:uuid}/support/escalations/{escalation:uuid}/resolve', 'resolveEscalation')->name('escalations.resolve');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.commerce.',
                'controller' => ConversationalCommerceApiController::class,
            ], function (Router $router): void {
                $router->put('{chatbot:uuid}/commerce/budget-lock', 'putBudget')->name('budget-lock.put');
                $router->post('{chatbot:uuid}/commerce/actions/{journal:uuid}/rollback', 'rollback')->name('actions.rollback');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class, 'throttle:60,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.order-workbench.',
                'controller' => UnifiedOrderWorkbenchAdminApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/unified-orders', 'index')->name('unified-orders.index');
                $router->get('{chatbot:uuid}/unified-orders/{unifiedOrder:uuid}', 'show')->name('unified-orders.show');
                $router->post('{chatbot:uuid}/unified-orders/sync', 'sync')->name('unified-orders.sync');
                $router->post('{chatbot:uuid}/unified-orders/import-external', 'importExternal')->name('unified-orders.import-external');
                $router->post('{chatbot:uuid}/unified-orders/{unifiedOrder:uuid}/settlements/import', 'importSettlements')->name('settlements.import');
                $router->post('{chatbot:uuid}/unified-orders/{unifiedOrder:uuid}/reconcile', 'reconcile')->name('unified-orders.reconcile');
                $router->get('{chatbot:uuid}/order-exceptions', 'exceptions')->name('order-exceptions.index');
                $router->get('{chatbot:uuid}/order-exceptions/{exception:uuid}', 'showException')->name('order-exceptions.show');
                $router->post('{chatbot:uuid}/order-exceptions/{exception:uuid}/acknowledge', 'acknowledge')->name('order-exceptions.acknowledge');
                $router->post('{chatbot:uuid}/order-exceptions/{exception:uuid}/assign', 'assign')->name('order-exceptions.assign');
                $router->post('{chatbot:uuid}/order-exceptions/{exception:uuid}/resolve', 'resolve')->name('order-exceptions.resolve');
                $router->post('{chatbot:uuid}/unified-orders/{unifiedOrder:uuid}/refund-proposals', 'prepareRefund')->name('refund-proposals.store');
                $router->post('{chatbot:uuid}/unified-orders/{unifiedOrder:uuid}/customer-contact-proposals', 'prepareCustomerContact')->name('customer-contact-proposals.store');
                $router->post('{chatbot:uuid}/unified-orders/{unifiedOrder:uuid}/support/threads/{thread:uuid}/link', 'linkThread')->name('support-threads.link');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.orders.',
                'controller' => NativeOrderApiController::class,
            ], function (Router $router): void {
                $router->post('{chatbot:uuid}/orders/{order:uuid}/transition', 'transition')->name('transition');
                $router->post('{chatbot:uuid}/returns/{return:uuid}/transition', 'transitionReturn')->name('returns.transition');
                $router->post('{chatbot:uuid}/returns/{return:uuid}/refund', 'refundReturn')->name('returns.refund');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.bnpl.',
                'controller' => BnplAdminApiController::class,
            ], function (Router $router): void {
                $router->get('bnpl/providers', 'providers')->name('providers.index');
                $router->post('bnpl/providers', 'storeProvider')->name('providers.store');
                $router->patch('bnpl/providers/{profile:uuid}', 'updateProvider')->name('providers.update');
                $router->delete('bnpl/providers/{profile:uuid}', 'disableProvider')->name('providers.disable');
                $router->get('bnpl/offers', 'offers')->name('offers.index');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.payments.',
                'controller' => PaymentAdminApiController::class,
            ], function (Router $router): void {
                $router->get('payments/intents', 'index')->name('index');
                $router->get('payments/intents/{intent:uuid}', 'show')->name('show');
                $router->post('payments/intents/{intent:uuid}/authorize', 'authorizePayment')->name('authorize');
                $router->post('payments/intents/{intent:uuid}/capture', 'capture')->name('capture');
                $router->post('payments/intents/{intent:uuid}/cancel', 'cancel')->name('cancel');
                $router->post('payments/intents/{intent:uuid}/refunds', 'refund')->name('refunds.store');
                $router->post('payments/intents/{intent:uuid}/reconcile', 'reconcile')->name('reconcile');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.',
                'controller' => FulfillmentApiController::class,
            ], function (Router $router): void {
                $router->get('checkouts/{checkout:uuid}/fulfillments', 'index')->name('fulfillments.index');
                $router->post('checkouts/{checkout:uuid}/fulfillments', 'store')->name('fulfillments.store');
                $router->post('fulfillments/{fulfillment:uuid}/processing', 'processing')->name('fulfillments.processing');
                $router->post('fulfillments/{fulfillment:uuid}/shipped', 'shipped')->name('fulfillments.shipped');
                $router->post('fulfillments/{fulfillment:uuid}/delivered', 'delivered')->name('fulfillments.delivered');
                $router->post('fulfillments/{fulfillment:uuid}/cancel', 'cancel')->name('fulfillments.cancel');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.',
                'controller' => InventoryApiController::class,
            ], function (Router $router): void {
                $router->get('inventory/locations', 'locations')->name('inventory.locations.index');
                $router->post('inventory/locations', 'storeLocation')->name('inventory.locations.store');
                $router->patch('inventory/locations/{location}', 'updateLocation')->name('inventory.locations.update');
                $router->post('inventory/{variant}/adjustments', 'adjust')->whereNumber('variant')->name('inventory.adjustments.store');
                $router->post('inventory/reservations/{reservation:uuid}/commit', 'commit')->name('inventory.reservations.commit');
                $router->post('inventory/reservations/expire', 'expire')->name('inventory.reservations.expire');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', LanguageMiddleware::class, 'throttle:60,1'],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.rental-hire.',
                'controller' => RentalHireApiController::class,
            ], function (Router $router): void {
                $router->get('{chatbot:uuid}/rental-hire/accounts/{account:uuid}/summary', 'summary')->name('accounts.summary');
                $router->get('{chatbot:uuid}/rental-hire/accounts/{account:uuid}/ledger', 'ledger')->name('accounts.ledger');
                $router->post('{chatbot:uuid}/rental-hire/accounts/{account:uuid}/payment-requests', 'paymentRequest')->name('accounts.payment-requests.store');
                $router->get('{chatbot:uuid}/rental-hire/accounts/{account:uuid}/receipts/{receipt:uuid}', 'receipt')->name('accounts.receipts.show');
            })
            ->group([
                'middleware' => [EnsureCommerceExtensionEnabled::class, 'api', 'auth', EnsureCommerceAdminAccess::class, LanguageMiddleware::class],
                'prefix' => 'api/v3/chatbot/ecommerce',
                'as' => 'api.v3.chatbot.ecommerce.admin.rental-hire.',
                'controller' => RentalHireAdminApiController::class,
            ], function (Router $router): void {
                $router->get('rental-hire/accounts', 'accounts')->name('accounts.index');
                $router->post('rental-hire/accounts', 'storeAccount')->name('accounts.store');
                $router->get('rental-hire/accounts/{account:uuid}', 'showAccount')->name('accounts.show');
                $router->post('rental-hire/accounts/{account:uuid}/access-token', 'rotateToken')->name('accounts.access-token.rotate');
                $router->get('rental-hire/accounts/{account:uuid}/agreements', 'agreements')->name('agreements.index');
                $router->post('rental-hire/accounts/{account:uuid}/agreements', 'storeAgreement')->name('agreements.store');
                $router->patch('rental-hire/agreements/{agreement:uuid}', 'updateAgreement')->name('agreements.update');
                $router->post('rental-hire/agreements/{agreement:uuid}/rates', 'addRate')->name('agreements.rates.store');
                $router->post('rental-hire/agreements/{agreement:uuid}/charges/generate', 'generateCharges')->name('agreements.charges.generate');
                $router->get('rental-hire/accounts/{account:uuid}/summary', 'summary')->name('accounts.summary');
                $router->get('rental-hire/accounts/{account:uuid}/ledger', 'ledger')->name('accounts.ledger');
                $router->post('rental-hire/accounts/{account:uuid}/payment-requests', 'paymentRequest')->name('payment-requests.store');
                $router->post('rental-hire/accounts/{account:uuid}/payments', 'recordPayment')->name('payments.store');
                $router->post('rental-hire/payments/{payment:uuid}/confirm', 'confirmPayment')->name('payments.confirm');
                $router->post('rental-hire/payments/{payment:uuid}/allocate', 'allocatePayment')->name('payments.allocate');
                $router->post('rental-hire/payments/{payment:uuid}/reverse', 'reversePayment')->name('payments.reverse');
                $router->post('rental-hire/accounts/{account:uuid}/adjustments', 'adjustment')->name('adjustments.store');
                $router->get('rental-hire/receipts/{receipt:uuid}', 'receipt')->name('receipts.show');
            });

        return $this;
    }

    private function registerSchedules(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $this->app->make(CommerceScheduleRegistrar::class)->register($schedule);
        });
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        if (! function_exists('app')) return;
        try {
            $lifecycle = app(CommerceLifecycleRuntime::class);
            $lifecycle->beginUninstall('Extension uninstall requested. Operational and financial data will be preserved.');
            $lifecycle->completeUninstall();
        } catch (\Throwable) {
            // Uninstall remains fail-safe when the host container or database is unavailable.
        }
    }
}
