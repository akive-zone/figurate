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
8. Process (media + text updates).
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
7. Process: create, list, attach media.
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

---

Date: 2026-02-07
Time: 10:45:00 WAT

**Proposed Channel + Thread Architecture (Holistic Draft)**

**Intent**
1. Keep one visible chatbox per request journey for the user.
2. Allow backend orchestration to switch AI agents by phase.
3. Prevent schema sprawl while preserving auditability and clean transitions.

**Core Objects**
1. `requests`
   The business object for the ask itself (title, description, status, pricing path). It should not directly own requester/profile FKs.
2. `channels`
   The UX container for the chat surface a user opens in Signal or Studio.
3. `channel_relations` (polymorphic)
   Links a channel to one or more business records (`Request`, later optionally `Order`, `Dispute`).
4. `request_actors` (polymorphic pivot)
   Actor membership for requests using (`request_id`, `actor_type`, `actor_id`, `action`, `status`).
   `actor` may be `User` or `Profile`.
   Example actions: `asker`, `target_profile`, later `assigned_profile`, `watcher`.
5. `threads` (polymorphic parent)
   Phase-scoped conversation contexts attached to a business record, usually the request. Threads should remain neutral and not store actor-specific memory IDs directly.
6. `thread_actors`
   Actor membership + behavior routing on each thread (`actorable_type`, `actorable_id`, `role`, `status`, `priority`, `config`).
   For named/system actors, store name in `actorable_type` and leave `actorable_id` null.
   Roles include `primary_handler`, `observer`, and `participant`.
7. `thread_actor_memories`
   Per-thread-per-actor memory state (provider/model/conversation continuity) so each actor keeps independent memory.
8. `messages` (polymorphic parent)
   The message stream. Preferred usage: store chat on `Thread` so each thread has isolated history.

**Single Chatbox, Multiple Thread Contexts**
1. Channel starts with one primary thread (`phase = intake`).
2. Primary behavior is resolved via active `thread_actors` where `role = primary_handler`.
3. UI shows one chatbox at a time, bound to `channel.active_thread_id` (or equivalent resolver).
4. When fulfillment phase changes, system opens a new thread and switches active thread.
5. Prior threads stay queryable for audit/history and can be reopened if needed.

**Thread Types**
1. `request_agent`
   Clarifies ask, scope, and worker matching steps.
2. `order_agent`
   Handles booked-order guidance, milestones, and issue triage.
3. `human_chat`
   Direct asker-worker conversation for negotiation/collaboration.
4. Optional future keys:
   `assignment_agent`, `billing_agent`, `dispute_agent`.

**Recommended Message Storage Rule**
1. Persist all user/worker/assistant/system chat events in `messages` with `messageable = Thread`.
2. Keep actor memory continuity in `thread_actor_memories`, not on `threads`.
3. Do not rely on separate ad hoc AI message tables for core product history.

**Flow Profiles (Product Modes)**
1. `ubuy` (direct)
   Request writes actor rows like:
   asker user (`action=asker`) + chosen profile (`action=target_profile`).
2. `upwork` (market)
   Request starts with asker actor; candidate/bidder profiles are represented as actor rows with flow-specific actions.
3. `uber` (auto-assignment)
   Request starts with asker actor; assignment writes selected profile actor (`action=assigned_profile`).

**Thread Transition Examples**
1. Request opened:
   Create `intake/request_agent` as main thread.
2. Bidding starts (`upwork`):
   Create `bidding/human_chat` or `bidding/request_agent`.
3. Quote accepted:
   Create `booking/order_agent`.
4. Work starts:
   Create `fulfillment/human_chat` plus optional `fulfillment/order_agent`.
5. Dispute raised:
   Create `dispute/dispute_agent` and mark prior active thread non-active.

**State + Transition Guardrails**
1. Thread creation should be event-driven, not arbitrary form-driven.
2. Add transition service/policy to enforce allowed phase moves by flow type.
3. Keep one active thread per channel by invariant.
4. Close or archive obsolete threads instead of deleting.

