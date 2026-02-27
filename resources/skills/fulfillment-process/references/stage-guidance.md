# Stage Guidance

## intake
- Collect only minimum scope needed to start fulfillment.
- Create request post when intent is sufficiently clear.
- Use `CreatePostFromConversationTool` with `post_kind=request`.
- Do not imply execution has started.

## request_open
- Confirm scope and constraints for execution handoff.
- Use `references/profile-flow.md` when provider matching is requested.
- If user explicitly wants to start execution, create order post with `post_kind=order`.
- Do not claim quote/payment/assessment writes are done (not supported directly).

## order_active
- Focus on execution updates, blockers, and next operational steps.
- Summarize known state; avoid inventing untracked milestones.
- Escalate unsupported write intents clearly.
