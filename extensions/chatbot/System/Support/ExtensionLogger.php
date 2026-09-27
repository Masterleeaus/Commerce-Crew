<?php
namespace App\Extensions\Chatbot\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot] '.$message,$context);}}
