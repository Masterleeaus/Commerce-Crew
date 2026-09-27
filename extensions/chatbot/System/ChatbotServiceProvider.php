<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Extensions\Chatbot\System\Http\Controllers\Api\ChatbotApplicationController;
use App\Extensions\Chatbot\System\Http\Controllers\Api\ChatbotFrameController;
use App\Extensions\Chatbot\System\Http\Controllers\Api\ChatbotRuntimeController;
use App\Extensions\Chatbot\System\Contracts\{AIRuntimeInterface, ConversationRuntimeInterface, MessageRuntimeInterface, NotificationRuntimeInterface, StreamingRuntimeInterface, WorkflowRuntimeInterface};
use App\Extensions\Chatbot\System\Services\{AIRuntime, ConversationRuntime, DraftRuntime, EventOutbox, EventOutboxProcessor, MessageRuntime, NotificationRuntime, ParticipantRuntime, PresenceRuntime, ProviderRegistry, StreamingRuntime, WorkflowRuntime};
use App\Extensions\Chatbot\System\Http\Controllers\AvatarController;
use App\Extensions\Chatbot\System\Http\Controllers\ChatbotAnalyticsController;
use App\Extensions\Chatbot\System\Http\Controllers\ChatbotCannedResponseController;
use App\Extensions\Chatbot\System\Http\Controllers\ChatbotController;
use App\Extensions\Chatbot\System\Http\Controllers\ChatbotCustomerController;
use App\Extensions\Chatbot\System\Http\Controllers\ChatbotKnowledgeBaseArticleController;
use App\Extensions\Chatbot\System\Http\Controllers\ChatbotMultiChannelController;
use App\Extensions\Chatbot\System\Http\Controllers\ChatbotTrainController;
use App\Extensions\Chatbot\System\Http\Middleware\LanguageMiddleware;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotCannedResponse;
use App\Extensions\Chatbot\System\Models\ChatbotKnowledgeBaseArticle;
use App\Extensions\Chatbot\System\Policies\ChatbotCannedResponsePolicy;
use App\Extensions\Chatbot\System\Policies\ChatbotKnowledgeBaseArticlePolicy;
use App\Extensions\Chatbot\System\Policies\ChatbotPolicy;
use App\Helpers\Classes\Helper;
use App\Http\Middleware\CheckTemplateTypeAndPlan;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface
{
    public function register(): void
    {
        $this->registerConfig();
        $this->app->singleton(ProviderRegistry::class);
        $this->app->singleton(EventOutbox::class);
        $this->app->singleton(EventOutboxProcessor::class);
        $this->app->singleton(DraftRuntime::class);
        $this->app->singleton(PresenceRuntime::class);
        $this->app->singleton(ParticipantRuntime::class);
        $this->app->bind(AIRuntimeInterface::class, AIRuntime::class);
        $this->app->bind(ConversationRuntimeInterface::class, ConversationRuntime::class);
        $this->app->bind(MessageRuntimeInterface::class, MessageRuntime::class);
        $this->app->bind(WorkflowRuntimeInterface::class, WorkflowRuntime::class);
        $this->app->bind(NotificationRuntimeInterface::class, NotificationRuntime::class);
        $this->app->bind(StreamingRuntimeInterface::class, StreamingRuntime::class);
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets()
            ->registerPolicies()
            ->registerCommand();

    }

    public function registerPolicies(): self
    {
        Gate::policy(Chatbot::class, ChatbotPolicy::class);
        Gate::policy(ChatbotKnowledgeBaseArticle::class, ChatbotKnowledgeBaseArticlePolicy::class);
        Gate::policy(ChatbotCannedResponse::class, ChatbotCannedResponsePolicy::class);

        return $this;
    }

    public function registerCommand(): static
    {
        $this->commands([Console\Commands\ProcessRuntimeOutboxCommand::class]);

        if (Helper::appIsDemo()) {
            $this->commands([
                Console\Commands\ClearDemoModeCommand::class,
            ]);

            //            if ($this->app->runningInConsole()) {
            //                $this->app->booted(function () {
            //                    $schedule = $this->app->make(Schedule::class);
            //                    $schedule->command('app:clear-chatbot-demo-mode')->everyMinute();
            //                });
            //            }
        }

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/js'     => public_path('vendor/chatbot/js'),
            __DIR__ . '/../resources/assets/images' => public_path('vendor/chatbot/images'),
            __DIR__ . '/../resources/assets/icons'  => public_path('vendor/chatbot/icons'),
            __DIR__ . '/../resources/pwa' => public_path('vendor/chatbot/pwa'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chatbot.php', $this->registerKey());

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', $this->registerKey());

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], $this->registerKey());

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
                    ->controller(ChatbotFrameController::class)
                    ->group(function (Router $router) {
                        $router->get('chatbot/{chatbot:uuid}/frame', 'frame')->name('chatbot.frame');
                    });
            })
            ->group([
                'middleware'     => ['api', LanguageMiddleware::class],
                'prefix'         => 'api/v2/chatbot',
                'as'             => 'api.v2.chatbot.',
                'controller'     => ChatbotApplicationController::class,
            ], function (Router $router) {
                $router->get('{chatbot:uuid}', 'index')->name('index');
                $router->get('{chatbot:uuid}/articles', 'articles')->name('articles');
                $router->get('{chatbot:uuid}/articles/{id}/show', 'showArticles')->name('articles.show');
                $router->get('{chatbot:uuid}/session/{sessionId}', 'indexSession')->name('index.session');
                $router->post('{chatbot:uuid}/session/{sessionId}/conversation', 'conversionStore')->name('conversion.store');
                $router->post('{chatbot:uuid}/session/{sessionId}/conversation/connect', 'connectSupport')->name('conversion.connect.support');
                $router->get('{chatbot:uuid}/session/{sessionId}/conversation/{chatbotConversation}', 'conversion')->name('conversion.show');
                $router->get('{chatbot:uuid}/session/{sessionId}/conversation/{chatbotConversation}/messages', 'messages')->name('conversion.messages');
                $router->get('{chatbot:uuid}/session/{sessionId}/conversation/{chatbotConversation}/export', 'export')->name('conversion.export');
                $router->post('{chatbot:uuid}/session/{sessionId}/conversation/{chatbotConversation}/messages', 'storeMessage')->name('conversion.store.message');
                $router->post('{chatbot:uuid}/session/{sessionId}/conversation/{chatbotConversation}/messages/append', 'appendMessage')->name('conversion.store.message.append');
                $router->post('{chatbot:uuid}/session/{sessionId}/conversation/{chatbotConversation}/file', 'storeFile')->name('conversion.store.file');
                $router->post('{chatbot:uuid}/session/{sessionId}/conversation/{chatbotConversation}/review', 'review')->name('conversion.review');
                $router->post('{chatbot:uuid}/session/{sessionId}/send-email', 'sendEmail')->name('send-email.store');
                $router->any('{chatbot:uuid}/session/{sessionId}/enable-sound', 'enableSound')->name('enable-sound');
                $router->post('{chatbot:uuid}/session/{sessionId}/collect-email', 'collectEmail')->name('collect.email');
            })

            ->group([
                'middleware' => ['api'],
                'prefix'     => 'api/v2/chatbot',
                'as'         => 'api.v2.chatbot.',
                'controller' => ChatbotFrameController::class,
            ], function (Router $router) {
                $router->post('{chatbot:uuid}/session/{sessionId}/page-visit', 'recordPageVisit')->name('page-visit.store');
                $router->put('{chatbot:uuid}/session/{sessionId}/page-visit', 'leavePageVisit')->name('page-visit.leave');
            })


            ->group([
                'middleware' => config('chatbot.runtime.middleware', ['api', 'auth']),
                'prefix' => 'api/v3/chatbot/runtime',
                'as' => 'api.v3.chatbot.runtime.',
                'controller' => ChatbotRuntimeController::class,
            ], function (Router $router) {
                $router->get('health', 'health')->name('health');
                $router->get('conversations', 'search')->name('conversations.search');
                $router->post('conversations/{conversation}/archive', 'archive')->name('conversations.archive');
                $router->post('conversations/{conversation}/merge', 'merge')->name('conversations.merge');
                $router->post('conversations/{conversation}/summary', 'summarize')->name('conversations.summary');
                $router->post('conversations/{conversation}/messages', 'createMessage')->name('messages.create');
                $router->post('conversations/{conversation}/attachments', 'storeAttachment')->name('attachments.store');
                $router->get('attachments/{attachment}/download', 'downloadAttachment')->name('attachments.download');
                $router->delete('attachments/{attachment}', 'deleteAttachment')->name('attachments.delete');
                $router->post('messages/{message}/sent', 'sent')->name('messages.sent');
                $router->post('messages/{message}/delivered', 'delivered')->name('messages.delivered');
                $router->post('messages/{message}/failed', 'failed')->name('messages.failed');
                $router->post('messages/{message}/retry', 'retry')->name('messages.retry');
                $router->post('messages/{message}/read', 'read')->name('messages.read');
                $router->get('conversations/{conversation}/events', 'streamEvents')->name('conversations.events');
                $router->post('conversations/{conversation}/typing', 'typing')->name('conversations.typing');
                $router->get('conversations/{conversation}/presence', 'presence')->name('conversations.presence');
                $router->post('conversations/{conversation}/presence', 'touchPresence')->name('conversations.presence.touch');
                $router->get('conversations/{conversation}/participants', 'participants')->name('conversations.participants');
                $router->post('conversations/{conversation}/participants', 'saveParticipant')->name('conversations.participants.save');
                $router->post('conversations/{conversation}/participants/{participant}/read', 'markParticipantRead')->name('conversations.participants.read');
                $router->delete('conversations/{conversation}/participants/{participant}', 'deleteParticipant')->name('conversations.participants.delete');
                $router->get('conversations/{conversation}/actions', 'actions')->name('conversations.actions');
                $router->post('conversations/{conversation}/actions', 'createAction')->name('conversations.actions.create');
                $router->post('conversations/{conversation}/actions/{action}/approve', 'approveAction')->name('conversations.actions.approve');
                $router->post('conversations/{conversation}/actions/{action}/reject', 'rejectAction')->name('conversations.actions.reject');
                $router->post('conversations/{conversation}/actions/{action}/execute', 'executeAction')->name('conversations.actions.execute');
                $router->post('conversations/{conversation}/actions/{action}/cancel', 'cancelAction')->name('conversations.actions.cancel');
                $router->post('conversations/{conversation}/workflows', 'dispatchWorkflow')->name('conversations.workflows.dispatch');
                $router->get('conversations/{conversation}/workflows/{runId}', 'workflow')->name('conversations.workflows.show');
                $router->post('conversations/{conversation}/workflows/{runId}/resume', 'resumeWorkflow')->name('conversations.workflows.resume');
                $router->post('conversations/{conversation}/workflows/{runId}/complete', 'completeWorkflow')->name('conversations.workflows.complete');
                $router->post('conversations/{conversation}/workflows/{runId}/fail', 'failWorkflow')->name('conversations.workflows.fail');
                $router->post('conversations/{conversation}/workflows/{runId}/retry', 'retryWorkflow')->name('conversations.workflows.retry');
                $router->delete('conversations/{conversation}/workflows/{runId}', 'cancelWorkflow')->name('conversations.workflows.cancel');
                $router->get('conversations/{conversation}/draft', 'draft')->name('conversations.draft');
                $router->put('conversations/{conversation}/draft', 'saveDraft')->name('conversations.draft.save');
                $router->delete('conversations/{conversation}/draft', 'deleteDraft')->name('conversations.draft.delete');
            })
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $route) {
                $route->controller(ChatbotMultiChannelController::class)
                    ->name('dashboard.chatbot-multi-channel.')
                    ->prefix('dashboard/chatbot-multi-channel')
                    ->group(function () {
                        Route::any('', 'index')->name('index');
                        Route::POST('delete', 'delete')->name('delete');
                    });
                $route->group([
                    'prefix'         => 'dashboard/chatbot',
                    'as'             => 'dashboard.chatbot.',
                ], function (Router $router) {
                    $router->resource('knowledge-base-article', ChatbotKnowledgeBaseArticleController::class);
                    $router->resource('canned-response', ChatbotCannedResponseController::class);
                    $router->resource('chatbot-customer', ChatbotCustomerController::class);
                });
                $route
                    ->controller(ChatbotAnalyticsController::class)
                    ->prefix('dashboard/chatbot/analytics')
                    ->name('dashboard.chatbot.analytics.')
                    ->group(function (Router $route) {
                        $route->get('', 'index')->name('index');
                    });
                $route
                    ->controller(ChatbotController::class)
                    ->prefix('dashboard/chatbot')
                    ->name('dashboard.chatbot.')
                    ->group(function (Router $route) {
                        $route->get('', 'index')
                            ->name('index')
                            ->middleware(CheckTemplateTypeAndPlan::class);
                        $route->post('', 'store')->name('store');
                        $route->post('update', 'update')->name('update');
                        $route->post('delete', 'delete')->name('delete');

                        // conversation
                        $route->get('conversations', 'conversations')->name('conversations');
                        $route->get('conversations-with-paginate', 'conversationsWithPaginate')->name('conversations.with.paginate');
                        $route->post('conversations/search', 'searchConversation')->name('conversations.search');

                        // ended routes
                        $route->get('{chatbot}/enbed', 'enbed')->name('enbed');
                    });
                $route
                    ->controller(ChatbotTrainController::class)
                    ->prefix('dashboard/chatbot/train')
                    ->name('dashboard.chatbot.train.')
                    ->group(function (Router $route) {
                        // train routes
                        $route->get('data', 'trainData')->name('data');
                        $route->post('delete-embedding', 'deleteEmbedding')->name('delete');
                        $route->post('generate-embedding', 'generateEmbedding')->name('generate.embedding');
                        $route->get('{chatbot}', 'train')->name('index');
                        $route->post('url', 'trainUrl')->name('url');
                        $route->post('file', 'trainFile')->name('file');
                        $route->post('text', 'trainText')->name('text');
                        $route->post('qa', 'trainQa')->name('qa');
                    });
                $route->post('dashboard/chatbot/avatar/upload', AvatarController::class)
                    ->name('dashboard.chatbot.upload.avatar');
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public function registerKey(): string
    {
        return 'chatbot';
    }
}
