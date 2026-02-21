# Active Plan

Date: 2026-02-21

## Goal
Stabilize chat orchestration and fulfillment execution around Laravel AI primitives, with clean naming and thin HTTP boundaries.

## Decisions Locked
1. Use Laravel AI queue + broadcasting flow from HTTP entrypoints.
2. Controller contract is minimal: `channel`, `thread_actor`, `message` (idempotency-safe), then delegate.
3. Non-HTTP internals should prefer bigint `id` over UUID.
4. Keep backend class names `ChatToolResolver` and `ChatAgentExecutor`.
5. Remove legacy `Signal` naming where not needed, especially frontend keys, storage names, CSS classes, and resource folder structure.
6. Keep orchestration generic across Human-Human and Human-Agent; avoid coupling orchestration to `ServiceRequest`.

## Current Architecture Direction
1. Channel is the user chat container.
2. Thread is execution context.
3. Thread actor selects who handles the message:
   - human actor
   - AI handler agent
4. Conversation routing resolves a thread and returns `resolved_thread_id`.
5. If a `thread_id` is explicitly passed, that validated thread is the resolved thread; intent-based switching happens only when thread is not provided.

## In Progress
1. Finalize Laravel AI broadcast-on-queue path called directly from HTTP controller.
2. Add feature tests for controller contract, idempotency, thread resolution, and queue/broadcast behavior.

## Next Tasks
1. Remove remaining `ServiceRequest` references from server web controllers/list payload shaping.
2. Confirm thread resolution invariants:
   - explicit thread wins
   - fallback resolves from channel scope
   - closed/inaccessible thread fails fast
3. Add/adjust feature tests for:
   - controller payload contract
   - idempotent message submission
   - thread resolution behavior
   - queue dispatch + broadcast visibility

## Risks
1. Route/provider drift after refactors (currently a known provider class mismatch exists).
2. Mixed use of UUID and bigint in old codepaths.
3. Legacy event flows overlapping Laravel AI events.

## Done Recently
1. Refactored frontend storage keys and CSS class/variable prefixes away from legacy naming.
2. Refactored JS API modules (`chat.js`, `base.js`, `index.js`).
3. Updated Native control panel provider discovery scoping.
4. Removed `PromptPresenterThread` and consolidated message creation flow.
5. Reduced chat orchestration coupling with `ServiceRequest` in key server API paths.
6. Normalized chat request payload to `body` + `attachments` across backend validation and frontend callers.
7. Unified thread message persistence via `StoreThreadMessage` action for both peer and agent message writes.
8. Hardened explicit thread context resolution to recover channel from relation tables when threadable is not channel.

## Exit Criteria For This Plan
1. Chat controller is thin and transport-only.
2. Orchestration is domain-driven and request-type agnostic.
3. Queue + broadcasting is fully Laravel AI-native.
4. Naming is consistent with no leftover legacy terminology in active UI surfaces.
