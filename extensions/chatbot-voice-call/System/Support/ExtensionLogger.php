<?php
namespace App\Extensions\ChatbotVoiceCall\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-voice-call] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-voice-call] '.$message,$context);}}
