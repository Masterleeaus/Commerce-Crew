<?php
namespace App\Extensions\ChatbotMessenger\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-messenger] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-messenger] '.$message,$context);}}
