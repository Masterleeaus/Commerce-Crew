# Support

Telegram: https://t.me/heew_support

## Modern runtime upgrade

This release preserves the existing extension identity and compatibility APIs while adding versioned migrations, runtime services, health checks, logging, metrics, REST-ready services, webhook/event foundations, permissions/feature-flag support, safe data-preserving uninstall defaults, and automated metadata tests.

### Upgrade safety

Run the normal MagicAI extension migration workflow. Existing tables and columns are preserved; new migrations use additive changes and guarded table/column creation. Uninstall behavior defaults to preserving operational data.

### Health and operations

The extension includes `config/platform-quality.php`, structured extension logging, metric events, and a health service or shared runtime health endpoint where applicable.

## 7.1.0 runtime hardening

- Atomic conversation merge now moves messages, attachments, structured actions and participants while preventing self-merges, cross-chatbot merges and merge cycles.
- Persistent per-user drafts, conversation presence, delivery receipts and a transactional event outbox were added through additive migrations.
- Runtime endpoints now use configurable authenticated middleware and policy authorization.
- Search validates filters, caps page size and searches message content as well as names and summaries.
- Delivery transitions persist receipt history and durable integration events.
- Health checks now verify every shared-runtime table.

### Runtime environment controls

```env
CHATBOT_RUNTIME_ENABLED=true
CHATBOT_RUNTIME_MIDDLEWARE=api,auth
CHATBOT_RUNTIME_MAX_PAGE_SIZE=100
CHATBOT_PRESENCE_TTL=120
CHATBOT_EVENT_RETENTION_DAYS=30
```

## 7.2.0 delivery reliability

- Idempotent runtime message creation accepts `Idempotency-Key` or `idempotency_key` and returns the existing message on safe retries.
- Delivery state transitions are monotonic across queued, sent, delivered and read states; failed messages must be explicitly retried.
- Sent and failed timestamps were added with additive guarded migrations.
- The durable event outbox now supports atomic claiming, worker locks, stale-lock recovery, exponential retry backoff, terminal failure and retention pruning.
- Signed webhook publishing uses a stable JSON body, event UUID idempotency header and configurable timeout.
- Added `chatbot:runtime-outbox` for publishing and maintenance.

```env
CHATBOT_OUTBOX_MAX_ATTEMPTS=8
CHATBOT_OUTBOX_BASE_BACKOFF=15
CHATBOT_OUTBOX_MAX_BACKOFF=3600
CHATBOT_OUTBOX_LOCK_TIMEOUT=300
CHATBOT_WEBHOOK_TIMEOUT=10
```

Recommended scheduler entry:

```php
Schedule::command('chatbot:runtime-outbox --release-stale')->everyMinute()->withoutOverlapping();
Schedule::command('chatbot:runtime-outbox --prune')->daily();
```

## 7.3.0 attachment hardening

The shared runtime now provides authenticated attachment upload, download, and deletion endpoints. Uploads are size-limited, MIME-allowlisted, dangerous extensions are blocked, filenames are normalised, content hashes are recorded, duplicate uploads within a conversation are reused, and records support quarantine and soft deletion. Existing attachment rows remain compatible through an additive migration.


## 7.4.0 participant runtime hardening

- Authenticated participant listing and upsert APIs.
- Safe participant removal scoped to its conversation.
- Per-participant read cursors and unread counts.
- Last-seen and last-read timestamps.
- Additive migration `2026_07_22_000005`.

## 7.5.0 structured action hardening

The shared runtime now exposes authenticated, conversation-scoped structured action APIs for AI approval cards, task cards, and other typed actions. Actions support idempotent creation, expiry, approval, rejection, execution, cancellation, result capture, and guarded state transitions. Existing structured action rows remain compatible through an additive migration.

## 7.6.0 workflow runtime hardening

The shared workflow runtime now supports conversation-scoped workflow runs, idempotent dispatch, guarded lifecycle transitions, completion and failure output, bounded retries with backoff, cancellation, stale-lock recovery, and atomic worker claiming. Authenticated v3 runtime endpoints expose dispatch, status, resume, completion, failure, retry, and cancellation without removing prior runtime contracts.

## 7.7.0 streaming reliability

- Persists bounded, expiring conversation stream events while retaining the existing `chatbot.stream` event API.
- Adds authenticated cursor replay at `GET /api/v3/chatbot/runtime/conversations/{conversation}/events`.
- Supports event filtering, replay limits, reconnect cursors, retention pruning, and migration-safe fallback before the new table exists.
