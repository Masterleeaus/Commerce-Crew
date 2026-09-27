<?php

namespace App\Extensions\ChatbotCustomerTag\System\Http\Controllers;

use App\Extensions\ChatbotCustomerTag\System\Http\Requests\CustomerTagRequest;
use App\Extensions\ChatbotCustomerTag\System\Models\ChatbotCustomerTag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class CustomerTagController extends Controller
{
    public function index()
    {
        return view('chatbot-customer-tag::tags.index', [
            'items'       => ChatbotCustomerTag::query()->latest()->paginate(20),
            'title'       => __('chatbot-customer-tag::messages.title'),
            'description' => __('chatbot-customer-tag::messages.description'),
        ]);
    }

    public function store(CustomerTagRequest $request): JsonResponse|RedirectResponse
    {
        $tag = ChatbotCustomerTag::query()->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => __('chatbot-customer-tag::messages.created'),
                'tag'     => array_merge($tag->toArray(), ['created_at_formatted' => $tag->created_at?->format('Y-m-d H:i')]),
            ], 201);
        }

        return redirect()->route('dashboard.chatbot-customer-tags.index')
            ->with([
                'type'    => 'success',
                'message' => __('chatbot-customer-tag::messages.created'),
            ]);
    }

    public function edit(ChatbotCustomerTag $chatbotCustomerTag): JsonResponse|View
    {
        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'tag'    => $chatbotCustomerTag,
            ]);
        }

        return view('chatbot-customer-tag::tags.edit', [
            'item'        => $chatbotCustomerTag,
            'action'      => route('dashboard.chatbot-customer-tags.update', $chatbotCustomerTag),
            'method'      => 'PUT',
            'title'       => __('chatbot-customer-tag::messages.edit_title'),
            'description' => __('chatbot-customer-tag::messages.edit_description'),
        ]);
    }

    public function update(CustomerTagRequest $request, ChatbotCustomerTag $chatbotCustomerTag): JsonResponse|RedirectResponse
    {
        $chatbotCustomerTag->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => __('chatbot-customer-tag::messages.updated'),
                'tag'     => array_merge($chatbotCustomerTag->toArray(), ['created_at_formatted' => $chatbotCustomerTag->created_at?->format('Y-m-d H:i')]),
            ]);
        }

        return redirect()->route('dashboard.chatbot-customer-tags.index')
            ->with([
                'type'    => 'success',
                'message' => __('chatbot-customer-tag::messages.updated'),
            ]);
    }

    public function destroy(ChatbotCustomerTag $chatbotCustomerTag): JsonResponse|RedirectResponse
    {
        $chatbotCustomerTag->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => __('chatbot-customer-tag::messages.deleted'),
            ]);
        }

        return redirect()->route('dashboard.chatbot-customer-tags.index')
            ->with([
                'type'    => 'success',
                'message' => __('chatbot-customer-tag::messages.deleted'),
            ]);
    }
}