**API Surface Direction**
1. Keep generic endpoints:
   `/api/request`, `/api/chat`, `/api/order`.
2. Chat write contract:
   - `POST /api/chat/{channel}`
   - body includes `content` and optional `thread_id`; server resolves thread behavior from primary handler actor.
3. Thread creation/switching rules should stay domain-driven (request/order events), not exposed as generic client thread CRUD.
4. Chat UI routes remain web-only (`web.php`) and call APIs via axios.

**Open Decisions to Finalize**
1. Should `threads` stay attached to `Request` only, or permit `Order`/`Dispute` ownership from day one?
2. What is the canonical `request_actors.action` vocabulary per flow (`ubuy`, `upwork`, `uber`)?
3. Should `human_chat` be auto-created at request-open, quote-accepted, or flow-specific?
4. Should inactive threads be writable for follow-ups, or hard-locked after transition?

**Ubuy Transition Map (Current Working Mode)**

**Thread Topology**
1. `channel` = project container.
2. Main thread is required:
   `purpose=orchestration`, `title=Project Main`.
   Add `thread_actors` primary handler: `actorable_type=request_agent`, `actorable_id=null`.
3. Purpose threads are optional and created only on intent change.
4. `worker_chat` thread is the first purpose-thread candidate.

**Actor Setup**
1. On request creation, create `request_actors` rows:
   - asker user: `action=asker`, `status=active`
   - selected worker profile: `action=target_profile`, `status=active`

**State Machine (Main Thread)**
1. `request_intake`
2. `quote_pending`
3. `quote_received`
4. `booking_pending`
5. `booked`
6. `fulfillment_in_progress`
7. `completion_review`
8. terminal:
   `fulfilled` or `disputed`

**Transition Rules**
1. `request_created`:
   - enter `request_intake`
   - create main thread
   - post system message in main thread
2. `worker_submits_quote`:
   - `request_intake` or `quote_pending` -> `quote_received`
   - keep same main thread
3. `asker_accepts_quote`:
   - `quote_received` -> `booked`
   - switch main thread primary handler actor from `request_agent` to `order_agent`
   - keep same main thread id
4. `work_started`:
   - `booked` -> `fulfillment_in_progress`
   - keep same main thread
5. `worker_marks_done`:
   - `fulfillment_in_progress` -> `completion_review`
   - keep same main thread
6. `asker_accepts_completion`:
   - `completion_review` -> `fulfilled`
   - keep same main thread
7. `asker_opens_dispute`:
   - from `booked` / `fulfillment_in_progress` / `completion_review` -> `disputed`
   - keep main thread as status source; optional `dispute` purpose thread may be created

**When To Create Purpose Threads**
1. `worker_chat`:
   - create when either side sends first direct human message
   - do not auto-create at request open
2. `dispute`:
   - create only when dispute workflow starts
3. `billing` (optional future):
   - create only if billing discussion needs isolated history

**No-New-Thread Rule**
1. Pure status transitions must not create new threads.
2. New thread requires a new intent/purpose, not a new status.
3. Main thread remains the fulfillment timeline source of truth.

**Event To Thread Action Matrix (Ubuy)**
1. `request_created` -> main thread: create, purpose thread: no
2. `worker_submits_quote` -> main thread: update phase, purpose thread: no
3. `asker_accepts_quote` -> main thread: update phase + switch agent, purpose thread: no
4. `first_worker_chat_message` -> main thread: no phase change, purpose thread: create `worker_chat`
5. `work_started` -> main thread: update phase, purpose thread: no
6. `worker_marks_done` -> main thread: update phase, purpose thread: no
7. `asker_accepts_completion` -> main thread: update phase to terminal, purpose thread: no
8. `asker_opens_dispute` -> main thread: update phase to terminal/branch, purpose thread: optional `dispute`

