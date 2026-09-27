<?php
namespace App\Extensions\ChatbotBooking\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-booking] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-booking] '.$message,$context);}}
