# Project Plan

Date: 2026-02-03
Time: 21:36:08 WAT

## Goal
Build a cross-platform platform (Mobile + Web) with NativePHP as the shell, Inertia (Signal) and Filament (Studio) as the two main experiences, plus a separate Admin app (Station).

## Scope
1. Signal (Inertia): request flow for device/person users.
2. Studio (Filament): provider onboarding, profiles, order management, progress logging.
3. Station (Filament): approvals, disputes, system oversight.

## Key Roles & User Types
1. System user.
2. Device user (guest, limited access to Signal).
3. Person user (authenticated, access to Signal + Studio).

## Core Entities (Draft)
1. User (type: system, device, person).
2. Profile (provider profile, requires admin approval).
3. ServiceCategory.
4. Request (created in Signal).
5. Quote (created by profile owner).
6. Order (accepted quote; can progress through billing, assessment, fulfillment).
7. Assessment (optional, must be acknowledged).
8. WorkLog (media + text uploads).
9. Payment (partial/full; multiple stages).
10. Rating (mutual).
11. Dispute.

## Phased Plan

### Phase 1: Foundations
1. Confirm product requirements, naming, and final flow definitions.
2. Define database schema and relationships.
3. Define policies/permissions for user types.
4. Set up NativePHP shell layout and app launcher (Blade).
5. Set up Signal (Inertia) and Studio (Filament) routing and navigation skeletons.

### Phase 2: Identity & Access
1. Device user creation and session handling.
2. Socialite login (Google/Apple) and upgrade flow to person user.
3. Access rules for Signal vs Studio vs Station.

### Phase 3: Studio Onboarding
1. Studio signup flow for person users.
2. KYC/KYB capture and verification state.
3. Provider profile creation and admin approval workflow.

### Phase 4: Signal Request Flow
1. Search/browse profiles by category and filters.
2. Request creation against a profile.
3. Notifications to profile owners.

### Phase 5: Quote to Order Lifecycle
1. Quote creation, acceptance, and order creation.
2. Payment handling for partial/full stages.
3. Optional assessment creation and acknowledgment.
4. Work logging and completion.
5. Acceptance/dispute and fulfillment.

### Phase 6: Admin (Station)
1. Profile approvals.
2. Dispute management.
3. Risk flags and manual review tools.

### Phase 7: QA, Performance, and Release
1. Feature tests for all core flows.
2. Mobile testing and NativePHP packaging.
3. Monitoring, logs, and release checklist.

## Milestones
1. M1: Data model + auth + routing skeleton.
2. M2: Studio onboarding + profile approval flow.
3. M3: Signal request + quote + order basics.
4. M4: Full lifecycle with payments, assessment, work logs.
5. M5: Admin station + dispute tools.
6. M6: End-to-end test + mobile packaging.

## Risks & Open Questions
1. Exact payment provider and escrow model.
2. KYC/KYB vendor choice and legal requirements.
3. Dispute resolution policy and data retention.
4. Scope for guest users and what is visible before auth.
5. Notification channels (email, push, in-app) and SLAs.

## Immediate Next Steps
1. Confirm product decisions for KYC/KYB and payments.
2. Finalize user upgrade flow and access matrix.
3. Lock the initial DB schema and create migrations.

---

Date: 2026-02-06
Time: 00:47:29 WAT

**Goal**
Unify the platform as a web app that runs in three surfaces: browser, NativePHP webview (mobile/desktop), and server API.

**Product Flow (From PRODUCT.md)**
1. Enquiry (request creation).
2. Quote (artisan proposes scope/price).
3. Booking (acceptance).
4. Assessment (onsite assessment and tools list).
5. Acknowledge (user confirms assessment).
6. Billing / Processing (estimate + add-ons + payment verification).
7. Track / Trace (work progress and quality control).
8. Settlement (work done, rating, close out).

**Architecture Direction**
1. Web UI is the primary interface and is used by browser and NativePHP webview.
2. API is the shared server surface for Studio operations and mobile/web clients.
3. Context switching is driven by `APP_CONTEXT` for any UI differences.

