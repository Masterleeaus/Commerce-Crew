# Commerce Crew Commerce Engine

**Version 4.9.0**

The Commerce Engine is the transactional and marketplace operations core of Commerce Crew. It connects conversational intent to catalogue, inventory, cart, checkout, payments, orders, returns and marketplace workflows while keeping consequential actions inside explicit authority, approval and reliability boundaries.

## Capability surface

### Native commerce

- Product catalogue, categories and variants
- Multi-location inventory and reservations
- Deterministic pricing, promotions and coupons
- Persistent carts with idempotent mutations
- Checkout sessions with approval safety
- Shipping quotes and partial fulfilment
- Address-aware tax calculation
- Native orders and returns
- Rental/hire receivables

### Payments

- Provider-neutral payment intents
- Authorization, capture and partial capture
- Full and partial refunds
- Signed, timestamped webhooks
- Idempotent asynchronous event handling
- Settlement reconciliation
- Cash, PayID, bank transfer and hosted payment flows through the internal/manual provider
- BNPL provider profiles and hosted checkout orchestration

The built-in payment boundary does not collect raw card or bank credentials. External card, direct-debit and credit providers integrate behind provider contracts or hosted payment flows.

### Conversational commerce

Commerce capabilities can be exposed through customer shopping, seller operations and customer-support roles. The conversation layer can discover products, build carts, prepare checkout, explain failures, manage commerce context and hand work to a human when required.

### Marketplace operations

- Provider-neutral marketplace connections
- Marketplace search and product normalization
- External order import
- Listing and inventory reads
- Governed listing, price, inventory, pause and resume writes
- Approval tokens and source-state verification
- Bulk dry runs and bounded execution
- Partial rollback safeguards
- Rate-limit governance
- Listing intelligence and compliance checks
- Brand-voice profiles and evidence-backed claims
- Marketplace inventory reconciliation

Marketplace checkout and marketplace-owned payment rules remain authoritative for marketplace-assisted shopping.

### Inventory coordination

Internal reservation-aware inventory is the canonical basis for channel allocation. The engine supports:

- Channel buffers and allocation policies
- Listing-to-variant mappings
- Oversell and undersell detection
- Stale marketplace-state detection
- Conflict history and acknowledgement workflows
- Version-checked correction proposals
- Bounded automatic preparation
- Separately governed execution

### Unified order workbench

Native and external orders are normalized into a shared operational view without erasing source-system authority. The workbench supports:

- Source normalization
- Payment and settlement reconciliation
- Fulfilment monitoring
- Address, payment, stock, return and settlement exceptions
- Customer-contact preparation
- Approval-aware refund proposals

## Security and authority

- Encrypted commerce credentials
- Credential rotation and revocation
- Redacted API output
- Strict tenant and chatbot ownership checks
- HMAC-signed, expiring commerce sessions
- Capability-scoped public-session authority
- Least-privilege route permissions
- Fail-closed behavior when required authority or tenant context is absent

## Reliability

- Registered recurring jobs
- Tenant-aware queued work
- Durable payment-webhook receipt before acknowledgement
- Deterministic exponential retry backoff
- Dead-letter handling
- Provider circuit breakers
- Lifecycle-aware request serving
- Data-preserving disable and uninstall behavior

## API surface

Current commerce APIs are versioned under:

```text
/api/v3/chatbot/ecommerce
```

The engine also retains compatibility endpoints required by supported commerce integrations.

Representative workflow groups include:

```text
Catalogue / Inventory / Cart / Checkout
Payments / BNPL / Orders / Returns
Rental & Hire Receivables
Marketplace Search / Imports / Writes
Listing Intelligence / Inventory Reconciliation
Unified Order Workbench
```

## Operational commands

The engine includes console commands for scheduled commerce maintenance such as payment expiry, rental/hire charge generation, pending-payment expiry and marketplace reconciliation. Deployments register recurring operations through the host scheduler.

## Verification

The repository includes executable primitive and contract checks plus unit and feature tests across the engine's major boundaries:

- Native commerce foundations
- Catalogue and inventory
- Pricing, cart and checkout
- Tax and shipping
- Orders and returns
- Payments and webhook integrity
- BNPL and rental/hire
- Conversational commerce
- Marketplace reads and writes
- Bulk marketplace operations
- Listing intelligence
- Inventory conflict resolution
- Unified order workbench
- Credential and tenant hardening
- Session authority and permissions
- Retry, circuit-breaker and lifecycle behavior

## Compatibility

The Commerce Engine declares support for **PHP 8.2+**, **Laravel 10/11**, **MagicAI 10.91+** and **Commerce Crew Core 7.7.0+**.

Commerce records, financial records and operational history are preserved by default across lifecycle changes so disabling an extension does not silently destroy business data.
