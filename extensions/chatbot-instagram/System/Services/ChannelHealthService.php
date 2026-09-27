<?php
namespace App\Extensions\ChatbotInstagram\System\Services;
class ChannelHealthService{public function check():array{return ['ok'=>true,'extension'=>'chatbot-instagram','provider'=>'instagram','shared_runtime'=>class_exists(\App\Extensions\Chatbot\System\Services\ProviderRegistry::class),'version'=>'2.0.0'];}}
