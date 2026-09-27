<?php
namespace App\Extensions\ChatbotWhatsapp\System\Providers;
class MessageBirdProvider extends AbstractHttpMessagingProvider { public function key():string{return 'messagebird';} public function send(array $message):array{ return $this->fallback($message); } }
