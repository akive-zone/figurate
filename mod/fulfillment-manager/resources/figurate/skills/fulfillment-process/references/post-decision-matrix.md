# Fulfillment Post Decision Matrix

Use this matrix to decide the correct action for a fulfillment turn.

## Action Classes

1. `WRITE_SUPPORTED`
2. `READ_ONLY_SKILL`
3. `ESCALATE_UNSUPPORTED_WRITE`

## Required Preconditions (Any Write)

1. Resolve `thread` and `channel` context.
2. Resolve request subject post from thread/channel.
3. Confirm actor participation context.
4. Confirm intent requires a state change.

If any precondition fails: stop and return an error or clarification request.

## Supported Writes (Current Runtime)

1. Create request from conversation intent
   - Class: `WRITE_SUPPORTED`
   - Tool: `CreatePostFromConversationTool`
   - Parameters:
     - `intent=subject`
     - `subject.title` and/or `subject.description`
     - optional `status`

2. Create order post from existing request context
   - Class: `WRITE_SUPPORTED`
   - Tool: `CreatePostFromConversationTool`
   - Parameters:
     - `intent=execution`
     - optional `title`, `description`, `status`

## Skill-Owned Read/Support Cases

1. User asks current stage/status/timeline
   - Class: `READ_ONLY_SKILL`
   - Action: derive flow snapshot using `flow-snapshot-contract.md`

2. User asks for possible workers/providers
   - Class: `READ_ONLY_SKILL`
   - Action: run profile matching from `profile-flow.md`

3. User asks what to do next
   - Class: `READ_ONLY_SKILL`
   - Action: apply `stage-guidance.md`

## Unsupported Writes (Escalate)

1. Quote submission or quote acceptance workflow with financial details
2. Assessment upsert or assessment acknowledgement writes
3. Payment recording writes
4. Dispute open/resolve writes
5. Rating writes

For all above:
1. Class: `ESCALATE_UNSUPPORTED_WRITE`
2. Action: clearly state that current runtime does not expose a direct write path for this operation.
3. Provide next step (manual process, admin path, or wait for workflow enablement).

## Response Rules by Action Class

1. `WRITE_SUPPORTED`
   - Call tool.
   - Report result with created identifiers and status.
   - If tool returns `created=false`, report idempotent existing state.

2. `READ_ONLY_SKILL`
   - Do not call write tool.
   - Return grounded summary/recommendation from skill references.

3. `ESCALATE_UNSUPPORTED_WRITE`
   - Do not simulate completion.
   - Do not fabricate IDs/status transitions.
   - Return explicit escalation guidance.

## Role Guardrails

1. Only participants can trigger fulfillment writes.
2. Channel membership is required for conversation-origin writes.
3. If role or participation is unclear, request clarification before writing.

## Idempotency Guardrails

1. Prefer existing request/order state when tool reports already created.
2. Avoid repeated writes for repeated user restatements in same stage.

## Timeline Guardrails

1. System timeline messages must reflect actual tool outcomes only.
2. For read-only turns, do not emit synthetic state-change messages.
