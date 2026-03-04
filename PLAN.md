# Chat Orchestration Plan

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

---

# Plan Entry: A2A Protocol

Date: 2026-03-02

## Goal
Implement A2A-compliant transport and task lifecycle on top of existing thread orchestration.

## Current Status
1. Inbound transport is implemented and active:
   - `/.well-known/agent-card`
   - `/api/a2a/rpc`
   - `/api/a2a/stream`
   - `/api/a2a/webhooks/push`
2. Inbound auth is implemented with Sanctum PAT + per-method abilities.
3. Owner boundary is implemented for task operations:
   - owner stamped on `message/send` as `meta.a2a_owner`
   - enforced on `tasks/get`, `tasks/list`, `tasks/cancel`, `tasks/resubscribe`, and push-config methods
4. Outbound A2A tooling is implemented:
   - allowlisted remote registry
   - `invoke_a2a_agent` and `delegate_a2a_task`
   - `sajya/client` JSON-RPC calls
5. Push notifications are implemented:
   - outbound push dispatch
   - inbound webhook processing and task reconciliation
6. Streaming is implemented:
   - SSE status updates for `message/stream` and `tasks/resubscribe`
7. Execution modeling is implemented:
   - `thread_events` is execution layer (`event_key`, `layer`, `kind`, `operation`, `state`, `thread_actor_id`)
   - `agent_tasks` linked via `thread_event_agent_tasks`
8. Current architecture decisions:
   - inbound A2A machine auth uses Sanctum PATs
   - owner boundary is principal-based via `meta.a2a_owner` until formal org/workspace tenancy exists
   - `thread_events` is the execution record; `agent_tasks` remains remote-task projection/mapping

## Remaining Work
1. Webhook security hardening:
   - enforce required signatures/secrets for inbound push
   - enforce timestamp freshness/replay checks
   - log explicit deny reasons
2. Outbound push trust hardening:
   - callback URL policy (https in non-local, block localhost/private ranges, optional allowlist)
3. A2A contract/security test coverage:
   - JSON-RPC contract shapes
   - lifecycle transitions + cancel behavior
   - stream/reconnect paths
   - authn/authz/owner-boundary failures
   - webhook signature/timestamp validation
4. Optional architecture enhancement:
   - bind `tasks/resubscribe`/stream to Reverb-backed event replay instead of polling loop

## Done Recently
1. Added baseline A2A scaffold:
   - `/.well-known/agent-card` endpoint
   - `/api/a2a/rpc` JSON-RPC method router (`message/send`, `message/stream`, `tasks/get`, `tasks/cancel`, `tasks/resubscribe`)
   - `/api/a2a/stream` SSE scaffold endpoint
   - initial `config/a2a.php` capability and agent metadata config
2. Migrated A2A JSON-RPC endpoint to `sajya/server` procedures with slash-delimited method mapping:
   - `message/send`, `message/stream`
   - `tasks/get`, `tasks/cancel`, `tasks/resubscribe`
3. Replaced A2A method stubs with real thread execution and persisted task state projection:
   - `message/send` resolves channel/thread, writes prompt message, and queues active presenters
   - `tasks/get` derives task state from prompt invocation metadata + assistant replies
   - `tasks/cancel` marks pending invocations as canceled in prompt metadata
4. Added cancellation-aware execution guards:
   - presenter success/failure callbacks ignore canceled invocations
   - canceled tasks are not overwritten to `completed` / `failed` by late async callbacks
5. Added outbound A2A agent tooling:
   - allowlisted remote-agent registry in `config/a2a.php` (`outbound.agents`)
   - `list_available_a2a_agents` + `invoke_a2a_agent` tools wired into presenter toolset
   - outbound JSON-RPC calls implemented via `sajya/client`
6. Added high-level outbound delegation wrapper tool:
   - `delegate_a2a_task` performs `message/send` + `tasks/get` polling with timeout handling
   - optional timeout cancellation via remote `tasks/cancel`
