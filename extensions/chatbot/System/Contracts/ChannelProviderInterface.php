<?php
namespace App\Extensions\Chatbot\System\Contracts;
interface ChannelProviderInterface { public function key(): string; public function send(array $message): array; public function normalizeInbound(array $payload): array; public function verifyWebhook(array $headers, string $body): bool; public function capabilities(): array; }
