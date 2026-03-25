# Active Plan Baseline

Date baseline: 2026-02-21 (`PLAN.md`)

## Goal
Stabilize chat orchestration and fulfillment execution around Laravel AI primitives with thin HTTP boundaries.

## Locked Constraints

1. Queue + broadcast handler-agent execution from HTTP entrypoints.
2. Minimal controller payload (`space`, `thread_actor`, `message`).
3. Thread resolution: explicit thread id wins; fallback only when missing.
4. Non-HTTP internals prefer bigint ids.
5. Keep orchestration request-type agnostic.

## Exit Direction

1. Transport-only controller.
2. Deterministic orchestration.
3. Clean client naming with no legacy drift.
