<?php
namespace App\Extensions\ChatbotTelegram\System\Support;
class FeatureFlags{public static function enabled(string $flag,bool $default=false):bool{return (bool)config('chatbot-telegram.feature_flags.'.$flag,config('platform-quality.feature_flags.'.$flag,$default));}}
