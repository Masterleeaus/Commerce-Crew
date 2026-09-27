<?php
namespace App\Extensions\ChatbotVoiceCall\System\Services;
class ChannelHealthService{public function check():array{return ['ok'=>true,'extension'=>'chatbot-voice-call','provider'=>'voice-call','shared_runtime'=>class_exists(\App\Extensions\Chatbot\System\Services\ProviderRegistry::class),'version'=>'2.0.0'];}}
