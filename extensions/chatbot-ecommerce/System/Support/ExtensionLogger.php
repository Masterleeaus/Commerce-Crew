<?php
namespace App\Extensions\ChatbotEcommerce\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-ecommerce] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-ecommerce] '.$message,$context);}}
