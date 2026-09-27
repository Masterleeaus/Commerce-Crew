<?php
namespace App\Extensions\ChatbotMessenger\System\Services;
class ChannelHealthService{public function check():array{return ['ok'=>true,'extension'=>'chatbot-messenger','provider'=>'messenger','shared_runtime'=>class_exists(\App\Extensions\Chatbot\System\Services\ProviderRegistry::class),'version'=>'2.0.0'];}}
