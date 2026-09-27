<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotTelegram\System;

use App\Extensions\ChatbotTelegram\System\Http\Controllers\ChatbotTelegramController;
use App\Extensions\ChatbotTelegram\System\Http\Controllers\Webhook\ChatbotTelegramWebhookController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotTelegramServiceProvider extends ServiceProvider
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

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/icons' => public_path('vendor/telegram-channel/icons'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/telegram-channel.php', 'telegram-channel');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'telegram-channel');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'telegram-channel');

        return $this;
    }

    private function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware'     => 'api',
                'prefix'         => 'api/v2/chatbot',
                'as'             => 'api.v2.chatbot.channel.',
            ], function (Router $router) {
                $router->any('{chatbotId}/channel/{channelId}/telegram', [ChatbotTelegramWebhookController::class, 'handle'])->name('telegram.post.handle');
            })
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router->controller(ChatbotTelegramController::class)
                    ->name('dashboard.chatbot-multi-channel.telegram.')
                    ->prefix('dashboard/chatbot-multi-channel/telegram')
                    ->group(function (Router $router) {
                        $router->post('store', 'store')->name('store');
                    });
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }
}
