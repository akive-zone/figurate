#  Platform Task Tracker

Backlog
- [ ] T07 Add realtime delivery (polling or push) for chat updates.
- [ ] T11 Generate OpenAPI export to verify filters + auth.
- [ ] T12 Define fulfillment statuses + state machine for request/quote/order/assessment/billing/worklog/settlement.
- [ ] T13 Add factories and seeders for missing models.
- [ ] T14 Add operation-specific validation and policies for Studio API actions.
- [ ] T16 Wire Studio UI to API (or shared domain logic) per chosen approach.
- [ ] T17 Add feature tests for Studio API operations.
- [ ] T18 Add policy tests for access control.
- [ ] T19 Add release checklist for API + NativePHP webview.
- [ ] T20 Product design: finalize Signal `flow_type` semantics (`ubuy`, `upwork`, `uber`) and selection rules.
- [ ] T21 Product design: define routing behavior per flow (direct target, open bidding, auto-assignment).
- [ ] T22 Product design: define agent-thread transition rules per flow (intake, bidding/assignment, booking, fulfillment).
- [ ] T24 Engineering: add `flow_type` persistence + validation once T20-T22 are approved.
- [ ] T25 Engineering: implement routing engine once T20-T22 are approved.
- [ ] T26 Engineering: implement thread-transition rules once T20-T22 are approved.

Progress
- [ ] T16 Wire Studio UI to API (or shared domain logic) per chosen approach. (Signal now uses request-context agent threads with RequestAgent/OrderAgent switching; Studio parity pending)

Followup
- [ ] (none)

Completed
- [x] T01 Audit current models, policies, and API Platform config
- [x] T02 Fix policy namespaces and register policies globally
- [x] T03 Add Studio API resources for fulfillment flow
- [x] T04 Align API middleware for current auth approach
- [x] T05 Note follow-up items (chat models, realtime)
- [x] T06 Define conversation/message models for the chatbox flow (asker + studio).
- [x] T08 Install Sanctum (composer require) and switch API middleware to `auth:sanctum`.
- [x] T09 Add token issuing endpoints (login/register) for API clients.
- [x] T10 Define auth scopes/abilities for Studio vs Signal.
- [x] T15 Decide Inertia vs Blade/Livewire for Signal UI and document decision.
- [x] T23 Capture initial Signal flow hypotheses (`ubuy`, `upwork`, `uber`) in DEVELOPMENT.md as design exploration.
