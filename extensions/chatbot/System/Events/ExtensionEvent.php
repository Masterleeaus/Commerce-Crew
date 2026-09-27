<?php
namespace App\Extensions\Chatbot\System\Events;
class ExtensionEvent{public function __construct(public readonly string$name,public readonly array$payload=[],public readonly string$extension='chatbot'){}}
