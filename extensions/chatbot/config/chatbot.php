<?php

return [
    'version' => 1.0,
    'avatars' => [
        'avatar-1.png',
        'avatar-2.png',
        'avatar-3.png',
        'avatar-4.png',
        'avatar-5.png',
    ],
    'notification_enabled' => env('CHATBOT_NOTIFICATION_ENABLED', true),

    'attachments' => [
        'disk' => env('CHATBOT_ATTACHMENT_DISK', 'local'),
        'directory' => env('CHATBOT_ATTACHMENT_DIRECTORY', 'chatbot/attachments'),
        'max_size_kb' => (int) env('CHATBOT_ATTACHMENT_MAX_SIZE_KB', 20480),
        'allowed_mime_types' => array_values(array_filter(explode(',', env('CHATBOT_ATTACHMENT_MIME_TYPES', 'image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,text/csv,application/zip,audio/mpeg,audio/ogg,video/mp4')))),
        'blocked_extensions' => array_values(array_filter(explode(',', env('CHATBOT_ATTACHMENT_BLOCKED_EXTENSIONS', 'php,phtml,phar,exe,com,bat,cmd,sh,js,html,htm,svg')))),
    ],

    'runtime' => [
        'enabled' => env('CHATBOT_RUNTIME_ENABLED', true),
        'middleware' => array_values(array_filter(explode(',', env('CHATBOT_RUNTIME_MIDDLEWARE', 'api,auth')))),
        'max_page_size' => (int) env('CHATBOT_RUNTIME_MAX_PAGE_SIZE', 100),
        'presence_ttl_seconds' => (int) env('CHATBOT_PRESENCE_TTL', 120),
        'event_retention_days' => (int) env('CHATBOT_EVENT_RETENTION_DAYS', 30),
        'webhooks' => [],
        'streaming' => [
            'event_ttl_seconds' => (int) env('CHATBOT_STREAM_EVENT_TTL', 3600),
            'retention_hours' => (int) env('CHATBOT_STREAM_RETENTION_HOURS', 24),
            'max_replay_events' => (int) env('CHATBOT_STREAM_MAX_REPLAY_EVENTS', 250),
        ],
        'outbox' => [
            'max_attempts' => (int) env('CHATBOT_OUTBOX_MAX_ATTEMPTS', 8),
            'base_backoff_seconds' => (int) env('CHATBOT_OUTBOX_BASE_BACKOFF', 15),
            'max_backoff_seconds' => (int) env('CHATBOT_OUTBOX_MAX_BACKOFF', 3600),
            'lock_timeout_seconds' => (int) env('CHATBOT_OUTBOX_LOCK_TIMEOUT', 300),
            'webhook_timeout_seconds' => (int) env('CHATBOT_WEBHOOK_TIMEOUT', 10),
        ],
    ],
];
