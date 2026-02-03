# Project Plan

Date: 2026-02-03
Time: 21:36:08 WAT

## Goal
Build a cross-platform platform (Mobile + Web) with NativePHP as the shell, Inertia (Signal) and Filament (Studio) as the two main experiences, plus a separate Admin app (Station).

## Scope
1. Signal (Inertia): request flow for device/person users.
2. Studio (Filament): provider onboarding, profiles, order management, progress logging.
3. Station (Filament): approvals, disputes, system oversight.

## Key Roles & User Types
1. System user.
2. Device user (guest, limited access to Signal).
3. Person user (authenticated, access to Signal + Studio).

## Core Entities (Draft)
1. User (type: system, device, person).
2. Profile (provider profile, requires admin approval).
3. ServiceCategory.
4. Request (created in Signal).
5. Quote (created by profile owner).
6. Order (accepted quote; can progress through billing, assessment, fulfillment).
7. Assessment (optional, must be acknowledged).
8. WorkLog (media + text uploads).
9. Payment (partial/full; multiple stages).
10. Rating (mutual).
11. Dispute.

## Phased Plan

### Phase 1: Foundations
1. Confirm product requirements, naming, and final flow definitions.
2. Define database schema and relationships.
3. Define policies/permissions for user types.
4. Set up NativePHP shell layout and app launcher (Blade).
5. Set up Signal (Inertia) and Studio (Filament) routing and navigation skeletons.

### Phase 2: Identity & Access
1. Device user creation and session handling.
2. Socialite login (Google/Apple) and upgrade flow to person user.
3. Access rules for Signal vs Studio vs Station.

### Phase 3: Studio Onboarding
1. Studio signup flow for person users.
2. KYC/KYB capture and verification state.
3. Provider profile creation and admin approval workflow.

### Phase 4: Signal Request Flow
1. Search/browse profiles by category and filters.
2. Request creation against a profile.
3. Notifications to profile owners.

### Phase 5: Quote to Order Lifecycle
1. Quote creation, acceptance, and order creation.
2. Payment handling for partial/full stages.
3. Optional assessment creation and acknowledgment.
4. Work logging and completion.
5. Acceptance/dispute and fulfillment.

### Phase 6: Admin (Station)
1. Profile approvals.
2. Dispute management.
3. Risk flags and manual review tools.

### Phase 7: QA, Performance, and Release
1. Feature tests for all core flows.
2. Mobile testing and NativePHP packaging.
3. Monitoring, logs, and release checklist.

## Milestones
1. M1: Data model + auth + routing skeleton.
2. M2: Studio onboarding + profile approval flow.
3. M3: Signal request + quote + order basics.
4. M4: Full lifecycle with payments, assessment, work logs.
5. M5: Admin station + dispute tools.
6. M6: End-to-end test + mobile packaging.

## Risks & Open Questions
1. Exact payment provider and escrow model.
2. KYC/KYB vendor choice and legal requirements.
3. Dispute resolution policy and data retention.
4. Scope for guest users and what is visible before auth.
5. Notification channels (email, push, in-app) and SLAs.

## Immediate Next Steps
1. Confirm product decisions for KYC/KYB and payments.
2. Finalize user upgrade flow and access matrix.
3. Lock the initial DB schema and create migrations.
