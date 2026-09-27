<?php
namespace App\Extensions\ChatbotAgent\System\Services;
use Illuminate\Support\Facades\DB;
class ExtensionHealthService{public function check():array{$checks=['database'=>false,'configuration'=>true];try{DB::connection()->getPdo();$checks['database']=true;}catch(\Throwable$e){$checks['error']=$e->getMessage();}return['ok'=>!in_array(false,$checks,true),'extension'=>'chatbot-agent','checks'=>$checks,'timestamp'=>now()->toIso8601String()];}}
