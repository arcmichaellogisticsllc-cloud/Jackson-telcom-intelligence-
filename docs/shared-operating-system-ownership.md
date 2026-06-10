# Shared Operating System & Ownership Model

Jackson Platform uses one company workflow.

The platform does not operate separate person-based systems. It maintains one shared company state and uses ownership plus perspective filters to emphasize what each operator should act on.

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

The Relationship / Opportunity Owner is primary for strategic accounts, relationships, opportunities, market intelligence, partnerships, and executive packages.

The Capacity / Readiness Owner is primary for capacity, subcontractors, workforce, field readiness, preconstruction readiness, and SyncERP handoff readiness.

Southwest, National, major pursuits, critical capacity gaps, strategic decisions, and quarterly reviews remain shared unless transferred.

## Ownership Fields

Major records support:

- `primary_owner`
- `secondary_owner`
- `shared_owner_flag`
- `ownership_notes`

Ownership changes write to the ownership change log, activities, and audit log.

## Ownership Responsibility Model

Ownership choices are system records, not hardcoded workflow lanes.

Core tables:

- `owner_profiles`: available owners, shared ownership placeholders, and system owners.
- `responsibility_roles`: scalable operating responsibilities such as Relationship / Opportunity Owner and Capacity / Readiness Owner.
- `owner_responsibility_roles`: which owners currently fill which responsibilities.
- `region_ownership_defaults`: regional and context-specific default assignments.

Existing text owner columns remain in place for compatibility with current workflows. They are populated from the ownership model so future owners, teams, or regional leaders can be added through configuration instead of code changes.

## Operating Rhythm

Daily:

- Company State Review
- My Priorities Review
- Shared Priorities Review

Weekly:

- Relationship / opportunity / market review
- Capacity / workforce / readiness review
- Shared pursuits / blockers / strategic accounts review

Monthly:

- Strategic Account Review
- Capacity Network Review
- Market Readiness Review

Quarterly:

- Regional Dominance Review
- Investment Review
