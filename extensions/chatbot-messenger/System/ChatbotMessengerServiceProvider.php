<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotMessenger\System;

use App\Extensions\ChatbotMessenger\System\Http\Controllers\ChatbotMessengerController;
use App\Extensions\ChatbotMessenger\System\Http\Controllers\Webhook\ChatbotMessengerWebhookController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotMessengerServiceProvider extends ServiceProvider
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
            ->registerMigrations()
            ->publishAssets()
            ->registerComponents();
    }

    public function registerComponents(): static
    {
        //        $this->loadViewComponentsAs('example', []);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/icons' => public_path('vendor/messenger-channel/icons'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/messenger-channel.php', 'messenger-channel');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'messenger-channel');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'messenger-channel');

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
                'middleware'     => 'api',
                'prefix'         => 'api/v2/chatbot',
                'as'             => 'api.v2.chatbot.channel.',
            ], function (Router $router) {
                $router->any('{chatbotId}/channel/{channelId}/messenger', [ChatbotMessengerWebhookController::class, 'handle'])->name('messenger.post.handle');
            })->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router->controller(ChatbotMessengerController::class)
                    ->name('dashboard.chatbot-multi-channel.messenger.')
                    ->prefix('dashboard/chatbot-multi-channel/messenger')
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
