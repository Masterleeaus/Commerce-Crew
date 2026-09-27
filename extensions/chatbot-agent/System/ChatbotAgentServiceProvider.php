<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotAgent\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\ChatbotAgent\System\Http\Controllers\AblyController;
use App\Extensions\ChatbotAgent\System\Http\Controllers\ChatbotAgentController;
use App\Extensions\ChatbotAgent\System\Http\Controllers\ChatbotCustomerTagController;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotAgentServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets();

    }

    public function publishAssets(): static
    {
        $this->publishes([
            //            __DIR__ . '/../resources/assets/js' => public_path('vendor/chatbot-agent/js'),
            //            __DIR__ . '/../resources/assets/images' => public_path('vendor/chatbot-agent/images'),
        ], 'extension');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'chatbot-agent');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'chatbot-agent');

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
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router
                    ->controller(ChatbotAgentController::class)
                    ->prefix('dashboard/chatbot-agent')
                    ->name('dashboard.chatbot-agent.')
                    ->group(function (Router $router) {
                        $router->get('', 'index')->name('index');
                        $router->get('notification/count', 'notification')->name('notification.count');
                        $router->post('conversations/name', 'name')->name('conversations.name.update');
                        $router->post('conversations/pinned', 'pinned')->name('conversations.pinned');
                        $router->post('conversations/closed', 'closed')->name('conversations.closed');
                        $router->get('conversations', 'conversations')->name('conversations');
                        $router->put('conversations', 'update')->name('conversations.update');
                        $router->post('conversations/search', 'searchConversation')->name('conversations.search');
                        $router->get('conversations-with-paginate', 'conversationsWithPaginate')->name('conversations.with.paginate');
                        $router->get('conversations-history-session', 'conversationsHistorySession')->name('conversations.history.session');
                        $router->get('history', 'history')->name('history');
                        $router->post('history', 'store');
                        $router->delete('destroy', 'destroy')->name('destroy');
                        $router->get('canned-responses', 'cannedResponses')->name('canned-responses');
                        $router->post('message-rewrite', 'rewriteMessage')->name('message-rewrite');
                    });

                $router
                    ->controller(AblyController::class)
                    ->prefix('dashboard/admin/settings')
                    ->name('dashboard.admin.settings.')
                    ->group(function (Router $router) {
                        $router->get('ably', 'index')->name('ably');
                        $router->post('ably', 'update')->name('ably.update');
                    });

                if (MarketplaceHelper::isRegistered('chatbot-customer-tag')) {
                    $router
                        ->controller(ChatbotCustomerTagController::class)
                        ->prefix('dashboard/chatbot-agent/customer-tags')
                        ->name('dashboard.chatbot-agent.customer-tags.')
                        ->group(function (Router $router) {
                            $router->get('', 'index')->name('index');
                            $router->post('', 'store')->name('store');
                            $router->post('sync', 'assign')->name('sync');
                        });
                }
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}
