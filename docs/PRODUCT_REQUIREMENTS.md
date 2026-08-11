# Product Requirements

## Product

A marketplace connecting customers who need vehicle towing/recovery in Riyadh with licensed
tow-truck providers, their drivers, and fleets. Launching as a Web + PWA product with the
backend designed so Flutter Customer and Provider/Driver mobile apps can be added later without
backend changes.

## Users

- **Customer** — requests towing, tracks the trip, pays, reviews, disputes.
- **Provider Owner** — runs a towing business: fleet, drivers, documents, bank account.
- **Fleet Manager** — manages a provider's trucks/drivers under the owner.
- **Driver** — executes trips, updates status, shares location.
- **Dispatcher** — operations staff who monitor/assign orders.
- **Customer Support** — handles tickets and disputes.
- **Finance Officer** — payments, refunds, ledger, settlements.
- **Compliance Officer** — document verification, provider approval/suspension.
- **Operations Manager** — command-center oversight, manual dispatch/escalation.
- **Admin / Super Admin** — full platform administration; every super-admin action is audited,
  with no exceptions (see `docs/SECURITY.md`).

## Core customer journey

1. Set pickup location (GPS / map pin / address search).
2. Set destination.
3. Select vehicle (from saved vehicles or ad hoc).
4. Select service/problem type.
5. Optionally attach photos.
6. See a price estimate (fixed, estimated range, or "needs manual quote").
7. Authenticate (phone + OTP) if not already — required before an order becomes actionable,
   never before that (guest quotes are allowed, guest *orders* are not).
8. Confirm the order.
9. System dispatches to nearby eligible providers automatically (dispatch waves).
10. Provider/driver accepts; customer tracks arrival and trip status live.
11. Trip completes; customer pays (or cash, depending on payment method chosen).
12. Customer receives an invoice, can rate the service, can open a dispute.

## Core provider journey

1. Register a provider account; verify phone.
2. Submit commercial/legal details and required documents.
3. Add fleet (tow trucks) and drivers.
4. Add bank account for settlements.
5. Wait for compliance review; **a provider is never auto-activated** — approval is a deliberate
   compliance action (see `docs/COMPLIANCE.md`).
6. Once approved: set service areas, go online, receive dispatch requests, accept/reject,
   update trip status, see earnings/settlements/ratings, manage team per role permissions.

## Non-goals for the initial release (see `docs/ROADMAP.md` for the deferred list)

Driver bidding, dynamic pricing, wallet, subscriptions, corporate accounts, insurance
integrations, intercity transport, loyalty, promo codes, referrals, call masking, AI-driven
support/pricing. Microservices, Kubernetes, Kafka, event sourcing, CQRS, blockchain are also
explicitly out of scope unless a proven need emerges.

## Market

Riyadh first. Architecture (cities/service-zones, currency-as-a-code) leaves room for expansion
to Jeddah, Dammam, Makkah, Madinah, Taif later without a domain-model rewrite. Primary language
is Arabic (RTL); English is a planned secondary locale.

## Success signals (informal, to sanity-check scope — not contractual KPIs)

Order completion rate, average time-to-acceptance, average time-to-arrival, provider
acceptance/cancellation rates, payment failure rate, dispute rate. Concrete targets are a
business decision outside this document's scope.
