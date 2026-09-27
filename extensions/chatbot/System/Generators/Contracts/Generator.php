<?php

namespace App\Extensions\Chatbot\System\Generators\Contracts;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Models\ChatbotHistory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

abstract class Generator implements GeneratorInterface
{
    public string $prompt;

    public EntityEnum $entity;

    public Chatbot $chatbot;

    public ChatbotConversation $conversation;

    public function histories(): Collection|array
    {
        return ChatbotHistory::query()
            ->where('conversation_id', $this->conversation->id)
            ->select('message', 'role', 'id', 'media_url')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    public function setPrompt(string $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getEntity(): EntityEnum
    {
        return $this->entity;
    }

    public function setEntity(EntityEnum $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getChatbot(): Chatbot
    {
        return $this->chatbot;
    }

    public function setChatbot(Chatbot $chatbot): static
    {
        $this->chatbot = $chatbot;

        return $this;
    }

    public function getConversation(): ChatbotConversation
    {
        return $this->conversation;
    }

    public function setConversation(ChatbotConversation $conversation): static
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function isImageMediaUrl(?string $mediaUrl): bool
    {
        if (! $mediaUrl) {
            return false;
        }

        $extension = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH) ?: $mediaUrl, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    }

    /**
     * @return array{base64: string, mime_type: string}|null
     */
    public function encodeImageFromMediaUrl(string $mediaUrl): ?array
    {
        $storagePath = str_replace('/uploads/', '', $mediaUrl);

        if (! Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        $contents = Storage::disk('public')->get($storagePath);
        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));

        $mimeMap = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        ];

        return [
            'base64'    => base64_encode($contents),
            'mime_type' => $mimeMap[$extension] ?? 'image/jpeg',
        ];
    }
}
