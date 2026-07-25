# Delivery Plan Status

Last updated: 2026-03-17

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

## Plan Entry: AI MCP Gateway

Date opened: 2026-03-15  
Current status: Partially Completed

### Goal
Establish a usable MCP gateway for Figurate, covering both the first-party Figurate MCP server and outbound MCP tool invocation from chat/runtime context.

### Completed

1. First-party Figurate MCP server is implemented and exposed at `/mcp/figurate`.
2. MCP web access is protected behind `EnsureDeviceUser` and `auth:sanctum`.
3. The Figurate server publishes a concrete capability surface for channels/threads/posts/actors/context, including:
   - read/list tools,
   - `create_thread`,
   - `create_post`,
   - `assign_thread_actor`,
   - `transfer_thread_session`,
   - guide/resource payloads,
   - planning/summarization prompts.
4. Server-side MCP payload shaping and authorization are implemented through shared gateway support (`FigurateMcpPayloads`) with policy checks against accessible channels/threads.
5. Chat runtime MCP tooling is implemented behind `services.mcp.enabled`, including discovery (`ListAvailableMcpToolsTool`) and invocation (`InvokeMcpTool`).
6. Outbound MCP server resolution supports both config-defined and persisted context servers, with overrides at user/channel/thread scope via `context_servers`.
7. Outbound MCP invocation supports both remote endpoint transport and local handler transport, including allowlisted tools, timeout bounds, optional headers, and normalized response envelopes.
8. MCP invocation outcomes are recorded to `thread_events` with MCP-specific event typing for execution traceability.
9. Context-server CRUD and registration flows exist so MCP endpoints/handlers can be attached to user, channel, and thread context.
10. Feature coverage exists for the first-party Figurate MCP server capability surface.

### Partially Completed

1. Core gateway behavior is implemented, but outbound MCP hardening is still incomplete:
   - tool allowlisting exists,
   - endpoint/handler presence checks exist,
   - broader trust policy and environment-aware restrictions are still open.
2. First-party server coverage exists, but outbound resolver/client/policy coverage is still thin compared with the implemented surface.
3. The Figurate MCP server is operational for chat-context inspection and safe workflow actions, but broader product workflow coverage is intentionally narrow and excludes fulfillment-state mutation.

### Open Work

1. Add focused unit/feature coverage for outbound MCP resolution, invocation policy, remote failure normalization, and `ContextServerController` edge cases.
2. Define and enforce outbound MCP trust rules for remote endpoints, credential/header policy, and environment-aware allowlisting.
3. Decide whether remote MCP integration should remain config/allowlist driven or gain capability discovery/schema-sync behavior.
4. Define clearer operator-facing observability for MCP endpoint failures, denial reasons, latency, and retry/no-retry behavior.
5. Decide whether the first-party Figurate MCP server should expand beyond chat/workflow support into additional domain mutations, or remain intentionally constrained.

### Exit Criteria Check

1. First-party Figurate MCP server is usable for supported chat/workflow operations: Met.
2. Chat runtime can discover and invoke scoped MCP tools through a unified gateway: Met.
3. MCP trust policy is fully enforced for outbound integrations: Not met.
4. Gateway test coverage is broad enough for inbound and outbound confidence: Not met.

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

## Plan Entry: Account, User, and Gadget Identity Architecture

Date opened: 2026-03-16  
Current status: Open

### Goal
Separate durable human ownership from acting runtime principals so Figurate can preserve same-gadget continuity without destructive user merges, while still allowing cross-gadget continuation through a shared account.

### Decision Summary

1. `users` remains the acting-principal table, not the durable human-identity table.
2. User types should be actor-oriented (`robot`, `gadget`, `subject`, `system`, etc.), replacing the ambiguous `device` terminology with `gadget`.
3. `accounts` becomes the durable human ownership layer.
4. `account_users` should link accounts to acting users instead of embedding `account_id` directly on `users`.
5. `user_agents` should track the concrete hardpoint and request metadata for the gadget/client/app-install/browser making requests on behalf of a given `user`.
6. A gadget user that authenticates should remain a `gadget` user and be attached to an `account`, not promoted in place to `subject`.
7. `subject` should remain reserved until there is a concrete runtime use case that is distinct from both gadget-origin actors and durable account ownership.
8. Cross-gadget continuity should be derived from account-linked actors, while actor provenance should remain attached to the specific acting `user`.

