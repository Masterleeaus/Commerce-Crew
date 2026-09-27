# Marketplace Write Safety Implementation Plan

**Goal:** Add single-listing marketplace writes without allowing unapproved bulk changes or stale-state overwrites.

## Safety boundary

- Writes are limited to listing content, price, inventory quantity, pause, and resume.
- Marketplace refunds, order cancellation, fulfilment mutation, checkout, and account configuration remain excluded.
- Every write starts from an observed listing snapshot and an expected SHA-256 source hash.
- A fresh provider read is required immediately before execution and rollback.
- Seller approval uses an expiring, one-time, HMAC-signed token bound to the action hash, chatbot, seller, and proposal.
- Inventory writes compare the requested marketplace quantity with mapped internal stock and block overselling by default.
- Provider write requests use a separate signed gateway endpoint, credential-vault references, idempotency keys, and rate-limit state.
- Rollback is allowed only while the current provider state still equals the state created by the original write.
- Bulk writes are deliberately excluded from this pass.

## Components

1. Provider-neutral write contracts and Amazon, eBay, Etsy, and generic gateway adapters.
2. Write proposal, attempt, and rate-limit persistence.
3. Patch normalization and operation capability declarations.
4. Source-version and inventory conflict detector.
5. Prepare, approve, execute, and rollback runtime.
6. Authenticated seller APIs and role-scoped AI tools.
7. Dedicated optional marketplace-write queue.
8. Blueprint manifest, OpenAPI, health, documentation, and regression checks.
