# Delivery Plan Status

Last updated: 2026-03-08

This file is now a live status check of existing plan entries, based on the current codebase.

## Plan Entry: Chat Orchestration

Date opened: 2026-02-21  
Current status: Partially Completed

### Goal
Stabilize chat orchestration and fulfillment execution around Laravel AI primitives, with clean naming and thin HTTP boundaries.

### Completed

1. Laravel AI queue + broadcast execution path is active from HTTP message submission.
2. Idempotent message submission behavior is implemented.
3. Thread resolution behavior is implemented:
   - explicit thread is respected when valid,
   - fallback resolves from channel scope,
   - invalid/closed/inaccessible explicit threads fail fast.
4. Backend class names stayed aligned with the decision (`ChatToolResolver`, `ChatAgentExecutor`).
5. Orchestration behavior is largely request-type agnostic and no longer centered on `ServiceRequest` controller flow.

### Partially Completed

1. Thin-controller objective is improved but not fully complete (`ChatController` still owns significant orchestration and shaping logic).
2. Legacy naming cleanup is mostly done, but some `Signal` wording remains.
3. Bigint-over-UUID internal consistency improved, but prior risk remains for older paths.
4. Planned feature test coverage for controller contract/idempotency/thread resolution/queue+broadcast is not yet complete.
5. Product intent for observer-agent prompting in human threads is documented, but orchestration rules are not yet codified in implementation-facing plan criteria.

### Open Work

1. Move more orchestration/request-shaping logic out of `ChatController` into dedicated actions/services.
2. Add missing feature tests for controller contract, idempotency, thread resolution, and queue+broadcast behavior.
3. Decide whether remaining `ServiceRequest`-typed policy coupling should be generalized now or left as-is.
4. Re-verify and close earlier route/provider drift risk after refactor history.
5. Define and implement observer-agent orchestration for human-participant threads, including:
   - allow/deny gating for observer prompting,
   - support for multiple observer agents in a single thread,
   - clear mode selection between presenter-response and observer-prompting flows.
6. Add feature coverage for observer-agent orchestration cases (single-user human thread and multi-user human thread).

### Exit Criteria Check

1. Chat controller is thin and transport-only: Not met.
2. Orchestration is domain-driven and request-type agnostic: Mostly met.
3. Queue + broadcasting is Laravel AI-native: Met.
4. Naming is fully consistent with no legacy terminology in active UI surfaces: Not met.
5. Observer-agent prompting behavior is implemented and validated for human-thread cases, including multi-observer scenarios: Not met.

---

## Plan Entry: A2A Protocol

Date opened: 2026-03-02  
Current status: Partially Completed (core delivery complete, hardening/testing open)

### Goal
Implement A2A-compliant transport and task lifecycle on top of existing thread orchestration.

### Completed

1. Inbound A2A transport is implemented and active (`agent-card`, `rpc`, `stream`, push webhook endpoint).
2. Inbound auth model is implemented with Sanctum PAT + per-method abilities.
3. Owner boundary for task access is implemented and enforced across task and push-config operations.
4. Outbound A2A tooling and remote delegation flow are implemented.
5. Push notifications are implemented for outbound dispatch and inbound reconciliation.
6. Streaming behavior is implemented for `message/stream` and `tasks/resubscribe`.
7. Execution modeling is in place with `thread_events` + `agent_tasks` linkage for traceability.
8. A2UI and MCP integration baseline exists in runtime/tooling and is operational.

### Partially Completed

1. Inbound webhook security is partially hardened:
   - signature validation is configured,
   - timestamp freshness/replay protection is not yet enforced,
   - explicit deny-reason logging is still pending.

### Open Work

1. Complete inbound webhook replay/timestamp controls and deny-path observability.
2. Implement outbound callback URL trust policy (environment-aware URL restrictions and optional allowlist).
3. Expand automated A2A contract/security coverage (shape, lifecycle, auth/owner failures, streaming/reconnect, webhook trust checks).
4. Evaluate optional Reverb-backed replay for stream/resubscribe path.

### Exit Criteria Check

1. Inbound/outbound transport supports send/get/list/cancel/stream reliably: Met.
2. Task lifecycle state is derived from persisted execution data: Met.
3. Owner-boundary authorization is enforced: Met.
4. Inbound push webhook signature + replay protection enforced: Not met (replay still open).
5. Automated A2A contract/security coverage across core paths: Not met.

---

## Plan Entry: A2UI (Backend)

Date opened: 2026-03-05  
Current status: Partially Completed

Scope note: This entry is backend-only. Frontend A2UI delivery is tracked separately.

### Goal
Implement backend A2UI compatibility for inbound/outbound agent interactions, validation, metadata capture, and protocol-safe payload handling.

### Completed

1. A2UI payload contract and normalization are implemented (`A2uiPayloadContract`).
2. A2UI catalog registry and capability-based decoration are implemented (`A2uiCatalogRegistry`).
3. Agent card advertises A2UI extension metadata and supported catalog capability fields.
4. Chat request validation accepts and normalizes A2UI client config and surface payload fields.
5. Chat message persistence captures A2UI metadata (actions/errors/client capabilities/data model) for runtime continuity.
6. A2A routing supports A2UI MIME handling and artifact behavior with transport edge-case unit coverage.
7. Server-side turn projection includes assistant A2UI payload metadata for client consumption.

### Partially Completed

1. Backend contract/security test coverage is still narrow (current tests focus on router transport edge cases).
2. Catalog governance is implemented structurally, but production catalog policy/content governance is still open.

### Open Work

1. Add broader backend tests for A2UI validation, normalization, persistence, and response projection paths.
2. Add negative-path tests for malformed/unsupported A2UI payloads and capability mismatches.
3. Finalize backend policy decisions for allowed catalogs and stricter inline-catalog acceptance rules by environment.
4. Add operational guidance for backend A2UI troubleshooting and payload auditability.

### Exit Criteria Check

1. Backend accepts and normalizes supported A2UI payloads safely: Met.
2. Backend persists and re-projects A2UI interaction context consistently: Met.
3. Backend enforces mature test coverage for A2UI contract and failure paths: Not met.
4. Backend catalog policy/governance is fully locked for production: Not met.
