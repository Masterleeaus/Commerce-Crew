<?php
namespace App\Extensions\ChatbotTelegram\System\Services;
class ChannelHealthService{public function check():array{return ['ok'=>true,'extension'=>'chatbot-telegram','provider'=>'telegram','shared_runtime'=>class_exists(\App\Extensions\Chatbot\System\Services\ProviderRegistry::class),'version'=>'2.0.0'];}}
