# v4.8.0 — Scheduler, Queue, Webhook and Lifecycle Reliability

This pass registers every recurring ecommerce command with Laravel's scheduler, restores explicit tenant context in queued work, separates payment-webhook receipt from processing, adds retry/dead-letter behavior, provider circuit breakers, lifecycle state management, and health visibility. Operational and financial data remain preserved when the extension is disabled or uninstalled.

## Reliability boundaries

- Scheduled jobs use `withoutOverlapping()` and `onOneServer()`.
- Payment webhooks are signature-verified and durably stored before a `202` acknowledgement.
- Processing occurs on `chatbot-ecommerce-payment-webhooks` with deterministic exponential backoff.
- Exhausted webhook events are dead-lettered rather than silently discarded.
- Marketplace read/write jobs restore chatbot and actor context.
- Marketplace provider calls use tenant-scoped circuit breakers.
- Disable and uninstall are fail-safe and preserve commerce records by default.

# v4.7.0 — Credential, Authorization and Tenant Hardening

This security-first pass migrates Shopify and WooCommerce secrets from plaintext chatbot columns into encrypted, hidden credential records; adds rotation, revocation and connection testing; enforces strict chatbot ownership across global administration queries; and requires signed, expiring, capability-scoped authority tokens for public shopping sessions. Public tokens cannot bind customer identities unless issued by the authenticated chatbot owner.

## Pass 18 highlights

- Encrypted Shopify and WooCommerce credential storage with redacted API summaries
- Safe migration and clearing of legacy plaintext credential columns
- Credential rotation, revocation and connection-test APIs
- Registered extension policy and route-level commerce permissions
- Strict owner and tenant scoping for pricing, tax, shipping, inventory, payments, fulfilment and rental/hire
- HMAC-SHA256 public session tokens bound to chatbot, session, origin, expiry and capabilities
- Server-generated public session IDs and signed customer-identity binding
- Cross-chatbot product, stock, payment, BNPL and rental record rejection
- Fail-closed behavior when security secrets or tenant context are absent

---

# v4.6.0 — Marketplace Inventory Conflict Resolution

This additive pass makes internal reservation-aware inventory the default canonical source for marketplace stock. It adds explicit listing-to-variant mappings, per-channel allocation and buffer policies, conflict scan runs, oversell exposure, delayed-sync warnings, immutable conflict history, seller acknowledgement and ignore workflows, and approval-gated correction preparation through the existing marketplace write runtime. It never automatically executes a marketplace stock change.

## Pass 17 highlights

- Internal inventory, active reservations, commitments, damaged units and safety stock determine canonical availability
- Connection and listing-level allocation rules: mirror, percentage, fixed cap or percentage cap
- Per-channel buffers, minimum quantities, maximum quantities and tolerance
- Exact-SKU mapping discovery with mandatory seller verification before correction
- Critical oversell conflicts and warning-level undersell conflicts
- Stale marketplace observation detection
- Persistent scan runs, conflicts and immutable conflict events
- Seller acknowledgement, timed ignore and refresh workflows
- Safe correction preparation through version-checked marketplace write proposals
- Optional bounded `auto_prepare`; automatic execution is always disabled
- Dedicated `chatbot-ecommerce-inventory-reconciliation` queue and scan command

---

# v4.5.0 — Listing Intelligence & Compliance

This additive pass introduces canonical product content profiles, seller brand-voice profiles, evidence-backed claims, marketplace-specific listing composition, policy-risk scanning, rewrite previews, and safe publication through the existing marketplace write approval and rollback flow. It preserves every v4.4.0 capability.

## Pass 16 highlights

- Amazon titles, bullet points and descriptions
- eBay titles, descriptions and item specifics
- Etsy titles, descriptions, tags and attributes
- Generic marketplace listing composition
- Brand voice examples, preferred terms and forbidden terms
- Canonical product facts, compatibility, materials, dimensions and semantic tags
- Claim evidence validation
- Storefront-specific and provider-specific compliance rules
- Blocking findings, warnings and remediation guidance
- Rewrite previews that cannot bypass seller approval
- Source-hash checks and standard marketplace rollback safety

---

# v4.4.0 — Marketplace Bulk Operations

