# Delivery Plan Status

Last updated: 2026-03-14

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
5. Shared orchestration primitives now exist for prompt dispatch, presenter resolution, queue fan-out, and task snapshot/cancel handling (`PromptDispatchService`, `MessageTaskService`, `AgentTaskService`).
6. Orchestration behavior is largely request-type agnostic and no longer centered on `ServiceRequest` controller flow.

### Partially Completed

1. Thin-controller objective is improved but not fully complete (`ChatController` still owns significant orchestration and shaping logic).
2. Legacy naming cleanup is mostly done, but some `Signal` wording remains.
3. Bigint-over-UUID internal consistency improved, but prior risk remains for older paths.
4. Product intent for observer-agent prompting in human threads is documented, but orchestration rules are not yet codified in implementation-facing plan criteria.

### Open Work

1. Move more orchestration/request-shaping logic out of `ChatController` into dedicated actions/services.
2. Decide whether remaining `ServiceRequest`-typed policy coupling should be generalized now or left as-is.
3. Re-verify and close earlier route/provider drift risk after refactor history.
4. Define and implement observer-agent orchestration for human-participant threads, including:
   - allow/deny gating for observer prompting,
   - support for multiple observer agents in a single thread,
   - clear mode selection between presenter-response and observer-prompting flows.
5. Add feature coverage for observer-agent orchestration cases (single-user human thread and multi-user human thread).

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
7. Local A2A task lifecycle is now persisted through `agent_tasks`, with send/get/list/cancel backed by `AgentTaskService` and synchronized from prompt/execution data instead of prompt-message metadata alone.
8. Execution modeling is in place with `thread_events` + `agent_tasks` linkage for traceability.
9. A2UI and MCP integration baseline exists in runtime/tooling and is operational.

### Partially Completed

1. Inbound webhook security is partially hardened:
   - signature validation is configured,
   - timestamp freshness/replay protection is not yet enforced,
   - explicit deny-reason logging is still pending.
2. Local A2A task persistence is implemented, but edge-case hardening is still open for mixed outcomes, sync semantics, and broader transport coverage around the new `AgentTask`-backed lifecycle.

### Open Work

1. Complete inbound webhook replay/timestamp controls and deny-path observability.
2. Implement outbound callback URL trust policy (environment-aware URL restrictions and optional allowlist).
3. Evaluate optional Reverb-backed replay for stream/resubscribe path.
4. Add broader feature coverage for `AgentTask`-backed A2A get/list/cancel/sync behavior, especially around cancellation, partial completion, and owner-scoped task lookup.

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
5. Chat/prompt message persistence captures A2UI metadata (actions/errors/client capabilities/data model) for runtime continuity, including the shared A2A prompt-dispatch path.
6. A2A routing and task artifact shaping support A2UI MIME handling with transport edge-case unit coverage.
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
Current status: Partially Completed

### Goal
Define backend operational behavior for interop failures and recovery so A2A/MCP flows are supportable in production.

### Completed

1. Local interop task status is now persisted in `agent_tasks` for ACP and local A2A flows, with prompt-linked snapshot data available for status inspection and sync.
2. Runtime task state now updates from shared message/execution snapshot logic, improving consistency between submission, completion, and cancellation views.

### Partially Completed

1. Operator-visible local task linkage is improved through persisted `agent_tasks`, but remote-linkage status modeling and recovery policy remain undefined.

### Open Work

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

---

## Plan Entry: ACP Sessions and Task Bridge

Date opened: 2026-03-14  
Current status: Partially Completed

### Goal
Deliver an authenticated ACP session/task API and bridge runtime so external ACP clients can create sessions, prompt agents, inspect task state, and cancel work using the existing thread orchestration model.

### Completed

1. Authenticated ACP API routes are implemented for session list/create/show, session prompt, task show, and task cancel.
2. ACP request validation and input normalization are implemented for common session/channel/prompt field aliases.
3. ACP session creation is implemented on top of channel-backed threads, including default purpose/title/phase resolution, presenter provisioning, membership enforcement, and active-thread tracking.
4. ACP prompt submission creates first-class local `agent_tasks` with persisted owner metadata, status snapshots, and prompt linkage.
5. ACP task access and cancellation are enforced through owned `AgentTask` resolution rather than prompt-message lookup alone.
6. Reusable orchestration services are implemented for prompt dispatching, local task sync, and message-task state/artifact projection (`PromptDispatchService`, `AgentTaskService`, `MessageTaskService`).
7. Existing A2A task routing now reuses the shared orchestration/task services, reducing duplicate task-state logic across ACP and A2A flows.
8. A Go ACP bridge runtime exists in `mod/fig-acp` with JSON-RPC methods for initialize/authenticate/session new/list/load/prompt/cancel.
9. The Go bridge can authenticate against the backend, create/load/list sessions, submit prompts, poll ACP task state to completion, and issue downstream task cancellation.
10. Feature coverage exists for ACP session create/list/load and prompt/cancel behavior, including persisted `AgentTask` assertions, with related A2A transport tests updated for the shared task-artifact behavior.

### Partially Completed

1. ACP task lifecycle behavior is usable but not yet fully hardened:
   - completion currently depends on polling task snapshots,
   - cancellation updates task metadata but does not represent deeper executor/job interruption guarantees.
2. ACP contract normalization is implemented, but the external client contract is not yet explicitly locked or documented.
3. Bridge runtime usability is implemented, but there is no automated Go test coverage or release/distribution workflow yet.
4. Shared orchestration extraction is in place, but broader controller-thinning and orchestration-boundary cleanup outside ACP remains open.

### Open Work

1. Add ACP failure-path and authorization coverage for forbidden channel/session access, invalid channel-thread pairing, timeout behavior, failed task states, and multi-presenter cases.
2. Decide and codify ACP task-state semantics for mixed presenter outcomes, partial failure, timeout, and cancellation after partial completion.
3. Add observability for ACP prompt submission, bridge polling failures, downstream cancellation failures, and task-state transitions.
4. Decide whether ACP should remain poll-based or gain a backend push/stream path for lower-latency task updates.
5. Lock the ACP external API contract for accepted aliases and response payloads before wider client adoption.
6. Add Go-side tests and CI/build hygiene for `mod/fig-acp`, including a clean story for local cache/output handling.

### Exit Criteria Check

1. ACP clients can create, list, load, prompt, inspect, and cancel sessions/tasks end-to-end through authenticated APIs: Mostly met.
2. ACP and A2A share consistent prompt/task orchestration primitives instead of duplicated logic: Met.
3. ACP bridge runtime can complete the prompt lifecycle reliably against the backend: Mostly met.
4. ACP behavior is hardened with failure-path coverage, observability, and locked client contract semantics: Not met.
