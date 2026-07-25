# Fulfillment Reference Scenario

Fulfillment is not a Figurate interface or enabled module. It is a reference application that can be built on top of Figurate to test whether the platform supports real, multi-party work.

## Scenario

A customer requests a service, such as repairing a door. The application collects the job description, photos, location, urgency, budget, and timing. Providers can respond, the customer can select an offer, and both parties can coordinate execution through completion and settlement.

## Application Resources

The application may define its own:

- Requests
- Quotes
- Assessments
- Orders
- Payments
- Processes
- Ratings
- Disputes
- Service categories
- Messages
- Spaces

These resources belong to the application built on Figurate. They are not part of Figurate's core API.

## Mapping to Figurate

| Application concern | Figurate capability |
| --- | --- |
| One service job and its durable context | Space |
| Intake, negotiation, execution, billing, and dispute conversations | Threads |
| Messages, offers, decisions, receipts, evidence, and status changes | Posts |
| Customer, provider, reviewer, and automated participants | Actors and agents |
| Business rules for quoting, assessment, payment, and disputes | Skills |
| Calls to payment, scheduling, messaging, and external business systems | Tools and channels |
| Relationships between jobs, people, artifacts, and external records | Graph relations |

## Test Flow

1. Create a service request from a conversation.
2. Preserve its context in a space.
3. Open separate threads for intake, quoting, execution, billing, and disputes when needed.
4. Invite the correct participants with scoped access.
5. Record quotes, approvals, payments, evidence, ratings, and decisions as durable posts or application-owned records.
6. Use agents and skills to assist with routing, summarization, review, and next actions.
7. Connect external systems through channels and tools without moving their domain models into Figurate core.

## What This Scenario Should Prove

- Figurate can support an application without absorbing the application's domain model.
- Conversations can become structured, durable work.
- People, agents, devices, and remote services can participate safely.
- Application-specific permissions and workflows can be layered on core context primitives.
- External actions remain traceable to the conversation and decision that caused them.

The scenario should be implemented later as a separate example or integration test application.