Adds bounded dry-run batches for marketplace listing, price, inventory, pause and resume actions. Every batch records selection filters, per-item before state, exact impact, conflicts, approval evidence, isolated execution results and guarded partial rollback. Bulk writes remain disabled until `CHATBOT_ECOMMERCE_MARKETPLACE_ALLOW_BULK_WRITES=true`.


## Version 4.3: Marketplace Write Safety

Marketplace Write Safety adds single-listing seller mutations without weakening the existing read-only customer marketplace mode.

### Supported seller writes

- Update normalized listing title, description, or attributes
- Update one listing price in integer minor currency units
- Update one listing inventory quantity
- Pause one listing
- Resume one listing

Every action is prepared first and stores its source hash, before state, proposed changes, conflict analysis, seller identity, idempotency key, provider evidence, and rollback state. Bulk publishing, bulk repricing, marketplace refunds, order cancellation, fulfilment updates, and marketplace checkout remain excluded.

Marketplace connections are read-only after upgrade. A seller must explicitly enable `write_enabled` on a connection, and the installation must configure `CHATBOT_ECOMMERCE_MARKETPLACE_WRITE_APPROVAL_SECRET`. The default mode is `approval_only`; raw marketplace credentials remain prohibited.

### Approval and rollback

- Expiring HMAC-signed one-time approval tokens
- Immediate provider refresh before execution
- Source-hash mismatch blocking
- Internal inventory oversell detection
- Per-connection write rate limits
- Dedicated signed `/v1/marketplace/write` gateway boundary
- Provider request IDs and attempt history
- Rollback only when the listing still matches the state created by the original write

### Marketplace write APIs

```text
GET  /api/v3/chatbot/ecommerce/{chatbot}/marketplace-write-proposals
POST /api/v3/chatbot/ecommerce/{chatbot}/marketplace-connections/{connection}/listings/{listing}/marketplace-write-proposals
POST /api/v3/chatbot/ecommerce/{chatbot}/marketplace-write-proposals/{proposal}/approve
POST /api/v3/chatbot/ecommerce/{chatbot}/marketplace-write-proposals/{proposal}/execute
POST /api/v3/chatbot/ecommerce/{chatbot}/marketplace-write-proposals/{proposal}/rollback
```



## Version 4.2: Marketplace Read Mode

Version 4.2 introduced a provider-neutral, read-only marketplace layer for Amazon, eBay, Etsy, and future providers while preserving the standalone `chatbot-ecommerce` package.

### Customer marketplace-assisted shopping

- Search active marketplace connections from the Customer Shopping Assistant or Customer Communications Agent
- Progressive states: queued, searching, partial, completed, failed
- Five-minute canonical search cache by chatbot, query, filters, and provider set
- Normalized product cards with numbered clickable text fallbacks
- Listing price, currency, availability, seller, shipping, BNPL summary, image, and marketplace handoff URL
- Marketplace checkout remains authoritative; the extension does not bypass marketplace payment rules

### Seller read operations

- Configure Amazon, eBay, Etsy, or generic read connections per chatbot
- Store only a credential-vault reference plus encrypted non-secret configuration
- Import external order snapshots with cursor-based incremental reads
- Idempotent orders keyed by connection and external order ID
- Immutable source hashes, imported line snapshots, and auditable sync runs
- Imported marketplace orders remain external snapshots until a seller deliberately reconciles them; they do not silently become native orders

### Read-only safety boundary

The Version 4.2 read boundary supports `search_listings`, `get_listing`, `get_inventory`, and `import_orders`. It intentionally excludes listing publication, repricing, stock mutation, cancellation, fulfilment updates, refunds, and marketplace checkout execution. Those write capabilities require a later approval, rollback, policy, and conflict-resolution pass.

### Marketplace APIs

```text
POST /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/marketplaces/searches
GET  /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/marketplaces/searches/{search}
GET  /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/marketplaces/listings/{provider}/{externalListingId}
GET  /api/v3/chatbot/ecommerce/{chatbot}/marketplace-connections
POST /api/v3/chatbot/ecommerce/{chatbot}/marketplace-connections
PATCH /api/v3/chatbot/ecommerce/{chatbot}/marketplace-connections/{connection}
POST /api/v3/chatbot/ecommerce/{chatbot}/marketplace-connections/{connection}/orders/import
GET  /api/v3/chatbot/ecommerce/{chatbot}/marketplace-orders
GET  /api/v3/chatbot/ecommerce/{chatbot}/marketplace-sync-runs
```
# Chatbot Ecommerce

