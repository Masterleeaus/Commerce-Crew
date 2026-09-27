<?php

namespace App\Extensions\Chatbot\System\Http\Controllers;

use App\Extensions\Chatbot\System\Http\Requests\ChatbotKnowledgeBaseArticleRequest;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotKnowledgeBaseArticle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ChatbotKnowledgeBaseArticleController extends Controller
{
    public function index()
    {
        return view('chatbot::knowledge-base-article.index', [
            'items' => ChatbotKnowledgeBaseArticle::query()
                ->where('user_id', auth()->id())
                ->paginate(20),
            'title'       => __('Knowledge Base'),
            'description' => __('Manage your knowledge base articles.'),
        ]);
    }

    public function create()
    {
        return view('chatbot::knowledge-base-article.edit', [
            'item'        => new ChatbotKnowledgeBaseArticle,
            'action'      => route('dashboard.chatbot.knowledge-base-article.store'),
            'method'      => 'POST',
            'chatbots'    => Chatbot::query()->where('user_id', auth()->id())->get(),
            'title'       => __('Create Article'),
            'description' => __('Create a new knowledge base article to help users find answers to their questions.'),
        ]);
    }

    public function store(ChatbotKnowledgeBaseArticleRequest $request): RedirectResponse
    {
        ChatbotKnowledgeBaseArticle::query()->create($request->validated());

        return redirect()->route('dashboard.chatbot.knowledge-base-article.index')
            ->with([
                'type'    => 'success',
                'message' => __('Knowledge base article created.'),
            ]);
    }

    public function edit(ChatbotKnowledgeBaseArticle $knowledgeBaseArticle)
    {
        $this->authorize('edit', $knowledgeBaseArticle);

        return view('chatbot::knowledge-base-article.edit', [
            'item'        => $knowledgeBaseArticle,
            'action'      => route('dashboard.chatbot.knowledge-base-article.update', $knowledgeBaseArticle->getKey()),
            'method'      => 'PUT',
            'chatbots'    => Chatbot::query()->where('user_id', auth()->id())->get(),
            'title'       => __('Edit Article'),
            'description' => __('Edit a knowledge base article to help users find answers to their questions.'),
        ]);
    }

    public function update(ChatbotKnowledgeBaseArticleRequest $request, ChatbotKnowledgeBaseArticle $knowledgeBaseArticle): RedirectResponse
    {
        $this->authorize('update', $knowledgeBaseArticle);

        $knowledgeBaseArticle->update($request->validated());

        return redirect()->route('dashboard.chatbot.knowledge-base-article.index')
            ->with([
                'type'    => 'success',
                'message' => __('Knowledge base article updated.'),
            ]);
    }

    public function destroy(ChatbotKnowledgeBaseArticle $knowledgeBaseArticle): RedirectResponse
    {
        $this->authorize('delete', $knowledgeBaseArticle);

        $knowledgeBaseArticle->delete();

        return redirect()->route('dashboard.chatbot.knowledge-base-article.index')
            ->with([
                'type'    => 'success',
                'message' => __('Knowledge base article successfully deleted.'),
            ]);
    }
}
