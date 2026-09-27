<?php
namespace App\Extensions\Chatbot\System\Events;
class ConversationMerged { public readonly int $targetId; public readonly int $sourceId; public function __construct(int $targetId, int $sourceId) { $this->targetId=$targetId; $this->sourceId=$sourceId; } }
