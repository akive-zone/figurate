# Flow Snapshot Contract

Build a deterministic flow snapshot from persisted state.

## Required Reads

1. Resolve space from thread context.
2. Resolve request subject post from thread/space.
3. Resolve current order linked to request (if present).
4. Resolve actor participation scope.

## Access Rules

1. If request subject exists: actor must be a participant of request context.
2. If request subject does not exist: actor must be a space member.
3. On failed access check: return `ok:false` with explicit error.

## Stage Inference

1. No request subject: `intake`
2. Request subject exists and no order: `request_open`
3. Request subject exists and order exists: `order_active`

## Snapshot Shape

1. `stage`
2. `space`: `id`, `uuid`, `status` or `null`
3. `request`: `id`, `ulid`, `type`, `status`, `title`, `description` or `null`
4. `thread`: `id`, `uuid`, `purpose`, `phase`, `status`
5. `order`: `id`, `status` or `null`
6. `recommended_next_actions`: stage-aligned action list

## Recommended Next Actions

1. `intake`
   - Collect minimum scope details.
   - Create request post from conversation.
   - Optionally suggest profiles once request exists.

2. `request_open`
   - Confirm scope and constraints.
   - Suggest profiles when asked.
   - Create order post only when user confirms execution start.

3. `order_active`
   - Track execution progress and blockers.
   - Provide actionable next steps.
   - Escalate unsupported state writes.

## Output Rules

1. Snapshot is read/decision context; it must not create records.
2. Any state change must come from a successful tool response.
3. If a write tool is unavailable for intent, mark as escalation path.
