# Commerce Crew

**A governed conversational commerce workforce for selling, supporting and operating across stores, marketplaces, messaging and voice.**

Commerce Crew is a modular commerce platform built around coordinated customer, seller and operational intelligence. Rather than adding a chatbot beside an existing store, Commerce Crew brings conversation directly into catalogue discovery, inventory, checkout, payments, orders, returns, marketplace operations, customer communications and support.

The repository combines a mature omnichannel conversation layer with the **Commerce Engine v4.9.0**, replacing the earlier v3.9.0 commerce module while retaining the wider extension suite.

## What Commerce Crew does

Commerce Crew supports the full path from a customer conversation to governed business action:

```text
Customers / Sellers / Operators
              |
              v
     Conversation + Voice
              |
              v
      Commerce Crew Core
              |
     +--------+---------+
     |        |         |
     v        v         v
 Shopping   Seller    Support
 Assistant  Steward   Workflows
     |        |         |
     +--------+---------+
              |
              v
        Commerce Engine
              |
  +-----------+------------+
  |           |            |
Catalogue   Orders     Marketplaces
Inventory   Payments   Channels
Checkout    Returns    Communications
  |           |            |
  +-----------+------------+
              |
              v
 Authority / Evidence / Approvals
 Rollback / Isolation / Reliability
```

## Highlights

- **Three-role conversational commerce** — dedicated customer shopping, seller stewardship and customer communications roles.
- **Native commerce** — catalogue, inventory, cart, checkout, tax, shipping, fulfilment, orders and returns.
- **Marketplace operations** — search, order import, governed write actions, bulk operations, dry runs and partial rollback.
- **Listing intelligence** — marketplace compliance, brand-voice profiles, evidence-backed claims and safe listing rewrites.
- **Inventory coordination** — reservation-aware channel allocation, reconciliation, conflict resolution and conflict history.
- **Payments and BNPL** — payment orchestration, asynchronous webhooks, settlement reconciliation and rental/hire receivables.
- **Unified order workbench** — native and external orders, exception management and linked customer communications.
- **Omnichannel communication** — web conversation, WhatsApp, Messenger, Instagram, Telegram and voice capabilities.
- **Human-in-the-loop control** — approvals, human handoff, action rollback and support escalation.
- **Operational safety** — encrypted credentials, credential rotation/revocation, signed session authority and strict tenant isolation.
- **Reliability controls** — durable queues, retry/dead-letter behaviour, provider circuit breakers and lifecycle management.
- **Bookings, reviews and customer intelligence** — supporting modules extend the core commerce workflow beyond transactions.

## Extension suite

| Module | Version | Purpose |
|---|---:|---|
| Commerce Crew Core | 7.7.0 | Conversation and shared platform foundation |
| Commerce Crew Agents | 3.0.0 | Agent workforce capabilities |
| **Commerce Crew Commerce Engine** | **4.9.0** | Advanced conversational and marketplace commerce |
| Commerce Crew Booking | 2.0.0 | Booking workflows |
| Commerce Crew Customer Intelligence | 2.0.0 | Customer tagging and segmentation |
| Commerce Crew Instagram | 2.0.0 | Instagram Direct integration |
| Commerce Crew Messenger | 2.0.0 | Messenger integration |
| Commerce Crew Telegram | 2.0.0 | Telegram integration |
| Commerce Crew WhatsApp | 2.0.0 | WhatsApp integration |
| Commerce Crew Voice | 3.0.0 | Voice interaction |
| Commerce Crew Voice Calls | 2.0.0 | Voice calling |
| Commerce Crew Reviews | 2.0.0 | Reviews and feedback |

## Commerce Engine architecture

The v4.9.0 Commerce Engine is the most advanced commerce module in the suite. It retains the v3.9.0 capability surface and extends it with marketplace governance, three-role commerce, operational reliability, credential security, tenant hardening, inventory conflict management and a unified order workbench.

Its capability surface includes:

**Customer commerce:** catalogue discovery, carts, checkout, budget locks, conversational cards, payments, BNPL, shipping, returns and human handoff.

**Seller operations:** catalogue and inventory management, listing intelligence, marketplace compliance, brand voice, safe listing rewrites, bulk operations and approval-controlled marketplace writes.

**Operations:** native/external order unification, settlement reconciliation, exception management, inventory reconciliation, reservation-aware allocation and customer communication linking.

**Governance and reliability:** tenant isolation, signed authority, credential vaulting, rollback, approvals, queues, retries, dead letters and provider circuit breakers.

## Repository structure

```text
extensions/
├── chatbot/                 # Commerce Crew Core
├── chatbot-agent/           # Agent workforce
├── chatbot-ecommerce/       # Commerce Engine v4.9.0
├── chatbot-booking/         # Booking
├── chatbot-customer-tag/    # Customer intelligence
├── chatbot-instagram/       # Instagram
├── chatbot-messenger/       # Messenger
├── chatbot-telegram/        # Telegram
├── chatbot-whatsapp/        # WhatsApp
├── chatbot-voice/           # Voice
├── chatbot-voice-call/      # Voice calls
└── chatbot-review/          # Reviews
```

Internal extension keys, namespaces and provider identifiers retain their compatibility names so the rebrand does not break installation or upgrade paths.

## Platform compatibility

The Commerce Engine declares compatibility with PHP 8.2+, Laravel 10/11 and MagicAI 10.91+, with Commerce Crew Core 7.7.0+ as its parent dependency. Individual supporting modules retain their own compatibility contracts.

## Engineering quality

The Commerce Engine includes contract, primitive, unit and feature tests covering core commerce operations, marketplace safety, inventory, pricing, checkout, tax, shipping, payments, BNPL, rental/hire, credential hardening, tenant isolation, reliability and the unified order workbench.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) and [`docs/CAPABILITIES.md`](docs/CAPABILITIES.md) for a concise system overview.
