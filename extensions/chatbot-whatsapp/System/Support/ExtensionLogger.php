<?php
namespace App\Extensions\ChatbotWhatsapp\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-whatsapp] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-whatsapp] '.$message,$context);}}
