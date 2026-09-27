<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Http\Resources\Admin;

use App\Extensions\Chatbot\System\Http\Resources\Api\ChatbotHistoryResource;
use App\Extensions\Chatbot\System\Models\ChatbotPageVisit;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use JsonSerializable;

class ChatbotConversationResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return [
            'id'                => $this->getAttribute('id'),
            'session_id'        => $this->getAttribute('session_id'),
            'chatbot_channel'   => $this->getAttribute('chatbot_channel'),
            'color'             => Arr::random(['#879EC4', '#018a1a', '#7f00c8', '#e633ec']),
            'ip_address'        => $this->getAttribute('ip_address'),
            'conversation_name' => $this?->customer?->name ?: $this->getAttribute('conversation_name'),
            'customer'          => $this->customer,
            'chatbot'           => ChatbotResource::make($this->getAttribute('chatbot')),
            'lastMessage'       => $this->getAttribute('lastMessage') ? ChatbotHistoryResource::make($this->getAttribute('lastMessage')) : [
                'message' => 'No message',
                'read_at' => now(),
            ],
            'ticket_status'      => $this->getAttribute('ticket_status'),
            'country_code'       => $this->getAttribute('country_code'),
            'pinned'             => $this->getAttribute('pinned'),
            'chatbot_id'         => $this->getAttribute('chatbot_id'),
            'created_at'         => $this->getAttribute('created_at'),
            'updated_at'         => $this->getAttribute('updated_at'),
            'histories'          => ChatbotHistoryResource::collection($this->getAttribute('histories')),
            'customer_tags'      => $this->whenLoaded('customerTags', function () {
                return $this->customerTags->map(fn ($tag) => [
                    'id'                => $tag->getKey(),
                    'tag'               => $tag->getAttribute('tag'),
                    'tag_color'         => $tag->getAttribute('tag_color'),
                    'background_color'  => $tag->getAttribute('background_color'),
                ]);
            }),
            'visited_pages'      => ChatbotPageVisit::query()
                ->where('chatbot_id', $this->getAttribute('chatbot_id'))
                ->where('session_id', $this->getAttribute('session_id'))
                ->orderByDesc('entered_at')
                ->get()
                ->map(fn (ChatbotPageVisit $visit) => [
                    'page_url'   => $visit->page_url,
                    'page_title' => $visit->page_title,
                    'duration'   => $visit->duration,
                    'entered_at' => $visit->entered_at?->toISOString(),
                ]),
        ];
    }
}
