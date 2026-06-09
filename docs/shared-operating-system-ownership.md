# Shared Operating System & Ownership Model

Jackson Platform uses one company workflow.

Mike and Ron do not operate separate systems. The platform maintains one shared company state and uses ownership plus perspective filters to emphasize what each person should act on.

## Doctrine

- One workflow.
- Shared company state.
- Clear primary ownership.
- Clear secondary support.
- Perspective filtering.
- No split-brain operations.

## Perspectives

Company View shows the full operating state: who has work, who has capacity, who needs work, who influences work, and what Jackson should do next.

My Priorities shows records and actions where the current operator is the primary owner.

Shared Priorities shows records where the operator is a secondary owner or the record is explicitly shared.

Company Priorities shows executive-critical items regardless of owner.

## Responsibility Defaults

Mike is primary for strategic accounts, relationships, opportunities, market intelligence, partnerships, and executive packages.

Ron is primary for capacity, subcontractors, workforce, field readiness, preconstruction readiness, and SyncERP handoff readiness.

Southwest, National, major pursuits, critical capacity gaps, strategic decisions, and quarterly reviews remain shared unless transferred.

## Ownership Fields

Major records support:

- `primary_owner`
- `secondary_owner`
- `shared_owner_flag`
- `ownership_notes`

Ownership changes write to the ownership change log, activities, and audit log.

## Operating Rhythm

Daily:

- Company State Review
- My Priorities Review
- Shared Priorities Review

Weekly:

- Mike relationship / opportunity / market review
- Ron capacity / workforce / readiness review
- Joint pursuits / blockers / strategic accounts review

Monthly:

- Strategic Account Review
- Capacity Network Review
- Market Readiness Review

Quarterly:

- Regional Dominance Review
- Investment Review
