<?php

namespace App\Extensions\Chatbot\System\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthCheckService
{
    public function check(): array
    {
        $requiredTables = [
            'ext_chatbot_conversations',
            'ext_chatbot_histories',
            'ext_chatbot_participants',
            'ext_chatbot_attachments',
            'ext_chatbot_workflow_runs',
            'ext_chatbot_structured_actions',
            'ext_chatbot_delivery_receipts',
            'ext_chatbot_drafts',
            'ext_chatbot_presence',
            'ext_chatbot_event_outbox',
            'ext_chatbot_stream_events',
        ];

        $checks = [
            'database' => false,
            'runtime_enabled' => (bool) config('chatbot.runtime.enabled', true),
            'tables' => [],
            'columns' => [],
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
            foreach ($requiredTables as $table) {
                $checks['tables'][$table] = Schema::hasTable($table);
            }
            $checks['columns']['history_idempotency_key'] = Schema::hasColumn('ext_chatbot_histories', 'idempotency_key');
            $checks['columns']['outbox_locked_at'] = Schema::hasColumn('ext_chatbot_event_outbox', 'locked_at');
            $checks['columns']['attachment_sha256'] = Schema::hasColumn('ext_chatbot_attachments', 'sha256');
            $checks['columns']['attachment_status'] = Schema::hasColumn('ext_chatbot_attachments', 'status');
            $checks['columns']['workflow_conversation_id'] = Schema::hasColumn('ext_chatbot_workflow_runs', 'conversation_id');
            $checks['columns']['workflow_idempotency_key'] = Schema::hasColumn('ext_chatbot_workflow_runs', 'idempotency_key');
            $checks['columns']['workflow_lock_token'] = Schema::hasColumn('ext_chatbot_workflow_runs', 'lock_token');
            $checks['columns']['workflow_attempt'] = Schema::hasColumn('ext_chatbot_workflow_runs', 'attempt');
            $checks['columns']['stream_event_uuid'] = Schema::hasColumn('ext_chatbot_stream_events', 'event_uuid');
            $checks['columns']['stream_event_conversation_id'] = Schema::hasColumn('ext_chatbot_stream_events', 'conversation_id');
        } catch (\Throwable $exception) {
            $checks['error'] = $exception->getMessage();
        }

        $healthy = $checks['database']
            && $checks['runtime_enabled']
            && ! in_array(false, $checks['tables'], true)
            && ! in_array(false, $checks['columns'], true);

        return [
            'ok' => $healthy,
            'extension' => 'chatbot',
            'version' => '7.7.0',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
