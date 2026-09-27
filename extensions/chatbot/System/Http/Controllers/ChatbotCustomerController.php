<?php

namespace App\Extensions\Chatbot\System\Http\Controllers;

use App\Extensions\Chatbot\System\Http\Requests\ChatbotCustomerRequest;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class ChatbotCustomerController extends Controller
{
    public function index()
    {
        return view('chatbot::contact.index', [
            'items' => ChatbotCustomer::query()
                ->whereNotNull('email')
                ->where('user_id', auth()->id())
                ->orderBy('id', 'desc')
                ->paginate(20),
            'title'       => __('Contacts'),
            'description' => __('Manage your chatbot contacts and view their interaction history.'),
        ]);
    }

    public function edit(ChatbotCustomer $chatbotCustomer)
    {
        return view('chatbot::contact.edit', [
            'item'        => $chatbotCustomer,
            'action'      => route('dashboard.chatbot.chatbot-customer.update', $chatbotCustomer->getKey()),
            'method'      => 'PUT',
            'chatbots'    => Chatbot::query()->where('user_id', auth()->id())->get(),
            'title'       => __('Edit Contact'),
            'description' => __('Edit Contact.'),
        ]);
    }

    public function update(ChatbotCustomerRequest $request, ChatbotCustomer $chatbotCustomer): RedirectResponse
    {
        $chatbotCustomer->update($request->validated());

        return redirect()->route('dashboard.chatbot.chatbot-customer.index')
            ->with([
                'type'    => 'success',
                'message' => __('Contact updated.'),
            ]);
    }

    public function destroy(ChatbotCustomer $chatbotCustomer): RedirectResponse
    {
        $chatbotCustomer->delete();

        return redirect()->route('dashboard.chatbot.chatbot-customer.index')
            ->with([
                'type'    => 'success',
                'message' => __('Knowledge base article successfully deleted.'),
            ]);
    }
}