### Reasoning

1. The current merge-heavy model treats login as a destructive identity transition, which is fragile because pre-auth gadget activity and post-auth account continuity are different concerns.
2. Keeping `users` as principals fits the product better because the table already represents non-human actors such as model and system users; overloading it as the durable account layer makes the semantics inconsistent.
3. Promoting the same gadget user in place gives excellent same-gadget continuity, but it breaks down when the same human later continues from another gadget and also overloads `subject` before its semantics are clear.
4. Introducing `accounts` plus `account_users` preserves the best part of the current model:
   - same-gadget continuity can keep the same `user_id`,
   - cross-gadget continuity can be granted through shared account ownership.
5. Treating the device as the hardpoint and the user-facing/runtime actor as the softpoint gives `user_agents` a concrete job: device/install/browser provenance belongs there instead of on the durable account layer.
6. Keeping authenticated gadget users as gadgets avoids destructive type churn and preserves the ability to introduce `subject` later for a genuinely different runtime actor.
7. This architecture also makes future org/workspace/tenancy evolution cleaner because durable ownership and acting principals are no longer the same axis.

### Canonical Scenario

1. A person opens the app anonymously on gadget A.
2. Figurate creates `user(type=gadget)` for that gadget and associates request provenance through `user_agents`.
3. Anonymous work such as threads, tasks, sessions, and conversations is created under that gadget user.
4. The person then authenticates with an account.
5. Instead of creating a separate durable `subject` user and destructively merging all prior work into it, Figurate should:
   - resolve or create the `account`,
   - attach the current gadget user to that account through `account_users`,
   - keep previously created work attached to its original `user_id`,
   - derive account continuity later by following the acting user back through `account_users`.
6. The current gadget keeps using the same `user_id` and remains type `gadget`, so same-gadget continuity is preserved.
7. Later, gadget B can create a second `gadget` user and attach it to the same account.
8. Cross-gadget continuation then works by resolving which account-linked actors can see or continue a resource, while actor provenance still records which gadget user actually performed each action.

### Target Model

1. `users`
   - acting principals only,
   - includes `gadget`, `robot`, `subject`, `system`, and future runtime actor types,
   - authenticated gadget actors remain `gadget` unless and until a distinct `subject` runtime need is defined.
2. `accounts`
   - durable human ownership and login identity.
3. `account_users`
   - account-to-user link table,
   - should support relationship metadata such as `gadget`, `operator`, `owner`, `is_primary`, `linked_at`, and `unlinked_at`.
4. `user_agents`
   - request/gadget/client provenance for a user,
   - should track the hardpoint identity and related device metadata such as device identifier, platform, app version, user agent, and last seen time.

### Ownership Rules

1. `user_id` answers "which actor performed this action?"
2. Durable resources should stay actor-scoped by `user_id` or existing actor relations unless there is a stronger domain reason to persist a separate ownership key.
3. Same-gadget continuity should prefer the current gadget user when resuming live/local state.
4. Cross-gadget continuation should be resolved by the domain using `account_users` and actor membership rather than storing `account_id` on channels, threads, tasks, or sessions.
5. `subject` should not be required for account login flows; account attachment should be sufficient for authenticated gadget continuity.

### Actor-Scoped Rule

1. `channels`, `threads`, `agent_tasks`, `agent_conversations`, and `thread_actor_sessions` should remain actor-scoped.
2. If later domain logic needs account-aware continuation, it should derive that through actor membership and account linkage rather than a persisted `account_id` on those rows.
3. Lower-level child records such as messages and thread events should remain actor-scoped as well.

### Planned Refactor Direction

1. Rename `device` terminology to `gadget` across auth/runtime flows.
2. Add `accounts`.
3. Add `account_users`.
4. Add `user_agents` as the hardpoint/provenance layer and move gadget lookup toward that table.
5. Keep durable, resumable resources actor-scoped and derive account context through `account_users` when needed.
6. Replace merge-on-login behavior with account attachment while keeping authenticated gadget users as gadgets.
7. Reserve `subject` until its runtime semantics are concrete enough to justify actual promotion or dedicated flows.
8. Move passkey/social/durable human-auth bindings toward `accounts` rather than treating a `person` or `subject` user row as the durable identity.
9. Keep token/runtime actor resolution user-scoped so auditability and transport checks continue to operate on the acting principal.

### Current Risks to Address