**Human Chat Observer Architecture**

**Goal**
1. Allow bots to listen to `human_chat` and trigger moderation/suggestion actions without replacing human-to-human conversation ownership.

**Design Principle**
1. `human_chat` remains a human message thread.
2. Bots run as observers in background, not as primary speakers.
3. Observer outputs are stored as events/actions, not mixed into user-authored messages by default.

**Suggested Components**
1. `thread_actors` table:
   - `thread_id`
   - `actorable_type` (actor class or named actor like `request_agent`, `order_agent`, `human_chat`, `safety_guard`)
   - `actorable_id` (nullable; null for named/system actors)
   - `role` (`primary_handler`, `observer`, `participant`)
   - `status` (`active`, `paused`)
   - `priority`
   - `config` (for mode and actor-specific options)
2. `thread_actor_memories` table:
   - `thread_id`
   - `thread_actor_id`
   - `provider`
   - `model`
   - `conversation_id`
   - `state`
   - `last_used_at`
3. `thread_events` table:
   - `thread_id`
   - `message_id`
   - `actor_key` (actor reference string derived from `thread_actors`)
   - `event_type` (`moderation_flagged`, `message_blocked`, `suggestion_created`, `risk_detected`)
   - `severity` (`low`, `medium`, `high`)
   - `payload` (json)
4. Optional message metadata:
   - `messages.meta.moderation_status`
   - `messages.meta.observer_flags[]`

**Runtime Flow**
1. Message saved to `human_chat`.
2. Dispatch async observer pipeline (`MessagePosted` -> queued job).
3. Run active observers for that thread.
4. Persist observer outcomes into `thread_events`.
5. Apply enforcement policy:
   - `allow`: no action
   - `flag`: keep visible + warning/notification
   - `block`: redact/hide + notify + audit
6. Suggestions are surfaced as system suggestions, not implicit user messages.

**Ubuy Integration Rules**
1. `worker_chat` thread should start with `safety_guard` observer enabled.
2. `enforcing` actions require deterministic policy checks plus audit event writes.
3. Main orchestration thread remains status source of truth; observer outcomes from `human_chat` may trigger state transitions only through explicit domain actions.

---

Date: 2026-02-10
Time: 23:58:00 WAT

**Agent Tooling Plan (Role-Aware Dynamic Loading)**

**Objective**
1. Let agents write to domain tables (`orders`, `assessments`) through explicit Laravel AI tools.
2. Load tools dynamically from thread context and prompting actor role (asker vs worker).
3. Keep memory per actor via `thread_actor_memories` and keep write actions auditable via `messages` system entries.

**Resolver Pattern**
1. Add `ThreadToolResolver` to resolve tools from:
   - active `thread` primary handler actor (`request_agent`, `order_agent`)
   - `request_actors` role checks for current user (`asker`, profile actor)
2. `RequestAgent` and `OrderAgent` call resolver from `tools()` using constructor context (`thread`, `actor`).

**Tool Matrix (Initial)**
1. `request_agent` + asker:
   - `CreateOrderFromQuoteTool`
2. `order_agent` + asker:
   - `AcknowledgeAssessmentTool`
3. `order_agent` + worker:
   - `UpsertAssessmentTool`

**Write Rules**
1. Tools must hard-check actor authorization against request/order ownership.
2. Tools must return structured JSON text payload (`ok`, ids, status, errors).
3. Tools write system messages on thread (`type=system`, `tag=*`) for audit timeline.
4. Tools must be idempotent where practical (example: return existing order if already created).

**API Interaction**
1. Continue single chat entrypoint: `POST /api/chat/{channel}`.
2. Client sends `content` (+ optional `thread_id`); server resolves thread + actor + tools.
3. No dedicated thread-tool endpoints are exposed.

---

Date: 2026-02-12
Time: 16:23:15 WAT

**System Overview (Conversation + Fulfillment)**