Version 4.3.0 preserves the existing Shopify and WooCommerce APIs and extends the provider-neutral native commerce runtime with a generic rental and hire receivables facility.

## Native commerce runtime

- Internal product catalogue, categories and variants
- Persistent line-item carts with idempotent mutations
- Multi-location inventory, reservations and adjustment history
- Deterministic pricing, promotions and advanced coupons
- Immutable, hash-addressed pricing snapshots
- Native checkout sessions with approval safety
- Server-generated shipping quotes
- Partial fulfilments and tracking
- Provider-neutral, address-aware tax calculation
- Provider-neutral payment intents, authorisation, capture, refunds, signed webhooks and reconciliation
- Versioned `/api/v3/chatbot/ecommerce` APIs

## Native payment engine

The payment engine is separate from MagicAI subscription and plan billing. It serves business-to-customer checkout and rental/hire receivables only.

It includes:

- Provider-neutral `PaymentProvider` contract
- Internal/manual provider for cash, PayID, bank transfer and external hosted payment flows
- Payment intents for native checkout and rental/hire payment requests
- Pending, requires-action, authorised, partially captured, captured, partially refunded, refunded, failed, cancelled, expired and disputed states
- Idempotent authorisation, capture, cancellation, refund and reconciliation operations
- Partial capture support
- Full and partial refunds
- Signed, timestamped webhook verification
- Durable webhook deduplication and retry-safe processing
- Provider references, hosted payment URLs and customer instructions
- Checkout completion after confirmed full capture
- Rental/hire payment confirmation and allocation after confirmed full capture
- Rental/hire ledger debit adjustments for refunds
- Payment-intent expiry and cleanup command
- Health checks for expired intents, amount mismatches and unprocessed webhooks

The built-in internal provider does not collect raw card or bank credentials. Card and direct-debit methods require a hosted payment URL supplied by an external gateway integration. Concrete Stripe, PayPal, Square or bank-debit adapters can implement the same provider contract later without changing checkout or rental/hire records.

### Payment APIs

Customer-facing endpoints can create and inspect payment intents for approved checkouts and token-authorised rental/hire payment requests. Authenticated administrative endpoints support authorisation, capture, cancellation, refunds and reconciliation. Providers post signed events to:

```text
POST /api/v3/chatbot/ecommerce/payment-webhooks/{provider}
```

Internal-provider webhook requests use `X-Payment-Timestamp` and `X-Payment-Signature`. The signature is an HMAC-SHA256 value over `timestamp.payload` and is rejected outside the configured replay window.

### Payment command

```text
php artisan chatbot-ecommerce:payments-expire
```

## Rental and hire receivables

The rental/hire facility is a generic payment and ledger subsystem. It supports residential or commercial rent, equipment hire, vehicle hire, storage, recurring leases and other scheduled obligations without turning the extension into a property-management or asset-management application.

It includes:

- Rental or hire accounts
- Token-scoped customer access
- Agreements and agreement subjects
- Weekly, fortnightly, monthly, daily, custom and one-time billing
- Effective-dated rate changes
- Automatic recurring charge generation
- Exact charge periods and due dates
- Partial payments
- Multiple payments against one charge
- One payment allocated across multiple charges
- Automatic oldest-balance allocation
- Manual payment allocation
- Overpayments retained as account credit
- Arrears calculations
- Debit and credit adjustments
- Payment reversals
- Immutable double-sided ledger entries
- Versioned, hash-addressed receipts
- Configurable bank transfer, PayID, cash or external payment instructions
- Idempotent payment requests and operations
- Expiring pending payment requests
- Charge generation and payment-expiry console commands

### Financial lifecycle

```text
Account
→ Agreement
→ Effective rate
→ Charge period
→ Payment request
→ Payment confirmation
→ Allocation
→ Receipt
→ Ledger and balance
```

