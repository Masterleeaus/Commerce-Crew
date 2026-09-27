<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\CommerceCommunicationThread;
use App\Extensions\ChatbotEcommerce\System\Models\CommerceEscalation;

final class CustomerCommunicationCardRuntime
{
    /** @param array<string,mixed> $draft @return array<string,mixed> */
    public function reply(CommerceCommunicationThread $thread, array $draft): array
    {
        $text = trim((string) ($draft['text'] ?? ''));

        return [
            'schema' => [
                'type' => 'commerce.customer_communication_reply',
                'version' => 1,
                'thread_uuid' => $thread->uuid,
                'channel' => $thread->channel,
                'intent' => $draft['intent'] ?? $thread->intent,
                'authority_level' => $draft['authority']['level'] ?? 'require_approval',
                'text' => $text,
                'actions' => $draft['actions'] ?? [],
            ],
            'fallback_text' => $text,
        ];
    }

    /** @return array<string,mixed> */
    public function escalation(CommerceEscalation $escalation): array
    {
        $fallback = sprintf('Conversation escalated (%s). %s', $escalation->severity, $escalation->summary);

        return [
            'schema' => [
                'type' => 'commerce.customer_communication_handoff',
                'version' => 1,
                'escalation_uuid' => $escalation->uuid,
                'reason' => $escalation->reason,
                'severity' => $escalation->severity,
                'status' => $escalation->status,
                'summary' => $escalation->summary,
                'recommended_action' => $escalation->recommended_action,
            ],
            'fallback_text' => $fallback,
        ];
    }
}