**Purpose**
1. Keep chat as the operational timeline.
2. Keep fulfillment records as the transactional source of truth.
3. Link both layers through `Request` and `Order`.

**Conversation Layer**
1. `channels`: the user-facing inbox/chat container.
2. `threads`: phase-scoped conversations attached to a business record (currently request-scoped).
3. `messages`: timeline entries on a thread (human, agent, system).

**Fulfillment Layer**
1. `requests`: intake/business intent and participant membership.
2. `orders`: accepted quote output for a request.
3. `assessments`: one-to-one with order.
4. `processes`: one-to-many with order (work/progress updates).
5. `payments`: one-to-many with order (billing/settlement events).

**Relationship Chain**
1. `Channel -> Request` (many-to-many via `channel_relations`, currently one primary request in practice).
2. `Request -> Threads` (polymorphic one-to-many).
3. `Thread -> Messages` (polymorphic one-to-many).
4. `Request -> Order` (one-to-one).
5. `Order -> Assessment` (one-to-one).
6. `Order -> Processes` (one-to-many).
7. `Order -> Payments` (one-to-many).

**Runtime Flow (Backend)**
1. `POST /api/request`: creates request + channel + intake thread (`phase=request_intake`) + optional first message.
2. `POST /api/chat/{channel}`: resolves active thread and writes/returns conversation output (human or agent mode).
3. `POST /api/order/channels/{channel}/quotes/{quote}/accept`: accepts quote, creates order, marks booked, opens order thread (`phase=order_kickoff`).
4. Assessment updates are currently driven by agent tools and reflected as system messages in thread.
5. Processes and payments are available through API Platform resources (`/api/signal/processes`, `/api/signal/payments`) and remain order-scoped domain records.

**Design Rule**
1. Use `threads/messages` for narrative, guidance, and audit timeline.
2. Use `request/order/assessment/process/payment` for durable state transitions and reporting.
3. Cross-layer consistency should always reconcile through `Request -> Order` keys.

---

Date: 2026-02-12
Time: 23:46:43 WAT

**Fulfillment Architecture Reiteration (Agnostic Posts Model)**

**Core Decision**
1. Use a single `posts` table for fulfillment and system events.
2. Keep `posts` context-free (no `channel_id`, no `thread_id`, no `request_id`, no `order_id`).
3. Use `post_relations` to attach any post to any domain object.
4. Use dual identifiers on all core tables: bigint `id` (internal joins) + `uuid` (public identifier).

**Tables**
1. `posts`
   - `id`
   - `uuid`
   - `type`
   - `status`
   - `payload` (json / jsonb by database engine)
   - `meta`
   - `occurred_at`
   - timestamps / soft deletes
2. `post_relations`
   - `id`
   - `uuid`
   - `post_id`
   - `relationable_type`, `relationable_id` (polymorphic)
   - `role` (`primary`, `context`, `derived`, `caused_by`)
   - timestamps
   - unique index on (`post_id`, `relationable_type`, `relationable_id`, `role`)

**Model Strategy (CMS-style)**
1. Keep domain models (`Request`, `Order`, `Assessment`, `Process`, `Payment`) as typed wrappers over the same `posts` table.
2. Different models enforce type scopes and payload contracts, while storage remains unified.
3. Relations to channel/thread/request/order context are represented through `post_relations`.

**Flow Model**
1. Each channel can have its own sequence of `post.type` events.
2. No fixed path is enforced by schema.
3. Example valid dynamic path: `request.created -> order.booked -> payment.captured` (no quote/process required).
4. Validation lives in application flow rules (`FlowEngine`), not in rigid table coupling.

**Operational Rules**
1. Posts are append-only events.
2. Build read projections for fast channel state reads.
3. Require exactly one `primary` relation per post.
4. Additional relations are optional and additive for traceability.
5. Public APIs should use `uuid`; internal relationships and indexing should use bigint `id`.
