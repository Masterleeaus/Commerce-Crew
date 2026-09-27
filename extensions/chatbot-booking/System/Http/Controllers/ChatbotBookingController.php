<?php

namespace App\Extensions\ChatbotBooking\System\Http\Controllers;

use App\Extensions\Chatbot\System\Services\ChatbotService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChatbotBookingController extends Controller
{
    public function __construct(public ChatbotService $service) {}

    public function index(Request $request)
    {
        $agentOptions = $request->user()
            ->externalChatbots()
            ->select(['id', 'title'])
            ->orderBy('title')
            ->get();

        return view('chatbot-booking::index', compact('agentOptions'));
    }
}
