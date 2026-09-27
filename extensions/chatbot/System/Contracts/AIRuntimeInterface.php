<?php
namespace App\Extensions\Chatbot\System\Contracts;
interface AIRuntimeInterface { public function complete(array $messages, array $options = []): array; public function summarize(array $messages): string; public function translate(string $text, string $locale): string; public function draft(array $context): array; }
