# Marketplace Read Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add read-only Amazon, eBay, Etsy, and generic marketplace discovery and order-import foundations to the standalone chatbot-ecommerce extension.

**Architecture:** Add a provider-neutral read contract and gateway transport so provider signing and secrets stay outside ordinary extension records. Persist normalized searches, listing snapshots, imported order snapshots, cursors, and sync runs; expose customer search and seller-owned connection/import APIs; keep all marketplace writes disabled.

**Tech Stack:** PHP 8.2+, Laravel service provider, Eloquent, queues, JSON manifests, OpenAPI 3.1, standalone PHP contract tests.

## Global Constraints

- Preserve every v4.1.0 file and database table.
- Keep the package standalone as `chatbot-ecommerce`.
- Do not store raw marketplace access tokens or marketplace passwords.
- Marketplace providers are read-only in this pass.
- Customer search may only use active connections assigned to the current chatbot.
- Seller endpoints must prove chatbot ownership.
- Imported marketplace orders remain immutable external snapshots and do not silently become native orders.

---

### Task 1: Provider contracts and normalization
- [ ] Add marketplace capability, state, cache, normalization, provider, and transport primitives.
- [ ] Write and run failing primitive tests.
- [ ] Implement minimal code and rerun tests.

### Task 2: Persistence and models
- [ ] Add repeat-safe migration for connections, searches, results, listing snapshots, order snapshots, lines, cursors, and sync runs.
- [ ] Add Eloquent models with encrypted configuration casts and UUID generation.
- [ ] Add contract checks for keys, indexes, and credential redaction.

### Task 3: Read providers and transport
- [ ] Add gateway transport and Amazon/eBay/Etsy/generic read providers.
- [ ] Add registry with declared capabilities and no write operations.
- [ ] Add tests for provider operation mapping and normalized output.

### Task 4: Search, cards, and order import
- [ ] Add cached marketplace search runtime with progressive states and partial-provider failures.
- [ ] Add read-only marketplace order import with cursors, idempotent snapshots, and sync runs.
- [ ] Add marketplace result cards and numbered text fallbacks.

### Task 5: APIs, jobs, command, and AI tools
- [ ] Add customer search/result routes.
- [ ] Add seller-owned connection, import, order, and sync-run routes.
- [ ] Add queued search/import jobs and a seller-triggered import command.
- [ ] Expose marketplace search/get-listing tools to shopping and support roles; expose import/list-order tools only to the seller role.

### Task 6: Metadata, docs, and release verification
- [ ] Update config, health, manifest, OpenAPI, README, and all versions to 4.2.0.
- [ ] Run all historical and new tests.
- [ ] Package only the standalone ecommerce extension and verify the extracted ZIP.
