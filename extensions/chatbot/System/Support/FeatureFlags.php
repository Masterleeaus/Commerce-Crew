<?php
namespace App\Extensions\Chatbot\System\Support;
class FeatureFlags{public static function enabled(string $flag,bool $default=false):bool{return (bool)config('chatbot.feature_flags.'.$flag,config('platform-quality.feature_flags.'.$flag,$default));}}
