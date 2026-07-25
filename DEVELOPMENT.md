- Application (app)
- Configuration (config)
- Extension (ext)
- Modification (mod)

So we're building a platform that supports device and remote deployments.

Using tools like
- NativePHP Desktop
- FilamentPHP
- InertiaJS

There are two main parts to the project that will be served inside the device app through NativePHP Desktop using [InertiaJS + FilamentPHP]

Orchestration model (product design direction, not locked policy):
- Candidate direction is one user-facing `conversation` per request context for the asker chatbox.
- Candidate direction is many internal `threads` per request/order lifecycle to handle agent switching and phase isolation.
- Threads can hold `agent_key`, `phase`, and `ai_conversation_id` memory.
- The active thread may change over time (for example `RequestAgent` to `OrderAgent`) while keeping one visible chat flow for the asker.


The app will be accessible to all users with a basic account

- users will have three types system, device and person, enterprise

- device users will have limited access to the app ... these are users that haven't authenticated (guest users)

- person users will have access to both apps

- a device user upgrades to a person / enterprise user through a configured, protocol-based identity adapter

So overview for now ... one of the usecase 

Users are here to make a chat in a channel ... One of the usecase is that the user message is converted to a request and when sent out to profiles ... they can accept and it becomes an order  

Some users manage profiles that is created to showcase their skills ... and pick from categories of services they want to offer 

profiles must be approved by admin before they can go live on the signal marketplace 


So a typical flow of the platform

- Person User / Device User comes to the signal app and searches for profiles that match their needs and then makes a request to that profile 

- The profile owner gets notified of the request and can accept or reject it 

- If accepted ... the profile owner makes a quote for the request ... this basically a logging of what they will do, or can do , or can do for the request 

- If person user accepts the quote ... it becomes an booked order

- after this stage ... billing can be done ... either full or partial payment

- the profile owner now does assestment of the order ... which is optional ... if there's an assestement, the order assetment must be acknowledged by the person user before work can begin

- after this stage ... billing can be done ... either full or partial payment

- the person profile goes ahead to deliver the service ... online / offline 
   - there's a place on the studio for logging (allows photo, video, audio and text uploads) ... this is where the profile owner logs their work progress

- profile owner marks the order as completed 

- person user gets notified of completion and can review the work done

- person user can either accept the work or dispute it

- after this stage ... billing can be done ...  full payment or balance payment of partial payment

- if accepted ... the order is marked as fulfilled 

- both parties can rate each other

## Archived Summary of Early Design Exploration (2026-02-03 to 2026-02-13)
Archived summary from `PLAN.md` (2026-02-03 to 2026-02-13):

1. Product scope was defined as a cross-platform system (web + Native shell) with Signal, Studio, and Station/admin surfaces.
2. The fulfillment journey was stabilized around:
   enquiry -> quote -> booking -> assessment -> acknowledge -> billing -> tracking -> settlement.
3. Chatbox-first direction was adopted:
   one visible user conversation, with internal lifecycle handling through channel/thread orchestration.
4. Channel/thread architecture evolved to:
   channel as UX container, thread as phase/purpose context, messages as timeline, actors for routing, and per-actor memory support.
5. Orchestration direction introduced `ConversationOrchestrator` with explicit active-thread resolution, responder selection, and policy-safe transitions.
6. Tooling direction added role-aware agent tools via resolver/executor patterns for domain writes (order/assessment style actions) with auditability.
7. Data modeling explored both:
   classic request/order/assessment/process/payment relationships, and an event-style `posts`/`post_relations` approach.
8. Guardrails were repeatedly emphasized:
   deterministic transitions, idempotent behavior, auditable system events, and clear policy boundaries.
9. Runtime/deployment notes captured monolith dual-surface operation:
   server DB migrations and Native-local DB migrations loaded conditionally by environment.

## ADR Scope: AI Interoperability (A2A, A2UI, MCP)

Date range: 2026-03-02 to 2026-03-08  
Status: Implemented baseline and in active hardening

### Why this was needed

We needed our AI service to work safely with:

- other agents (A2A),
- dynamic UI payloads from agents (A2UI),
- external tools/context servers (MCP),

while keeping one consistent product experience for users.

### Decisions we made

1. We support A2A as a first-class integration path for inbound and outbound agent collaboration.
2. We keep ownership boundaries on tasks so callers can only access their own task lifecycles.
3. We model execution as traceable events, so task history and agent actions are auditable.
4. We support A2UI as a reusable rendering/runtime layer so agent-provided UI can be shown consistently.
5. We support MCP through controlled server resolution and invocation policy instead of open-ended tool execution.

### What is done

1. A2A transport endpoints, task lifecycle operations, streaming updates, and push callback flow are implemented.
2. A2A authentication and per-method access controls are in place.
3. Task ownership checks are enforced for task reads, listing, cancellation, and subscription-style flows.
4. Outbound agent delegation and remote-task reconciliation are implemented.
5. A2UI runtime pieces are in place (surface renderer, field registry, client/runtime composables, page integration).
6. MCP support for context server discovery, resolution, and tool invocation is integrated with the AI tool layer.
7. Core execution/event linkage is in place so A2A and MCP activity can be tracked end-to-end.

### What remains

1. Complete webhook trust hardening (signature, replay-window protection, and stricter deny logging).
2. Expand contract/security test coverage for lifecycle edge cases and reconnect behavior.
3. Finalize stricter outbound callback trust rules for non-local environments.
4. Continue simplifying documentation and ops playbooks so implementation details stay in code and tests.

### Product-level outcome

The platform now has a practical interoperability baseline:

- agents can coordinate with other agents,
- agent-generated UI can be rendered consistently,
- approved external tools can be invoked safely,

without changing the user-facing chat-first experience.
