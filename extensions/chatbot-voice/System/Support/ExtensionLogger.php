<?php
namespace App\Extensions\ChatbotVoice\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-voice] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-voice] '.$message,$context);}}
