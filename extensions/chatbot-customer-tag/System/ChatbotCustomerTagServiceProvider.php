<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotCustomerTag\System;

use App\Extensions\ChatbotCustomerTag\System\Http\Controllers\CustomerTagController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotCustomerTagServiceProvider extends ServiceProvider
{
    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations();
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'chatbot-customer-tag');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'chatbot-customer-tag');

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
                    ->prefix('dashboard/chatbot-customer-tags')
                    ->as('dashboard.chatbot-customer-tags.')
                    ->group(function (Router $router) {
                        $router->get('', [CustomerTagController::class, 'index'])->name('index');
                        $router->post('', [CustomerTagController::class, 'store'])->name('store');
                        $router->get('{chatbotCustomerTag}/edit', [CustomerTagController::class, 'edit'])->name('edit');
                        $router->put('{chatbotCustomerTag}', [CustomerTagController::class, 'update'])->name('update');
                        $router->delete('{chatbotCustomerTag}', [CustomerTagController::class, 'destroy'])->name('destroy');
                    });
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }
}
