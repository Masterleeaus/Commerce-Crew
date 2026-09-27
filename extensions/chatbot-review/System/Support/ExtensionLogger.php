<?php
namespace App\Extensions\ChatbotReview\System\Support;use Illuminate\Support\Facades\Log;
class ExtensionLogger{public static function info(string$message,array$context=[]):void{Log::info('[chatbot-review] '.$message,$context);}public static function error(string$message,array$context=[]):void{Log::error('[chatbot-review] '.$message,$context);}}
