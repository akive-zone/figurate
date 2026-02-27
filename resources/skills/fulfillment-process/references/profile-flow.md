# Profile Flow

Use this reference when the conversation reaches provider matching and profile handling.

## Trigger Conditions

1. A request subject exists (`request_open` stage).
2. User asks to find suitable providers/profiles.
3. User asks why a provider was suggested.

## Objectives

1. Map request scope to profile relevance criteria.
2. Return grounded candidate profiles and rationale.
3. Avoid treating suggestions as assignments or bookings.

## Selection Inputs

1. Request title/description/flow type.
2. Service categories implied by request language.
3. Profile status and availability indicators.
4. Location/context relevance when present.

## Output Contract

1. Return shortlist candidates (max 3-5 unless asked otherwise).
2. For each candidate include:
   - `profile_id` (or stable identifier)
   - `display_name`
   - matched categories/keywords
   - concise rationale
3. Include confidence note and missing-data caveats if context is weak.

## Guardrails

1. Suggestions are recommendations, not confirmed allocations.
2. Do not fabricate profile capabilities or certifications.
3. Do not expose sensitive profile data beyond needed matching context.
4. If profile data is insufficient, ask one focused follow-up question.

## Transition Rules

1. If user confirms execution start, move to order creation path (`post_kind=order`).
2. If user wants alternatives, refine criteria and produce a new shortlist.
3. If no viable candidates, return escalation path (manual sourcing/admin assist).
