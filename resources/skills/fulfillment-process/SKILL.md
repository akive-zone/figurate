---
name: fulfillment-process
description: Run fulfillment orchestration for channel/thread conversations. Use when the agent must derive fulfillment state, enforce participation checks, decide whether to write or retrieve, and create request or order posts using current runtime tools.
---

# Fulfillment Process

Use this skill when handling fulfillment-related user turns.

## Runtime Objective

1. Derive accurate state from domain records.
2. Decide whether the turn is `read-only`, `state-change`, or `escalation`.
3. Perform only supported state writes.
4. Keep timeline and reply content consistent with real writes.

## Current Runtime Capability

1. Supported write actions:
   - Create request post via `CreatePostFromConversationTool` with `post_kind=request`
   - Create order post via `CreatePostFromConversationTool` with `post_kind=order`
2. Skill-owned read/support actions:
   - Flow snapshot derivation (`references/flow-snapshot-contract.md`)
   - Profile matching flow (`references/profile-flow.md`)
   - Stage guidance (`references/stage-guidance.md`)
3. Not currently supported as direct writes:
   - quote submission/acceptance writes
   - assessment upsert/ack writes
   - payment/dispute/rating writes

## Execution Steps

1. Parse user intent.
2. Build flow snapshot from `references/flow-snapshot-contract.md`.
3. Apply decision rules from `references/post-decision-matrix.md`.
4. If stage is `request_open` and user asks for providers, apply `references/profile-flow.md`.
5. If action is supported, call the required tool exactly once.
6. If action is unsupported, return clear next-step or escalation guidance.
7. Never claim a write happened unless tool result confirms success.

## Quality Bar

1. State accuracy first: no inferred IDs, statuses, or timestamps.
2. Permission safety: enforce participation checks before any state change.
3. Determinism: one intent, one chosen action path.
4. Idempotency-aware behavior: avoid duplicate state writes for repeated intent.
5. Traceability: include what record changed and why.

## Guardrails

1. Do not rely on HTTP-specific model wrappers.
2. Do not invent operations not backed by current tools/runtime.
3. Do not create fulfillment posts without participation context.
4. Do not present recommendations as completed actions.
5. Keep payload/meta naming clean and stable.

## References

1. `references/flow-snapshot-contract.md`
2. `references/post-decision-matrix.md`
3. `references/stage-guidance.md`
4. `references/profile-flow.md`
5. `references/active-plan-baseline.md`
