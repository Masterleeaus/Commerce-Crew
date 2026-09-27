<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotReview\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note Add this provider to the MarketplaceServiceProvider so it can be discovered by the application.
 */
class ChatbotReviewServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->publishAssets();
    }

    public function registerConfig(): static
    {
        $configPath = __DIR__ . '/../config/chatbot-review.php';

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'chatbot-review');
        }

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'chatbot-review');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'chatbot-review');

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            // __DIR__ . '/../resources/assets/js' => public_path('vendor/chatbot-review/js'),
        ], 'extension');

        return $this;
    }

    public function registerKey(): string
    {
        return 'chatbot-review';
    }
}
