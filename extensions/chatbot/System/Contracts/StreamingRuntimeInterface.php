<?php
namespace App\Extensions\Chatbot\System\Contracts;
interface StreamingRuntimeInterface { public function publish(string $channel, string $event, array $payload): void; public function typing(int $conversationId, string $participant, bool $active): void; public function presence(string $participant, bool $online): void; }
