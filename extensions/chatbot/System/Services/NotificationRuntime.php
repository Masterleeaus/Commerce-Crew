<?php
namespace App\Extensions\Chatbot\System\Services;
use App\Extensions\Chatbot\System\Contracts\NotificationRuntimeInterface;
use Illuminate\Support\Facades\Notification;
class NotificationRuntime implements NotificationRuntimeInterface { public function notify(string $topic,array $recipients,array $payload): void { event('chatbot.notification',compact('topic','recipients','payload')); } }