The facility does not model rental bonds, inspections, maintenance, tenancy notices, evictions, asset dispatch or legal enforcement. Those remain separate domains. Card and direct-debit collection require a concrete gateway adapter or hosted payment URL. The shared payment engine can create and track those intents, process signed gateway events, reconcile captures, allocate rental/hire payments and issue refunds without storing raw payment credentials.

## Rental/hire customer APIs

These endpoints require a valid `X-Rental-Access-Token` issued when the account is created or rotated.

```text
GET  /api/v3/chatbot/ecommerce/{chatbot}/rental-hire/accounts/{account}/summary
GET  /api/v3/chatbot/ecommerce/{chatbot}/rental-hire/accounts/{account}/ledger
POST /api/v3/chatbot/ecommerce/{chatbot}/rental-hire/accounts/{account}/payment-requests
GET  /api/v3/chatbot/ecommerce/{chatbot}/rental-hire/accounts/{account}/receipts/{receipt}
```

## Rental/hire administration APIs

Authenticated administrative APIs support:

- Account creation and token rotation
- Agreement creation and lifecycle updates
- Effective-dated rate changes
- Charge generation
- Account summaries and ledgers
- Payment requests
- Manual or externally confirmed payments
- Automatic or explicit allocations
- Payment reversals
- Debit and credit adjustments
- Receipt retrieval

All monetary amounts use integer minor units. Existing charges, ledger entries and receipts are never silently rewritten.

## Console commands

```text
php artisan chatbot-ecommerce:rental-hire-generate-charges
php artisan chatbot-ecommerce:rental-hire-expire-payments
```

## Compatibility

Existing `api/v2/chatbot` Shopify and WooCommerce routes remain available. All new migrations are additive. Historical commerce, tax, rental, hire and payment records are preserved on rollback and uninstall by default.

## Two shopping modes

The extension preserves two distinct shopping modes:

1. **Native conversational shopping** — the internal catalogue, cart, pricing, shipping, tax, checkout, payment and BNPL runtimes can complete a merchant-owned transaction.
2. **Marketplace-assisted shopping** — the chatbot may search, compare and recommend products from marketplaces, but marketplace-owned checkout, payment and BNPL options remain authoritative and the customer is handed off to that marketplace.

The marketplace-assisted mode never creates a native BNPL credit transaction for an Amazon, eBay, Etsy or similar marketplace checkout.

## BNPL payment orchestration

The BNPL runtime supports approved third-party credit providers without making this extension a credit provider. It includes:

- Chatbot-scoped BNPL provider profiles
- Provider types for Afterpay/Clearpay, Klarna, Zip, Affirm, PayPal Pay Later and generic hosted adapters
- Native checkout and rental/hire scopes
- Preliminary amount, currency, country and scope eligibility
- Minimum and maximum transaction amounts
- Configurable instalment counts and intervals
- Exact minor-unit instalment schedules with deterministic rounding
- Provider licence and disclosure requirements
- Terms, privacy, hardship and complaints links
- Signed, expiring hosted-checkout state tokens
- Idempotent offer selection
- Payment-intent linkage
- Approval, capture, cancellation, decline, expiry and refund synchronisation
- Marketplace-managed display-only BNPL availability
- Customer acceptance of provider terms before selection
- Health checks for stale offers and incomplete provider disclosures

BNPL providers make the final credit and affordability decision. No internal BNPL lending is created by this extension, and raw consumer credit credentials are not stored.

Residential rent BNPL is disabled by default. Hire BNPL is enabled only when a chatbot owner configures an active provider profile with a licence reference, required customer disclosures, a hosted checkout adapter URL and a server-side state secret. Rent, lease and other recurring-account types require an explicit configuration change.

### BNPL APIs

```text
GET  /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/checkout/bnpl/offers
POST /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/checkout/bnpl/offers
GET  /api/v3/chatbot/ecommerce/{chatbot}/rental-hire/accounts/{account}/payments/{payment}/bnpl/offers
POST /api/v3/chatbot/ecommerce/{chatbot}/rental-hire/accounts/{account}/payments/{payment}/bnpl/offers
```

Authenticated provider administration:

