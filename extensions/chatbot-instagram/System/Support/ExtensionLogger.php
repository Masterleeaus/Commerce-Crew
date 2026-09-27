<?php
namespace App\Extensions\ChatbotInstagram\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-instagram] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-instagram] '.$message,$context);}}
