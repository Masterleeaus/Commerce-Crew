<?php
namespace App\Extensions\ChatbotWhatsapp\System\Support;
use Illuminate\Support\Facades\Log;
class Metrics{public static function increment(string $metric,array $tags=[]):void{event('chatbot.metric',['extension'=>'chatbot-whatsapp','metric'=>$metric,'value'=>1,'tags'=>$tags]);}public static function timing(string $metric,float $milliseconds,array $tags=[]):void{event('chatbot.metric',['extension'=>'chatbot-whatsapp','metric'=>$metric,'value'=>$milliseconds,'tags'=>$tags]);}}