1. Existing merge actions are optimized for user-to-user migration and will not remain the right abstraction once account linking replaces destructive merges.
2. Several current auth flows still assume a `device -> person` promotion path; those assumptions will need to move to `gadget -> account attachment`, with `subject` remaining unused until explicitly needed.
3. `user_agents` naming may still be confused with AI-agent concepts unless code/docs stay explicit about it representing client/gadget provenance.
4. Domain services will need clear rules for deriving account continuity from actor membership instead of relying on a direct `account_id` shortcut.

### Exit Criteria

1. Identity model is explicitly split between acting users and durable accounts.
2. Same-gadget login no longer requires destructive user merges.
3. Cross-gadget continuation works through account-linked actor resolution instead of persisted account ownership on runtime resources.
4. Request provenance remains attributable to the exact acting gadget/model/system user.
5. Terminology and runtime rules are updated consistently from `device/person` semantics to `gadget/account` semantics where appropriate.
6. Hardpoint/device provenance is tracked through `user_agents` instead of relying on `users` as the primary hardpoint record.

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
8. The ACP bridge runtime has moved to Xallet as `backend/xallet-acp-bridge`, with JSON-RPC methods for initialize/authenticate/session new/list/load/prompt/cancel.
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
6. Add Go-side tests and CI/build hygiene for Xallet's `backend/xallet-acp-bridge`, including a clean story for local cache/output handling.

### Exit Criteria Check

1. ACP clients can create, list, load, prompt, inspect, and cancel sessions/tasks end-to-end through authenticated APIs: Mostly met.
2. ACP and A2A share consistent prompt/task orchestration primitives instead of duplicated logic: Met.
3. ACP bridge runtime can complete the prompt lifecycle reliably against the backend: Mostly met.
4. ACP behavior is hardened with failure-path coverage, observability, and locked client contract semantics: Not met.

---

## Plan Entry: Fig Node Coordination Runtime

Date opened: 2026-06-29
Current status: Open

### Goal

Tighten Fig into a deployable coordination node that can sit beside existing chat, ERP, CRM, CMS, ticketing, fulfillment, and enterprise collaboration systems while providing issue tracking, change review, durable work memory, and agent-assisted orchestration.

### Current Direction

Fig should not be treated as another chat application. Chat is a surface and input. The core runtime is the work graph and coordination layer behind existing systems:

- `Space` represents long-lived work context.
- `Thread` represents an active session or workstream.
- `Post` represents durable artifacts, events, decisions, reviews, and updates.
- `Channel` and `ChannelRoute` connect Fig to external systems and route inbound/outbound work.
- Agents, skills, tools, MCP, A2A, ACP, webhooks, and WebSockets provide interoperability and orchestration.

### Planned Scope

1. Lock the channel/route/address model around external-system overlays:
   - inbound messages and events,
   - outbound updates and callbacks,
   - route-specific addresses,
   - route/channel/address skill context.
2. Define the issue-tracking shape shared by fulfillment, ticket resolution, change review, and enterprise collaboration:
   - status,
   - owner,
   - participants,
   - priority,
   - source system,
   - linked artifacts,
   - resolution outcome.
3. Define change-review artifacts as first-class posts:
   - proposed change,
   - review summary,
   - risk flags,
   - approval/denial,
   - applied/rolled-back state.
4. Tighten channel ingress so external events can deterministically resolve:
   - owning channel,
   - route,
   - address,
   - space,
   - thread,
   - actor,
   - applicable skills.
5. Define observer-agent behavior for human and group threads:
   - when observers are allowed,
   - how multiple observers participate,
   - when observation becomes a review post,
   - how actions require approval.
6. Add test coverage for the node-overlay contract:
   - webhook route ingress,
   - skill context inheritance,
   - outbound delivery metadata,
   - issue/change-review post shaping,
   - authorization boundaries for external actors.

### Exit Criteria

1. A Fig node can be attached to an external system through channels/routes and reliably map inbound events into spaces, threads, posts, actors, and skills.
2. Fulfillment, ticket resolution, and change review can share the same core issue/work tracking model without special-case controller flows.
3. Human conversation, agent observation, and tool execution produce durable reviewable artifacts rather than transient-only chat turns.
4. External-system callbacks and outbound deliveries include enough metadata to correlate work across systems.
5. Authorization and trust boundaries are explicit for inbound routes, outbound callbacks, MCP tools, A2A/ACP clients, and observer agents.
