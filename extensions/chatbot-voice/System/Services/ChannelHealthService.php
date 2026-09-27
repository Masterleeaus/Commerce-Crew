<?php
namespace App\Extensions\ChatbotVoice\System\Services;
class ChannelHealthService{public function check():array{return ['ok'=>true,'extension'=>'chatbot-voice','provider'=>'voice','shared_runtime'=>class_exists(\App\Extensions\Chatbot\System\Services\ProviderRegistry::class),'version'=>'3.0.0'];}}
