# Commerce Crew Architecture

Commerce Crew separates interaction, commerce execution, channel connectivity and operational control. Customer and merchant conversations enter through the core interaction layer; specialist modules provide channel and domain capabilities; the Commerce Engine turns approved intent into commerce operations.

## Design principles

1. **Conversation is an operating surface, not a bolt-on widget.**
2. **Roles are explicit.** Customer shopping, seller operations and customer communications have distinct responsibilities.
3. **Writes are governed.** Marketplace and consequential actions can require approval, preserve evidence and support rollback.
4. **Tenants are isolated.** Public sessions and credentials are handled with fail-closed boundaries.
5. **External systems are treated as unreliable.** Queues, retries, dead letters and circuit breakers protect workflows.
6. **Native and external commerce converge in one workbench.** Orders, settlements, exceptions and communications can be coordinated without pretending every provider behaves identically.

## Layers

- **Interaction:** conversation, voice and channel adapters.
- **Intelligence:** customer shopping, seller stewardship, support and communications roles.
- **Commerce:** catalogue, inventory, cart, checkout, tax, shipping, fulfilment, payments, BNPL, orders and returns.
- **Marketplace:** read/search/import, governed writes, bulk operations, listing intelligence and inventory reconciliation.
- **Control:** authority, approvals, credentials, tenant boundaries, evidence, rollback and human handoff.
- **Reliability:** queues, scheduling, retry/dead-letter handling, circuit breakers and lifecycle controls.
