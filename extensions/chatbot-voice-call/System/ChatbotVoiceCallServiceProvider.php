<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotVoiceCall\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\Chatbot\System\Http\Middleware\LanguageMiddleware;
use App\Extensions\ChatbotVoiceCall\System\Http\Controllers\VoiceCallController;
use App\Extensions\ChatbotVoiceCall\System\Http\Controllers\VoiceCallSettingController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotVoiceCallServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(): void
    {
        $this->registerViews()
            ->registerRoutes()
            ->registerMigrations();
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chatbot-voice-call.php', 'chatbot-voice-call');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'chatbot-voice-call');

        return $this;
    }

    public function registerRoutes(): static
    {
        $this->router()
            ->group([
                'middleware' => ['api', LanguageMiddleware::class],
                'prefix'     => 'api/v2/chatbot',
                'as'         => 'api.v2.chatbot.',
                'controller' => VoiceCallController::class,
            ], function (Router $router) {
                $router->post('{chatbot:uuid}/session/{sessionId}/voice-call/start', 'start')->name('voice-call.start');
                $router->post('{chatbot:uuid}/session/{sessionId}/voice-call/end', 'end')->name('voice-call.end');
                $router->post('{chatbot:uuid}/session/{sessionId}/voice-call/transcript', 'transcript')->name('voice-call.transcript');
            });

        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router
                    ->controller(VoiceCallSettingController::class)
                    ->prefix('dashboard/admin/settings')
                    ->name('dashboard.admin.settings.')
                    ->group(function (Router $router) {
                        $router->get('voice-call', 'index')->name('voice-call');
                        $router->post('voice-call', 'update')->name('voice-call.update');
                    });
            });

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    /** @return Router|Route */
    private function router()
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}
