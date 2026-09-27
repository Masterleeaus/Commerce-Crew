<?php
namespace App\Extensions\ChatbotAgent\System\Services;
use Illuminate\Support\Facades\Http;
class WebhookDispatcher{public function dispatch(string$url,string$event,array$payload,string$secret=''):array{$body=['event'=>$event,'extension'=>'chatbot-agent','payload'=>$payload,'occurred_at'=>now()->toIso8601String()];$signature=hash_hmac('sha256',json_encode($body),$secret);$response=Http::withHeaders(['X-Chatbot-Signature'=>$signature])->post($url,$body);return['status'=>$response->status(),'successful'=>$response->successful()];}}
