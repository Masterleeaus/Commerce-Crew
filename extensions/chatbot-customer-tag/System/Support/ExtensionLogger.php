<?php
namespace App\Extensions\ChatbotCustomerTag\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-customer-tag] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-customer-tag] '.$message,$context);}}
