<?php
namespace App\Extensions\ChatbotWhatsapp\System\Contracts;
interface MessagingProviderInterface extends \App\Extensions\Chatbot\System\Contracts\ChannelProviderInterface { public function schedule(array $message, \DateTimeInterface $sendAt): array; public function deliveryReport(array $payload): array; }
