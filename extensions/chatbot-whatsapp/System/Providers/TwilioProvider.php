<?php
namespace App\Extensions\ChatbotWhatsapp\System\Providers;
class TwilioProvider extends AbstractHttpMessagingProvider { public function key():string{return 'twilio';} public function send(array $message):array{ return $this->fallback($message); } }
