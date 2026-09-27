<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Http\Controllers;

use App\Extensions\Chatbot\System\Services\ChatbotAnalyticsService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ChatbotAnalyticsController extends Controller
{
    public function __construct(public ChatbotAnalyticsService $analyticsService) {}

    public function index(Request $request): View
    {
        $chatbotIds = $request->user()->externalChatbots->pluck('id')->toArray();

        $stats = $this->analyticsService->getStatCards($chatbotIds);
        $newConversationsData = $this->analyticsService->getNewConversationsChartData($chatbotIds);
        $agentRepliesData = $this->analyticsService->getAgentRepliesChartData($chatbotIds);
        $topAgents = $this->analyticsService->getTopAgents($chatbotIds);
        $topChannels = $this->analyticsService->getTopChannels($chatbotIds);

        return view('chatbot::analytics.index', [
            'stats'                => $stats,
            'newConversationsData' => $newConversationsData,
            'agentRepliesData'     => $agentRepliesData,
            'topAgents'            => $topAgents,
            'topChannels'          => $topChannels,
        ]);
    }
}