---

Date: 2026-02-06
Time: 01:10:00 WAT

**Phase 1: Platform Alignment**
1. Confirm Studio user journey and map to entities and state machine.
2. Define API Platform resources for Studio flow.
3. Define request/quote/order/assessment/billing/worklog/settlement statuses.
4. Align auth strategy for API consumers and webview.

**Deliverables**
1. Studio flow spec as state transitions.
2. API resource list and operations list.
3. Updated route and middleware plan for API access.

---

Date: 2026-02-06
Time: 02:00:00 WAT

**Phase 2: Data Model & Policies**
1. Verify existing models and add missing ones.
2. Add policies for Studio actions (quote, assessment, billing, work log, completion).
3. Define relationships and prevent N+1 via eager loading.
4. Add factories and seeders for new models.

**Deliverables**
1. Data model diagram (textual in notes).
2. Policy matrix for Studio roles.
3. Factory coverage for test setup.

---

Date: 2026-02-06
Time: 03:00:00 WAT

**Phase 3: API Platform Implementation (Studio)**
1. Annotate models with `#[ApiResource]` for Studio surfaces.
2. Define operations for each resource:
3. Request: list, detail, accept/decline.
4. Quote: create, revise, accept.
5. Assessment: create, update, acknowledge.
6. Billing/Payment: create invoice, verify payment.
7. WorkLog: create, list, attach media.
8. Order: status updates, completion.
9. Rating: submit, list.

**Deliverables**
1. Studio API resources with OpenAPI docs.
2. Default filters and pagination.
3. Validation rules per operation.

---

Date: 2026-02-06
Time: 04:00:00 WAT

**Phase 4: Web UI Integration**
1. Decide on Inertia vs Blade/Livewire for Signal UI.
2. Wire Studio UI to consume the API (or use shared domain logic directly for web).
3. Add context-aware views only where needed.

**Deliverables**
1. Studio UI flow wired to API.
2. Shared UI behavior across browser and NativePHP webview.

---

Date: 2026-02-06
Time: 05:00:00 WAT

**Phase 5: Testing & Release Readiness**
1. Feature tests for Studio API operations.
2. Policy tests for access control.
3. Happy path and failure path coverage for the flow.

**Deliverables**
1. Passing PHPUnit tests.
2. Minimal release checklist.

---

Date: 2026-02-06
Time: 01:09:32 WAT

**Chatbox-First Fulfillment Flow (Signal + Studio)**

**Core Principle**
Both the asker (Signal) and the handler (Studio) use a chatbox-first experience. Each fulfillment step is expressed as a chat event, with system messages guiding state changes.

**Signal (Asker) Chat Flow**
1. Enquiry: user submits request, images, and description in chat.
2. Recommendations: system suggests artisans in chat with quick-select options.
3. Quote: artisan reply appears in chat with quoted scope and estimate.
4. Booking: user accepts quote in chat (system confirms booking).
5. Assessment: artisan posts assessment details and tools list in chat.
6. Acknowledge: user confirms assessment in chat (system logs acknowledgement).
7. Billing: system posts estimate + add-ons; user pays via chat CTA.
8. Track/Trace: artisan posts progress updates and work logs in chat.
9. Settlement: artisan marks done; user rates and confirms completion in chat.

**Studio (Handler) Chat Flow**
1. New request arrives as a chat thread.
2. Handler replies with quote and proposed scope in chat.
3. Booking confirmation appears in thread after user acceptance.
4. Handler posts onsite assessment and tools list in chat.
5. Acknowledgement appears as a system chat event.
6. Billing posted by system; handler sees payment confirmation in chat.
7. Handler logs progress and completion via chat updates.
8. Rating and completion confirmation posted to thread.

**API Requirements (Studio + Signal)**
1. Conversations: create, list, show.
2. Messages: create (user/system/handler), list, attachments.
3. System events: quote, assessment, billing, payment, completion, rating.
4. State transitions: enforce flow order and permissions.
5. Realtime: optional push or polling for new messages and events.
