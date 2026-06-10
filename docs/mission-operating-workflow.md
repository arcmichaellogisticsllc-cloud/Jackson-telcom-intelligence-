# Mission Operating Workflow

Jackson Platform is built around one mission:

Acquire Work.
Acquire Capacity.
Acquire Influence.
Convert all three into revenue.

Everything else is infrastructure.

## Four Mission Lanes

### Acquire Work

Purpose: identify work Jackson should pursue.

Sources:

- Real intelligence
- Signals
- Strategic accounts
- Opportunities
- Market intelligence
- Pursuits

Required operating answers:

- What work exists?
- Who owns it?
- What is the next action?
- What is blocking pursuit?

### Acquire Capacity

Purpose: build deployable capacity before committing to work.

Sources:

- Subcontractor onboarding
- Capacity radar
- Workforce intelligence
- Documents
- Compliance reviews
- Capacity reviews

Required operating answers:

- Who can perform work?
- What documents are missing?
- What crews are available?
- What must be verified before approval?

### Acquire Influence

Purpose: create access to decision makers and work flow.

Sources:

- Contacts
- Organizations
- Relationship intelligence
- Strategic accounts
- Communications
- Network intelligence

Required operating answers:

- Who influences who gets work?
- What objective does the relationship support?
- What should Jackson ask or do next?
- What access is missing?

### Convert To Revenue

Purpose: match work, capacity, and influence into bid-ready and handoff-ready opportunities.

Sources:

- Opportunities
- Pursuit decisions
- Preconstruction profiles
- Project packages
- ERP readiness profiles

Required operating answers:

- Is there enough influence?
- Is there enough capacity?
- Is pursuit approved?
- Is preconstruction ready?
- Is the package ready for SyncERP handoff?

## Mission Status

Mission queue items use operational status:

- New
- Needs Review
- Needs Info
- Ready to Act
- In Progress
- Ready for Decision
- Ready for Revenue
- Blocked
- Closed

## Blockers

The platform should surface blockers before operators browse modules:

- Missing owner
- Missing next action
- Missing documents
- Weak influence fit
- Weak capacity fit
- Missing pursuit decision
- Missing preconstruction profile
- Missing handoff package
- ERP readiness below threshold
- Low confidence or unresolved data review

## Completion Test

The pass is complete only when the Command Center can show:

1. Work being acquired.
2. Capacity being acquired.
3. Influence being acquired.
4. Revenue conversion opportunities and blockers.
5. Direct links to the record or action that moves each item forward.

Run:

```bash
php scripts/check_mission_workflow.php
```

The script does not seed demo data. Empty lanes are valid when there is no production data, but the mission structure, routes, and action paths must exist.
