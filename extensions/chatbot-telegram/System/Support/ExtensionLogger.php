<?php
namespace App\Extensions\ChatbotTelegram\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-telegram] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-telegram] '.$message,$context);}}
