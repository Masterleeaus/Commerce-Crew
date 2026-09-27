<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotVoice\System;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\ChatbotVoice\System\Http\Controllers\AvatarController;
use App\Extensions\ChatbotVoice\System\Http\Controllers\ChatbotVoiceController;
use App\Extensions\ChatbotVoice\System\Http\Controllers\ChatbotVoiceEmbbedController;
use App\Extensions\ChatbotVoice\System\Http\Controllers\ChatbotVoiceHistoryController;
use App\Extensions\ChatbotVoice\System\Http\Controllers\ChatbotVoiceTrainController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Author: MagicAI Team <info@liquid-themes.com>
 *
 * @note When you create a new service provider, make sure to add it to the "MarketplaceServiceProvider". Otherwise, your Laravel application won’t recognize this provider, and the related functions won’t work properly.
 * @note If you want to perform a specific action when an extension is uninstalled, you can use the UninstallExtensionServiceProviderInterface. By implementing this interface, you can define custom operations that will be triggered during the uninstallation of the extension.
 */
class ChatbotVoiceServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register()
    {
        $this->registerConfig();
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets();

    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chatbot-voice.php', 'chatbot-voice');

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/js'     => public_path('vendor/chatbot-voice/js'),
            __DIR__ . '/../resources/assets/images' => public_path('vendor/chatbot-voice/images'),
        ], 'extension');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'chatbot-voice');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'chatbot-voice');

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
                'middleware' => 'web',
            ], function (Router $router) {
                $router
                    ->controller(ChatbotVoiceController::class)
                    ->group(function (Router $router) {
                        $router->get('chatbot-voice/{uuid}/frame', 'frame')->name('chatbot-voice.frame');
                        $router->post('chatbot-voice/checkVoiceBalance', 'checkVoiceBalance')->name('chatbot-voice.checkVoiceBalance');
                    });
            })
            ->group([
                'middleware' => ['web', 'auth'],
                'prefix'	    => 'dashboard/chatbot-voice',
                'as'  		     => 'dashboard.chatbot-voice.',
                'controller' => ChatbotVoiceController::class,
            ], function (Router $router) {
                $router->get('', 'index')->name('index');
                $router->post('store', 'store')->name('store');
                $router->put('update', 'update')->name('update');
                $router->delete('delete', 'delete')->name('delete');

                $router->group([
                    'prefix' 		   => 'train',
                    'as' 			      => 'train.',
                    'controller' 	=> ChatbotVoiceTrainController::class,
                ], function (Router $router) {
                    $router->get('data', 'trainData')->name('data');
                    $router->delete('delete', 'delete')->name('delete');
                    $router->post('generate', 'generateEmbedding')->name('generate');

                    $router->post('file', 'trainFile')->name('file');
                    $router->post('text', 'trainText')->name('text');
                    $router->post('url', 'trainUrl')->name('url');
                });

                $router->group([
                    'prefix' 		   => 'conversation',
                    'as' 			      => 'conversation.',
                ], function (Router $router) {
                    $router->get('with-paginate', [ChatbotVoiceHistoryController::class, 'loadConversationWithPaginate'])->name('with.paginate');
                });
            })
            ->group([
                'prefix'         => 'api/v2/chatbot-voice',
                'as'             => 'api.v2.chatbot-voice.',
            ], function (Router $router) {
                $router->get('{uuid}', [ChatbotVoiceEmbbedController::class, 'index'])->name('index');
                $router->post('{uuid}/store-conversation', [ChatbotVoiceHistoryController::class, 'storeConversation'])->name('store-conversation');
            })
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router->post('dashboard/chatbot-voice/avatar/upload', AvatarController::class)
                    ->name('dashboard.chatbot-voice.upload.avatar');
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
