# Three-Role Commerce Orchestration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend `chatbot-ecommerce` as one standalone extension with three governed roles: Customer Shopping Assistant, Seller Commerce Steward, and Customer Communications Agent.

**Architecture:** Add a role router and a channel-agnostic customer-communications domain inside the ecommerce extension. Reuse existing catalogue, cart, order, payment, fulfilment, return, approval, and audit records; do not duplicate channel transports or inbox systems. Customer communications persist thread/message/action/escalation records and expose safe tool definitions plus approval-gated actions.

**Tech Stack:** PHP 8.2+, Laravel service provider/routes/Eloquent/migrations, standalone PHP primitive and contract checks, JSON manifests, OpenAPI 3.1.

## Global Constraints

- Keep the extension slug and namespace `chatbot-ecommerce` / `App\\Extensions\\ChatbotEcommerce`.
- Produce only the standalone ecommerce extension archive; do not rebuild the 12-extension suite.
- Preserve all v4.0.0 files and migrations.
- Store no channel credentials and do not implement duplicate WhatsApp, Telegram, Messenger, Instagram, email, SMS, or inbox transports.
- Role access must fail closed and customer actors must never receive seller tools.
- Refunds, cancellations, replacements, price concessions, and seller mutations require policy evaluation and approval unless explicitly inside configured limits.
- Customer-facing replies must derive factual order/payment/fulfilment/return state from stored records rather than model memory.
- External message IDs must be idempotent per chatbot/channel.

---

### Task 1: Three-role primitives and role routing

**Files:**
- Create: `System/Support/CommerceRole.php`
- Create: `System/Support/CommerceRoleRouter.php`
- Create: `System/Support/CustomerCommunicationAuthority.php`
- Test: `tests/run_three_role_primitives.php`

**Interfaces:**
- Produces: `CommerceRole::SHOPPING_ASSISTANT`, `SELLER_STEWARD`, `CUSTOMER_COMMUNICATIONS`; `CommerceRoleRouter::resolve(array): string`; `CustomerCommunicationAuthority::decision(string,array): array`.

- [ ] Write failing primitive tests for role resolution, fail-closed seller access, auto-answer, approval-required, and human-only decisions.
- [ ] Run the primitive script and verify it fails because support classes are absent.
- [ ] Implement minimal deterministic support classes.
- [ ] Re-run and confirm all primitive checks pass.

### Task 2: Communication persistence

**Files:**
- Create: `database/migrations/2026_08_03_060013_three_role_commerce_communications.php`
- Create: `System/Models/CommerceCommunicationThread.php`
- Create: `System/Models/CommerceCommunicationMessage.php`
- Create: `System/Models/CommerceCommunicationPolicy.php`
- Create: `System/Models/CommerceCommunicationAction.php`
- Create: `System/Models/CommerceEscalation.php`

**Interfaces:**
- Produces tables for channel-agnostic threads, immutable message records, scoped policies, approval actions, and handoff/escalation records.

- [ ] Add source-contract checks for all files and required columns/indexes.
- [ ] Run contract script and verify failure.
- [ ] Add repeat-safe additive migration and focused Eloquent models with UUID generation and casts.
- [ ] Re-run contract checks.

### Task 3: Customer communications context and triage runtime

**Files:**
- Create: `System/Services/CommerceRoleRuntime.php`
- Create: `System/Services/CustomerCommunicationContextRuntime.php`
- Create: `System/Services/CustomerCommunicationRuntime.php`
- Create: `System/Services/CustomerCommunicationCardRuntime.php`

**Interfaces:**
- Consumes existing products, carts, orders, payments, fulfilments, returns, context, and action approval services.
- Produces `toolDefinitions()`, `ingestMessage()`, `threadContext()`, `draftReply()`, `prepareAction()`, `executeApprovedAction()`, and `handoff()`.

- [ ] Add contract checks proving role-scoped tools, factual-context lookup, message deduplication, authority evaluation, and escalation paths.
- [ ] Implement context composition and deterministic triage fallback.
- [ ] Implement safe response cards with numbered text fallbacks.
- [ ] Implement approval-gated support actions and human handoff summaries.
- [ ] Run primitive and contract checks.

### Task 4: APIs and provider registration

**Files:**
- Create: `System/Http/Controllers/Api/CommerceRoleApiController.php`
- Create: `System/Http/Controllers/Api/CustomerCommunicationApiController.php`
- Create: `System/Http/Controllers/Api/CustomerCommunicationAdminApiController.php`
- Create: `System/Http/Resources/Api/CommerceCommunicationThreadResource.php`
- Modify: `System/ChatbotEcommerceServiceProvider.php`

**Interfaces:**
- Produces role-definition/resolution endpoints, support-message ingestion, context, draft reply, approved action, escalation, thread list, and policy administration endpoints.

- [ ] Add route contract tests before registration.
- [ ] Register public/session-scoped support routes and authenticated seller policy/thread routes.
- [ ] Validate payload size, channel values, confidence, action types, and idempotency keys.
- [ ] Run route contracts and PHP lint.

### Task 5: Tool bridge, config, manifest, OpenAPI, and documentation

**Files:**
- Modify: `System/Services/EcommerceToolService.php`
- Modify: `config/platform-quality.php`
- Modify: `extension.manifest.json`
- Modify: `extension.json`
- Modify: `index.json`
- Modify: `upgrade.php`
- Modify: `README.md`
- Modify: `openapi/conversational-commerce-v1.yaml`
- Create: `tests/run_three_role_contract_checks.php`

**Interfaces:**
- Exposes role-scoped tool definitions and v4.1.0 metadata without exposing seller tools to customer roles.

- [ ] Add failing metadata/manifest/OpenAPI/source contracts.
- [ ] Add three-role capability flags, owned tables, route declarations, and data-retention declarations.
- [ ] Document the three roles, shared core, authority matrix, channel boundaries, handoff, and rollback relationship.
- [ ] Update OpenAPI with role, thread, message, draft, action, escalation, and policy schemas.
- [ ] Update all version metadata to `4.1.0`.
- [ ] Run JSON/YAML parsing and all contract checks.

### Task 6: Full regression and standalone packaging

**Files:**
- Create output: `/mnt/data/chatbot-ecommerce-upgraded-v4.1.0.zip`
- Create output: `/mnt/data/chatbot-ecommerce-upgraded-pass12.sha256`

- [ ] Lint every packaged PHP file.
- [ ] Run every `tests/run_*.php` script.
- [ ] Run supplied Blueprint validator and JSON schema validation.
- [ ] Parse OpenAPI YAML.
- [ ] Verify v4.0.0 files are preserved unless intentionally modified.
- [ ] Build standalone ZIP only.
- [ ] Extract the ZIP into a fresh directory and repeat lint, tests, metadata, OpenAPI, and archive integrity checks.
- [ ] Generate SHA-256 checksum file.
