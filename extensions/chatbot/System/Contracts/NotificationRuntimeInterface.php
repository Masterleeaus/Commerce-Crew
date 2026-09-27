<?php
namespace App\Extensions\Chatbot\System\Contracts;
interface NotificationRuntimeInterface { public function notify(string $topic, array $recipients, array $payload): void; }
