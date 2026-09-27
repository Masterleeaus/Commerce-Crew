<?php
namespace App\Extensions\ChatbotWhatsapp\System\Services;
use App\Extensions\ChatbotWhatsapp\System\Contracts\MessagingProviderInterface; use InvalidArgumentException;
class MessagingProviderRegistry { private array $providers=[]; public function register(MessagingProviderInterface $p):void{$this->providers[$p->key()]=$p;} public function get(string $key):MessagingProviderInterface{return $this->providers[$key]??throw new InvalidArgumentException("Unknown messaging provider [$key]");} public function all():array{return $this->providers;} }