```text
GET    /api/v3/chatbot/ecommerce/bnpl/providers
POST   /api/v3/chatbot/ecommerce/bnpl/providers
PATCH  /api/v3/chatbot/ecommerce/bnpl/providers/{profile}
DELETE /api/v3/chatbot/ecommerce/bnpl/providers/{profile}
GET    /api/v3/chatbot/ecommerce/bnpl/offers
```

### BNPL maintenance command

```text
php artisan chatbot-ecommerce:bnpl-expire
```

Concrete provider API adapters are not bundled in v3.9.0. Provider profiles connect to a server-controlled hosted gateway adapter URL; direct Afterpay, Klarna, Zip, Affirm, PayPal or Stripe API adapters can be added behind this boundary without changing cart, checkout, rental/hire or payment records.


## Slice 1: conversational commerce operating layer

### Customer Commerce Assistant

The native catalogue now exposes approval-aware AI tools for product search, exact product selection, comparison, cart inspection, prepared cart changes, coupons, checkout preparation, order tracking and return requests. The runtime uses three authority levels:

- **Inform** — read, search, compare and explain.
- **Prepare** — create an exact proposed action and one-time approval card.
- **Execute** — perform only the previously approved action.

Every cart-changing tool queries the persistent Conversational Context Stack before it acts. The stack records the active product result set, selected product and variant, last query and filters, short-term preferences, compressed summary, cart link and pending actions. Ambiguous phrases such as “the red one” are rejected rather than guessed unless the context resolves to one exact record.

Structured product, comparison, cart, checkout, order and return cards include numbered clickable text fallbacks for SMS and channels without interactive components. Product search uses a configurable five-minute cache and returns progressive status stages so clients can render immediate progress.

### Safety and observability

- Per-order and daily **Budget Locks** can be scoped to a chatbot, customer identity or shopping session.
- Provider failures pass through a configurable **Error Lexicon** that returns plain-English remediation while logging only a hash of sensitive raw messages.
- Completed native checkouts materialise immutable orders automatically after confirmed payment capture.
- Orders retain item, customer, address, pricing, tax and payment snapshots.
- Return requests validate remaining returnable quantities and support refund, exchange or store-credit resolutions.

### Seller Commerce Steward

The Seller Commerce Steward remains a separate permission mode sharing the same commerce core. Marketplace write adapters are intentionally deferred, but the extension now includes an append-only Commerce Action Journal. Native seller mutations can preserve before and after state and support guarded one-click **Rollback** when the subject has not changed since execution. This establishes rollback safety before Amazon, eBay, Etsy and other bulk-write adapters are introduced.

### Slice 1 APIs

```text
GET   /api/v3/chatbot/ecommerce/commerce/tools
GET   /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/commerce/context
PATCH /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/commerce/context
POST  /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/commerce/tools/execute
GET   /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/orders
GET   /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/orders/{order}
POST  /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/checkouts/{checkout}/orders
POST  /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/orders/{order}/returns
```

Authenticated controls include budget-lock configuration, order/return transitions and action rollback.

The initial public agent-integration contract is published at `openapi/conversational-commerce-v1.yaml` so enterprise and external-agent integrations can target a stable request and approval envelope before later marketplace adapters are released.

## Version 4.1: three-role commerce

The ecommerce extension now exposes three governed roles over one shared commerce core.

### Customer Shopping Assistant

The **Customer Shopping Assistant** is the conversational storefront. It searches and compares products, prepares carts, applies coupons, calculates delivery and tax, presents payment or BNPL options, tracks orders, and prepares returns. Consequential actions continue to use Inform → Prepare → approval → Execute.

### Seller Commerce Steward

The **Seller Commerce Steward** is the authenticated operator role for catalogue, pricing, inventory, orders, fulfilment, marketplace operations, analytics, and reversible seller actions. Customer actors cannot request or inherit seller tools.

### Customer Communications Agent

The **Customer Communications Agent** handles seller-to-customer and customer-to-seller commerce conversations without duplicating channel transports or inbox infrastructure. Existing chatbot, email, SMS, WhatsApp, Telegram, Messenger, Instagram, marketplace-message, or internal-inbox systems may pass messages into the ecommerce support API.

It supports:

- Pre-sale product, stock, delivery, coupon, BNPL, and compatibility questions
- Checkout, address, payment, and inventory problems
- Order status, tracking, delays, missing or damaged items
- Returns, exchanges, refunds, warranties, complaints, and reordering
- Persistent channel-agnostic threads and immutable message records
- External message ID deduplication
- Factual context from carts, orders, payments, fulfilments, and returns
- Plain-English response cards with text fallbacks
- Human handoff summaries with urgency and recommended next actions

### Customer communications authority matrix

- **Answer automatically** — high-confidence product facts, stock, policies, and verified order status.
- **Prepare automatically** — cart recovery, return, exchange, replacement, or address-correction proposals.
- **Execute within limits** — only explicitly configured low-value actions for verified customers.
- **Require approval** — refunds, cancellations, replacements, credits, discounts, and unknown actions outside configured limits.
- **Human-only** — fraud decisions, legal threats, chargebacks, safety incidents, and regulatory complaints.

Automatic financial limits default to zero. Auto-replies default to disabled. Sellers must explicitly configure policies per chatbot.

### Three-role APIs

```text
GET  /api/v3/chatbot/ecommerce/commerce/roles
POST /api/v3/chatbot/ecommerce/commerce/roles/resolve
GET  /api/v3/chatbot/ecommerce/support/tools
POST /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/support/messages
GET  /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/support/threads/{thread}/context
POST /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/support/threads/{thread}/draft
POST /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/support/threads/{thread}/actions
POST /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/support/threads/{thread}/actions/{action}/execute
POST /api/v3/chatbot/ecommerce/{chatbot}/session/{sessionId}/support/threads/{thread}/escalations
```

Authenticated seller APIs manage support threads, policies, and escalation resolution. The extension stores no channel credentials and does not replace the parent chatbot or channel extensions.

## Version 4.9: unified orders, settlements and exception workbench

The seller workspace now projects native orders and authoritative external Shopify, WooCommerce, Amazon, eBay, Etsy and generic marketplace order snapshots into one tenant-scoped read model. External records remain external snapshots and are never silently converted into native orders.

Settlement entry identities are isolated by chatbot, provider, source/account scope and external entry ID. Workbench idempotency keys are bound to the exact order, action and normalized proposal; reusing a key for different work fails closed.

The workbench provides:

- Unified source, status, payment, fulfilment and customer views
- Immutable source-snapshot history
- Provider-neutral settlement entry imports
- Fees, taxes, shipping costs, refunds, chargebacks and payout reconciliation
- Expected-versus-reported net payout variance history
- Exception queues for invalid addresses, payment failures, fulfilment delays, stock conflicts, disputed returns and settlement variance
- Seller acknowledgement, assignment and resolution workflows
- Approval-required refund proposals
- Customer-contact drafts that remain inside the existing chatbot-agent and channel transport boundary
- Links between customer communication threads and the relevant unified order

Authenticated seller APIs:

```text
GET  /api/v3/chatbot/ecommerce/{chatbot}/unified-orders
GET  /api/v3/chatbot/ecommerce/{chatbot}/unified-orders/{unifiedOrder}
POST /api/v3/chatbot/ecommerce/{chatbot}/unified-orders/sync
POST /api/v3/chatbot/ecommerce/{chatbot}/unified-orders/import-external
POST /api/v3/chatbot/ecommerce/{chatbot}/unified-orders/{unifiedOrder}/settlements/import
POST /api/v3/chatbot/ecommerce/{chatbot}/unified-orders/{unifiedOrder}/reconcile
GET  /api/v3/chatbot/ecommerce/{chatbot}/order-exceptions
POST /api/v3/chatbot/ecommerce/{chatbot}/order-exceptions/{exception}/acknowledge
POST /api/v3/chatbot/ecommerce/{chatbot}/order-exceptions/{exception}/assign
POST /api/v3/chatbot/ecommerce/{chatbot}/order-exceptions/{exception}/resolve
POST /api/v3/chatbot/ecommerce/{chatbot}/unified-orders/{unifiedOrder}/refund-proposals
POST /api/v3/chatbot/ecommerce/{chatbot}/unified-orders/{unifiedOrder}/customer-contact-proposals
POST /api/v3/chatbot/ecommerce/{chatbot}/unified-orders/{unifiedOrder}/support/threads/{thread}/link
```
