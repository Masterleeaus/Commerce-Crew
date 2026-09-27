<?php
namespace App\Extensions\ChatbotReview\System\Support;
class FeatureFlags{public static function enabled(string $flag,bool $default=false):bool{return (bool)config('chatbot-review.feature_flags.'.$flag,config('platform-quality.feature_flags.'.$flag,$default));}}
