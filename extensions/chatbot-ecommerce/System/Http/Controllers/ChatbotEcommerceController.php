<?php

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers;

use App\Extensions\Chatbot\System\Services\ChatbotService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChatbotEcommerceController extends Controller
{
    public function __construct(public ChatbotService $service) {}

    public function index(Request $request)
    {
        $agentOptions = $request->user()
            ->externalChatbots()
            ->select(['id', 'title'])
            ->orderBy('title')
            ->get();

        return view('chatbot-ecommerce::index', compact('agentOptions'));
    }
}
