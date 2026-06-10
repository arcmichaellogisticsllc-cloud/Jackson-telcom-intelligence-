# Operational Capacity Workflow

This workflow turns a discovered ground crew or subcontractor into operational capacity without creating fake data or bypassing review.

## Ground Crew Intake Flow

1. Create a ground crew prospect from Onboarding.
2. Generate the subcontractor intake link.
3. Send the link manually to the subcontractor.
4. The subcontractor submits company, coverage, crew, equipment, service, availability, and document readiness information.
5. Jackson reviews submitted documents and marks each one Approved, Rejected, Requested, or Expired.
6. Jackson completes Compliance Review and Capacity Review.
7. Only after both reviews and required documents are approved can the subcontractor move to Approved, Preferred, or Strategic Partner.
8. Approved capacity syncs into Capacity Profiles and becomes available to the mission spine.

No automated outreach or automatic approval occurs.

## Operational Readiness Gates

Subcontractor readiness is recalculated after:

- intake submission
- document update
- review update
- stage change
- compliance/document sync from the subcontractor network

The recalculation updates:

- onboarding status
- readiness score
- readiness category
- missing items
- risk flags
- next operator action
- capacity profile sync state

## Mission Spine Behavior

Acquire Capacity shows providers that still need intake, documents, compliance review, or capacity review.

Convert To Revenue checks whether open work with weak capacity fit has matching capacity candidates in the same region and discipline. If a match exists, the next action becomes review matching capacity before recruiting from scratch.

## Daily Action Behavior

Onboarding creates one active daily action per onboarding record/category. New events update that action instead of creating duplicate noise.

Example action progression:

- Send subcontractor intake link.
- Review submitted intake.
- Review submitted COI.
- Complete compliance review.
- Complete capacity review.
- Approve, hold, or reject this provider.

## Verification

Run:

```bash
php scripts/check_operational_capacity.php
```

The script creates a temporary ground crew, submits intake, reviews documents, approves capacity, validates revenue matching, and rolls back all data before exiting.
