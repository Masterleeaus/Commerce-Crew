<?php
namespace App\Extensions\ChatbotCustomerTag\System\Support;
class FeatureFlags{public static function enabled(string $flag,bool $default=false):bool{return (bool)config('chatbot-customer-tag.feature_flags.'.$flag,config('platform-quality.feature_flags.'.$flag,$default));}}
