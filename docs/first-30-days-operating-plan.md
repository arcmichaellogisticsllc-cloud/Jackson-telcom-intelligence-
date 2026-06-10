# First 30 Days Operating Plan

Jackson Telcom Command Center exists to keep operators focused on the five questions that matter:

1. Who has work?
2. Who has capacity?
3. Who needs work?
4. Who influences work?
5. What should we do next?

## Daily Workflow

Start every day at the Jackson Telcom Command Center.

1. Open Command Center.
2. Review Today's Priorities.
3. Review Work Ready.
4. Review Capacity Available.
5. Review Capacity Seeking Work.
6. Review Influence Network.
7. Complete, dismiss, or delegate the top five actions.
8. Record outcome notes before moving to the next action.

Relationship / opportunity ownership should work assigned account, relationship, opportunity, market, and partnership priorities first, then shared Southwest and national actions.

Capacity / readiness ownership should work assigned capacity, subcontractor, workforce, readiness, preconstruction, and handoff priorities first, then shared Southwest and national actions.

Admin should review cross-theater priorities, data health, and system reliability.

## Weekly Workflow

Run the acquisition cycle sequentially, then review operator screens.

```bash
php scripts/run_acquisition_cycle.php
php scripts/check_data_integrity.php
php scripts/smoke_routes.php
```

Weekly review order:

1. Executive Brief
2. Decision Support
3. Escalations
4. Hunting Lists
5. Capacity Radar
6. Relationship Graph
7. Demand Engine review queue
8. SyncERP Integration handoff queue
9. Platform Health

Do not run SQLite database-writing scripts in parallel.

## Monthly Review

Use the monthly review to decide what to keep, strengthen, or stop.

1. Review Intelligence Warehouse.
2. Review lessons learned.
3. Identify best relationships.
4. Identify best subcontractor sources.
5. Identify hunts that converted.
6. Identify content and distribution that created demand.
7. Identify pursuits won, lost, avoided, or blocked.
8. Update market priorities for Southeast, Great Lakes, Southwest, and National.

## Signal Review

Signals are not work by themselves.

Review:

- Escalations first
- Hunt-classified signals second
- Watchlist changes third
- Archived/noise only when troubleshooting source quality

Signals should become targets, recommendations, watchlist entries, or archived noise.

## Hunt Review

Hunts must produce movement.

Review:

- Today’s Hunt Actions
- overdue hunt tasks
- targets stuck in the same status more than seven days
- targets ready for outreach
- converted targets
- not-fit targets

Every active hunt should have an owner, playbook, next step, and outcome path.

## Capacity Review

Capacity review answers: Can Jackson execute?

Review:

- critical capacity gaps
- predictive 30-day and 60-day gaps
- approved providers
- preferred providers
- strategic partners
- subcontractors missing compliance documents
- trusted capacity with available crews

Capacity gaps should create recruitment actions, hunt assignments, or subcontractor follow-ups.

## Relationship Review

Relationships are influence assets.

Review:

- project managers
- utility contacts
- prime contractor contacts
- relationship risks
- relationships without primary objectives
- relationships with no recent contact
- contacts ready to mobilize, provide work, provide capacity, or provide market intelligence

Every critical relationship should have a next best action.

## Pursuit Review

Pursuit review answers: Should Jackson spend attention, capacity, and relationships on this work?

Review:

- fiber backbone alignment
- relationship fit
- capacity fit
- market fit
- margin forecast
- preconstruction risk
- bid/no-bid decision
- SyncERP handoff readiness for awarded work

Avoid work that does not advance Jackson Telcom's fiber backbone mission or that cannot be supported by capacity, relationship, and margin realities.
