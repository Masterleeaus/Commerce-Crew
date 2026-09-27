<?php

namespace App\Extensions\Chatbot\System\Http\Controllers;

use App\Extensions\Chatbot\System\Http\Requests\ChatbotCannedResponseRequest;
use App\Extensions\Chatbot\System\Models\ChatbotCannedResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ChatbotCannedResponseController extends Controller
{
    public function index()
    {
        return view('chatbot::canned-response.index', [
            'items' => ChatbotCannedResponse::query()
                ->where('user_id', auth()->id())
                ->paginate(20),
            'title'       => __('Canned Responses'),
            'description' => __('Manage canned responses for quick customer replies.'),
        ]);
    }

    public function create()
    {
        return view('chatbot::canned-response.edit', [
            'item'        => new ChatbotCannedResponse,
            'action'      => route('dashboard.chatbot.canned-response.store'),
            'method'      => 'POST',
            'title'       => __('Create Canned Response'),
            'description' => __('Create a canned response to reuse in customer conversations.'),
        ]);
    }

    public function store(ChatbotCannedResponseRequest $request): RedirectResponse
    {
        ChatbotCannedResponse::query()->create($request->validated());

        return redirect()->route('dashboard.chatbot.canned-response.index')
            ->with([
                'type'    => 'success',
                'message' => __('Canned response created.'),
            ]);
    }

    public function edit(ChatbotCannedResponse $cannedResponse)
    {
        $this->authorize('edit', $cannedResponse);

        return view('chatbot::canned-response.edit', [
            'item'        => $cannedResponse,
            'action'      => route('dashboard.chatbot.canned-response.update', $cannedResponse->getKey()),
            'method'      => 'PUT',
            'title'       => __('Edit Canned Response'),
            'description' => __('Update the canned response content.'),
        ]);
    }

    public function update(ChatbotCannedResponseRequest $request, ChatbotCannedResponse $cannedResponse): RedirectResponse
    {
        $this->authorize('update', $cannedResponse);

        $cannedResponse->update($request->validated());

        return redirect()->route('dashboard.chatbot.canned-response.index')
            ->with([
                'type'    => 'success',
                'message' => __('Canned response updated.'),
            ]);
    }

    public function destroy(ChatbotCannedResponse $cannedResponse): RedirectResponse
    {
        $this->authorize('delete', $cannedResponse);

        $cannedResponse->delete();

        return redirect()->route('dashboard.chatbot.canned-response.index')
            ->with([
                'type'    => 'success',
                'message' => __('Canned response deleted.'),
            ]);
    }
}
