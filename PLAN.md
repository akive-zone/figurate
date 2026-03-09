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
5. Product intent for observer-agent prompting in human threads is documented, but orchestration rules are not yet codified in implementation-facing plan criteria.

### Open Work

1. Move more orchestration/request-shaping logic out of `ChatController` into dedicated actions/services.
2. Decide whether remaining `ServiceRequest`-typed policy coupling should be generalized now or left as-is.
3. Re-verify and close earlier route/provider drift risk after refactor history.
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
3. Evaluate optional Reverb-backed replay for stream/resubscribe path.

### Exit Criteria Check

1. Inbound/outbound transport supports send/get/list/cancel/stream reliably: Met.
2. Task lifecycle state is derived from persisted execution data: Met.
3. Owner-boundary authorization is enforced: Met.
4. Inbound push webhook signature + replay protection enforced: Not met (replay still open).

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

1. Catalog governance is implemented structurally, but production catalog policy/content governance is still open.

### Open Work

1. Tighten backend handling paths for malformed/unsupported A2UI payloads and capability mismatches.
2. Finalize backend policy decisions for allowed catalogs and stricter inline-catalog acceptance rules by environment.
3. Add operational guidance for backend A2UI troubleshooting and payload auditability.

### Exit Criteria Check

1. Backend accepts and normalizes supported A2UI payloads safely: Met.
2. Backend persists and re-projects A2UI interaction context consistently: Met.
3. Backend catalog policy/governance is fully locked for production: Not met.

---

## Plan Entry: AI Interop Security and Trust (Backend)

Date opened: 2026-03-08  
Current status: Open

### Goal
Define and deliver backend trust boundaries for A2A and MCP flows so integrations are secure by default across environments.

### Planned Scope

1. Inbound A2A webhook trust hardening (replay-window enforcement and explicit deny behavior).
2. Outbound callback trust policy (environment-aware URL restrictions and optional allowlist controls).
3. Clear token/ability boundary model for machine-to-machine A2A operations.
4. MCP invocation trust boundaries (allowlist, policy enforcement, and failure behavior standards).

### Exit Criteria

1. A2A inbound webhook trust policy is fully enforced in runtime.
2. Outbound callback trust policy is implemented and environment-aware.
3. A2A ability boundaries are documented and aligned with runtime behavior.
4. MCP trust boundaries are locked and consistently applied.

---

## Plan Entry: A2UI Catalog Governance (Backend)

Date opened: 2026-03-08  
Current status: Open

### Goal
Lock backend governance for A2UI catalogs so payload behavior is predictable, policy-controlled, and production-safe.

### Planned Scope

1. Define allowed catalog IDs by environment and rollout strategy.
2. Define inline catalog acceptance policy by environment.
3. Define backend behavior for unsupported catalog references and policy denials.
4. Align agent-card A2UI extension metadata with enforced backend policy.

### Exit Criteria

1. Catalog allow/deny policy is production-ready and environment-scoped.
2. Inline catalog policy is explicitly enforced.
3. Unsupported catalog behavior is deterministic and documented.
4. Advertised A2UI capabilities match actual backend enforcement.

---

## Plan Entry: A2A Ownership and Tenancy Evolution (Backend)

Date opened: 2026-03-08  
Current status: Open

### Goal
Evolve task ownership boundaries from principal-only scoping to tenancy-ready boundaries without breaking current integrations.

### Planned Scope

1. Document current principal-based owner model and constraints.
2. Define target org/workspace-aware ownership model for A2A task access.
3. Define migration path for owner metadata and task filtering semantics.
4. Preserve backward compatibility expectations for existing machine clients.

### Exit Criteria

1. Target tenancy ownership model is locked.
2. Migration approach is defined and sequenced.
3. Runtime authorization semantics are clearly versioned for clients.
4. Existing principal-bound behavior remains stable during transition.

---

## Plan Entry: AI Interop Operations and Recovery (Backend)

Date opened: 2026-03-08  
Current status: Open

### Goal
Define backend operational behavior for interop failures and recovery so A2A/MCP flows are supportable in production.

### Planned Scope

1. Define task lifecycle recovery behavior for delayed/failed callbacks and stream interruptions.
2. Define operator-visible status and failure states for remote task linkage.
3. Define backend retry/escalation policy boundaries for push and remote task sync.
4. Define minimum observability events needed for production support and incident triage.

### Exit Criteria

1. Recovery behavior is deterministic for common failure modes.
2. Operator-facing status model is defined for task linkage and sync state.
3. Retry/escalation policy is explicit and enforced where required.
4. Observability requirements are documented and aligned with runtime events.

---

## Plan Entry: A2A/A2UI Compatibility and Versioning (Backend)

Date opened: 2026-03-08  
Current status: Open

### Goal
Define backend compatibility policy for A2A/A2UI spec evolution so integrations remain stable as protocols change.

### Planned Scope

1. Define compatibility window and support policy for canonical + legacy method aliases.
2. Define versioned behavior for payload shape evolution and deprecation timelines.
3. Define rollout expectations for agent-card capability/version signaling.
4. Define policy for introducing and retiring compatibility shims.

### Exit Criteria

1. Compatibility policy is explicit and version-aware.
2. Deprecation approach is defined with stable client expectations.
3. Agent-card signaling is aligned with supported runtime behavior.
4. Shim lifecycle policy is clear for future protocol changes.
