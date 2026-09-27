<?php
namespace App\Extensions\Chatbot\System\Services;
use App\Extensions\Chatbot\System\Contracts\AIRuntimeInterface;
use Illuminate\Contracts\Container\Container;
class AIRuntime implements AIRuntimeInterface {
 public function __construct(private readonly Container $app) {}
 public function complete(array $messages,array $options=[]): array { if($this->app->bound(GeneratorService::class)){ return ['content'=>(string)$this->app->make(GeneratorService::class)->generate($messages,$options),'meta'=>[]]; } return ['content'=>'','meta'=>['status'=>'provider_unavailable']]; }
 public function summarize(array $messages): string { $text=collect($messages)->map(fn($m)=>($m['role']??'participant').': '.strip_tags((string)($m['message']??'')))->implode("\n"); if($text==='') return ''; $result=$this->complete([['role'=>'system','content'=>'Summarize this conversation factually and concisely.'],['role'=>'user','content'=>$text]],['temperature'=>0.2]); return trim($result['content']) ?: mb_substr($text,0,1200); }
 public function translate(string $text,string $locale): string { $r=$this->complete([['role'=>'system','content'=>"Translate to {$locale}. Return only the translation."],['role'=>'user','content'=>$text]],['temperature'=>0]); return trim($r['content']) ?: $text; }
 public function draft(array $context): array { return $this->complete([['role'=>'system','content'=>'Draft a helpful response. Do not claim actions were completed.'],['role'=>'user','content'=>json_encode($context,JSON_UNESCAPED_UNICODE)]],['temperature'=>0.4]); }
}
