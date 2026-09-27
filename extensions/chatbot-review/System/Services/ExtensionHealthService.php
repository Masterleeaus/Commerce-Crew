<?php
namespace App\Extensions\ChatbotReview\System\Services;
use Illuminate\Support\Facades\DB;
class ExtensionHealthService{public function check():array{$checks=['database'=>false,'configuration'=>true];try{DB::connection()->getPdo();$checks['database']=true;}catch(\Throwable$e){$checks['error']=$e->getMessage();}return['ok'=>!in_array(false,$checks,true),'extension'=>'chatbot-review','checks'=>$checks,'timestamp'=>now()->toIso8601String()];}}
