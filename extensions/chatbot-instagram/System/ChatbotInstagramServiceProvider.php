<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotInstagram\System;

use App\Extensions\ChatbotInstagram\System\Http\Controllers\ChatbotInstagramController;
use App\Extensions\ChatbotInstagram\System\Http\Controllers\ChatbotInstagramSettingController;
use App\Extensions\ChatbotInstagram\System\Http\Controllers\Oauth\InstagramController;
use App\Extensions\ChatbotInstagram\System\Http\Controllers\Webhook\ChatbotInstagramWebhookController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ChatbotInstagramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->publishAssets();
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'chatbot-instagram');

        return $this;
    }

    protected function registerViews(): static
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'instagram-channel');

        return $this;
    }

    protected function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chatbot-instagram.php', 'chatbot-instagram');

        return $this;
    }

    protected function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/icons' => public_path('vendor/instagram-channel/icons'),
        ], 'extension');

        return $this;
    }

    protected function registerRoutes(): static
    {
        $router = $this->router();

        $router->group([
            'middleware' => ['web', 'auth', 'admin'],
            'prefix'     => 'dashboard/admin/chatbot-instagram/settings',
            'as'         => 'dashboard.admin.chatbot-instagram.settings.',
        ], function (Router $router) {
            $router->controller(ChatbotInstagramSettingController::class)
                ->group(function (Router $router) {
                    $router->get('', 'index')->name('index');
                    $router->post('', 'update')->name('update');
                });
        });

        $router->group([
            'middleware' => ['web', 'auth'],
        ], function (Router $router) {
            $router->prefix('chatbot/instagram/oauth')
                ->as('chatbot.instagram.oauth.')
                ->controller(InstagramController::class)
                ->group(function (Router $router) {
                    $router->get('redirect', 'redirect')->name('redirect');
                    $router->get('callback', 'callback')->name('callback');
                });
        });

        $router->group([
            'middleware'     => 'api',
            'prefix'         => 'api/v2/chatbot',
            'as'             => 'api.v2.chatbot.',
        ], function (Router $router) {
            $router->any('webhook/instagram', [ChatbotInstagramWebhookController::class, 'handleGlobal'])
                ->name('webhook.instagram');

            $router->any('{chatbotId}/channel/{channelId}/instagram', [ChatbotInstagramWebhookController::class, 'handle'])
                ->name('channel.instagram.post.handle');
        });

        $router->group([
            'middleware' => ['web', 'auth'],
        ], function (Router $router) {
            $router->controller(ChatbotInstagramController::class)
                ->name('dashboard.chatbot-multi-channel.instagram.')
                ->prefix('dashboard/chatbot-multi-channel/instagram')
                ->group(function (Router $router) {
                    $router->post('store', 'store')->name('store');
                });
        });

        return $this;
    }

    protected function router(): Router|Route
    {
        return $this->app['router'];
    }
}
