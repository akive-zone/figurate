So we're buildin a cross platform that supports Native and Web 

Using tools like
- NativePHP
- FilamentPHP
- InertiaJS

There are two main parts to the project that will be served inside the NativePHP app using [InertiaJS + FilamentPHP]

The app launcher will be a blade view in NativePHP that allows the user switch between various auth session

Orchestration model (product design direction, not locked policy):
- Candidate direction is one user-facing `conversation` per request context for the asker chatbox.
- Candidate direction is many internal `threads` per request/order lifecycle to handle agent switching and phase isolation.
- Threads can hold `agent_key`, `phase`, and `ai_conversation_id` memory.
- The active thread may change over time (for example `RequestAgent` to `OrderAgent`) while keeping one visible chat flow for the asker.


The app will be accessible to all users with a basic account

- users will have three types system, device and person, enterprise

- device users will have limited access to the app ... these are users that haven't authenticated (guest users)

- person users will have access to both apps

- a device user upgrades to a person / enterprise user by identity provider login with Google / Apple 

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

## Signal Fulfillment Flows (Design Exploration)

The following are product flow candidates to evaluate and refine:

- `ubuy` candidate:
- Asker targets a specific profile/tasker.
- Request starts with an intake thread using `RequestAgent`.
- Quote/booking/fulfillment likely stays bound to that selected profile unless reassigned.

- `upwork` candidate:
- Asker creates an open request.
- Multiple profiles can express interest and submit quotes/bids.
- Asker selects one quote to book, then flow can switch into fulfillment with `OrderAgent`.

- `uber` candidate:
- Asker creates a request without selecting a worker.
- System may auto-assign the best matching profile using availability + matching rules.
- After assignment, flow can proceed to quote or direct booking based on service configuration.

Thread usage (working design hypothesis):

- Main thread begins at request intake (`RequestAgent`).
- Additional threads can be used for scoped phases (for example negotiation, booking, fulfillment, disputes).
- A single request context may own multiple threads while preserving one primary user-facing conversation.
- Final rules for thread creation/switching remain open pending product decisions.

## Database & Migrations

Since the project is a monolith deployed on both the Server (MySQL/PostgreSQL) and Mobile (SQLite via NativePHP), we use a conditional migration strategy in `AppServiceProvider`:

- **Server Migrations:** Located in `database/migrations/server`. These contain schemas for the central database (Users, Profiles, Orders, etc.).
- **Mobile Migrations:** Located in `database/migrations/native`. These contain schemas for the local device database.
- **Shared Migrations:** Located in `database/migrations`.

The `AppServiceProvider` detects the environment using `config('nativephp-internal.running')` and loads the appropriate paths:

```php
if (config('nativephp-internal.running')) {
    $this->loadMigrationsFrom(database_path('migrations/native'));
} else {
    $this->loadMigrationsFrom(database_path('migrations/server'));
}
```

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