7. Added A2A push-notification scaffolding on top of Spatie webhooks:
   - new JSON-RPC methods:
     - `tasks/list`
     - `tasks/pushNotificationConfig/set`
     - `tasks/pushNotificationConfig/get`
     - `tasks/pushNotificationConfig/list`
     - `tasks/pushNotificationConfig/delete`
   - task status webhook dispatch for `submitted` / `working` / `completed` / `failed` / `canceled`
   - inbound webhook endpoint for A2A push callbacks (`/api/a2a/webhooks/push`) with processing job
8. Added latest-spec compatibility layer for A2A push/task RPC:
   - canonical JSON-RPC method aliases (e.g. `CreateTaskPushNotificationConfig`, `GetTask`, `SendStreamingMessage`) now accepted at `/api/a2a/rpc`
   - push webhook payload switched to JSON-RPC `SendTaskStreamingNotificationRequest`-style shape (`method`, `params.id`, `params.statusUpdate`)
   - push config responses now include canonical camelCase keys (`taskId`, `pushNotificationConfig`, `pushNotificationConfigs`) while keeping legacy keys for compatibility
9. Switched A2A JSON-RPC endpoint to native `Route::rpc` and moved concerns into middleware:
   - feature gating now in `EnsureA2aEnabled` middleware
   - canonical method-name normalization now in `NormalizeA2aRpcMethodNames` middleware
10. Completed SSE streaming behavior on `/api/a2a/stream`:
   - supports `message/stream` and `tasks/resubscribe`
   - emits incremental `a2a.task` updates by polling `tasks/get` until terminal state
   - sends `a2a.ping` keepalive events and terminal `a2a.done`/`a2a.timeout` events
11. Added outbound push callback reconciliation for delegated remote tasks:
   - persistent mapping table `agent_tasks` (`remote_agent_id + remote_task_id -> local thread`)
   - `delegate_a2a_task` now attempts remote push config registration and stores mapping
   - inbound push webhook processor now resolves mapping, updates link state, and writes local `a2a_remote_response` message + event
12. Refactored `agent_tasks` remote identifiers into single JSON column:
   - `remote.agent_id`, `remote.task_id`, `remote.push_config_id`
   - delegate + webhook reconciliation now query/update via JSON paths
13. Scoped A2A configuration into `inbound` and `outbound` sections:
   - inbound runtime checks and agent card now read from `a2a.inbound.*`
   - outbound runtime reads remain under `a2a.outbound.*`
14. Implemented Sanctum PAT enforcement for inbound A2A:
   - `/api/a2a/rpc` and `/api/a2a/stream` now require `auth:sanctum`
   - added method-level ability gate middleware using `a2a.inbound.auth.method_abilities`
15. Added `agent_tasks` <-> `thread_events` pivot relation:
   - new pivot table `thread_event_agent_tasks`
   - task-aware A2A events now attach to related `agent_tasks` for direct traceability
16. Reframed `thread_events` to execution semantics:
   - added execution fields: `layer`, `kind`, `operation`, `state`, and optional `thread_actor_id`
   - A2A, MCP, observer, and orchestration writes now populate execution fields
   - presenter-bound tool invocations now carry `thread_actor_id` for sub-agent attribution
17. Implemented owner-boundary enforcement for inbound A2A task access:
   - `message/send` now stamps `meta.a2a_owner` from Sanctum-authenticated principal
   - `tasks/get`, `tasks/cancel`, `tasks/resubscribe`, and push-config methods now resolve tasks only if owner matches caller
   - `tasks/list` now filters by owner (`meta.a2a_owner.subject_type` + `subject_id`)

## Exit Criteria
1. Inbound and outbound A2A transport paths operate reliably for send/get/list/cancel/stream workflows.
2. Task lifecycle state is consistently derived from persisted thread/prompt execution data.
3. Owner-boundary authorization is enforced for all task and push-config operations.
4. Inbound push webhook security controls (signature and replay protection) are enforced in runtime.
5. Automated A2A coverage exists for contract shape, lifecycle transitions, auth/owner failures, and streaming/reconnect behavior.
