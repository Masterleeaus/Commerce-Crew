<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Http\Resources\Api;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ChatbotHistoryResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return [
            'conversation_id'     => $this->getAttribute('conversation_id'),
            'role'                => $this->getAttribute('role'),
            'user_id'             => $this->getAttribute('user_id'),
            'ip_address'          => $this->getAttribute('ip_address'),
            'message'             => $this->getAttribute('message'),
            'message_type'        => $this->getAttribute('message_type'),
            'is_internal_note'    => $this->getAttribute('is_internal_note'),
            'media_url'           => $this->getAttribute('media_url'),
            'media_name'          => $this->getAttribute('media_name'),
            'user'                => $this->getAttribute('user'),
            'created_at'          => $this->getAttribute('created_at')->timezone($this->timezone()),
            'read_at'          	  => $this->getAttribute('read_at'),
            'isHTML'              => $this->isHTML($this->getAttribute('message')),
        ];
    }

    public function timezone(): array|string
    {
        $timezone = request()?->header('x-timezone');

        if (is_string($timezone)) {
            return $timezone;
        }

        return 'UTC';
    }

    private function isHTML(?string $content): bool
    {
        return str_contains($content ?? '', 'lqd-ext-chatbot-html-response');
    }
}
