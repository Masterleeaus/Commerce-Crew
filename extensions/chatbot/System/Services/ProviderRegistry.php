<?php
namespace App\Extensions\Chatbot\System\Services;
use App\Extensions\Chatbot\System\Contracts\ChannelProviderInterface;
use InvalidArgumentException;
class ProviderRegistry { private array $providers=[]; public function register(ChannelProviderInterface $provider): void { $this->providers[$provider->key()]=$provider; } public function get(string $key): ChannelProviderInterface { return $this->providers[$key]??throw new InvalidArgumentException("Unknown chatbot provider [$key]"); } public function all(): array { return $this->providers; } }
