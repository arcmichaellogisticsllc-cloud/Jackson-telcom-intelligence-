# UI Upgrade Map

This UI upgrade is a shell and operator-experience refactor. It must not change the business architecture, execution boundaries, authorization model, CSRF behavior, or data contracts.

## Upgrade Doctrine

Modules feed intelligence. Workspaces organize action.

The UI should help Mike, Ron, executives, and operators answer:

1. Who has work?
2. Who has capacity?
3. Who needs work?
4. Who influences work?
5. What should we do next?

Backend engines should remain accessible, but they should not be the primary mental model for daily usage.

## Workspace Navigation

COMMAND:
- `/` Command Center
- `/executive-os` Executive OS
- `/daily-brief` Daily Brief
- `/executive-briefs` Executive Brief
- `/executive-packages` Decision Packages
- `/decision-visuals` Decision Visuals
- `/strategic-review` Strategic Review

WORK:
- `/workspace/work` Work Workspace
- `/acquisition-command` Work Intelligence
- `/strategic-account-intelligence` Strategic Accounts
- `/opportunities` Opportunities
- `/pursuits` Pursuits
- `/preconstruction` Preconstruction

CAPACITY:
- `/workspace/capacity` Capacity Workspace
- `/capacity-radar` Capacity Radar
- `/subcontractor-acquisition` Subcontractor Network
- `/subcontractors` Preferred Network
- `/targets` Strategic Partners
- `/workforce-intelligence` Workforce Intelligence

RELATIONSHIPS:
- `/workspace/relationships` Relationship Workspace
- `/communications` Communications
- `/contacts` Contacts
- `/organizations` Organizations
- `/relationship-graph` Relationship Graph
- `/network-intelligence` Network Intelligence

MARKET:
- `/workspace/market` Market Workspace
- `/signals` Signals
- `/escalations` Escalations
- `/watchlists` Watchlists
- `/market-intelligence` Market Intelligence
- `/competitive-intelligence` Competitive Intelligence
- `/harvesters` Acquisition Harvesters

GROWTH:
- `/workspace/growth` Growth Workspace
- `/demand` Demand
- `/traffic` Content
- `/outreach` Distribution
- `/demand-briefing` Channels

ONBOARDING:
- `/workspace/onboarding` Onboarding Workspace
- `/onboarding` Overview
- `/onboarding/subcontractors` Subcontractors
- `/onboarding/workforce` Workforce
- `/onboarding/strategic-accounts` Strategic Accounts
- `/onboarding/markets` Markets
- `/onboarding/documents` Documents
- `/onboarding/reviews` Reviews
- `/onboarding/metrics` Metrics

OPERATIONS:
- `/workspace/operations` Operations Workspace
- `/syncerp-integration` SyncERP Integration
- `/syncerp-handoff-brief` Handoff Brief

SYSTEM:
- `/workspace/system` System Workspace
- `/production-readiness` Production Readiness
- `/data-quality` Data Quality Review
- `/connector-runs` Connector Runs
- `/audit-logs` Audit Logs
- `/operating-rhythm` Operating Rhythm
- `/platform-review` Platform Health
- `/settings` Settings
- `/recommendations` Recommendations
- `/activities` Activities
- `/warehouse` Intelligence Warehouse

## Page Classification

Executive-facing:
- Command Center
- Executive OS
- Executive Brief
- Daily Brief
- Decision Packages
- Decision Visuals
- Strategic Review

Operator-facing:
- Workspace homes
- Daily Actions
- Recommendations
- Onboarding
- Data Quality Review
- Communications

Record workspaces:
- Strategic accounts
- Contacts
- Organizations
- Opportunities
- Pursuits
- Subcontractors
- Preconstruction profiles
- Project packages

Admin/system:
- Settings
- Production Readiness
- Connector Runs
- Audit Logs
- Platform Health
- Intelligence Warehouse

## Protected Flows

These flows must not be broken by UI work:

- Login and logout
- Authorization checks
- CSRF-protected POSTs
- Record actions
- Communication logging
- Daily action completion and dismissal
- Recommendation governance
- Data quality issue resolution
- Connector run review
- Pilot feedback
- SyncERP package readiness/status actions

Every POST must keep:

- CSRF validation
- server-side authorization
- audit logging where applicable
- safe redirect
- visible success or resulting record state

## Component Inventory

Shared shell:
- `app/Views/layouts/app.php`
- `public/assets/styles.css`

Shared components:
- `components/action_first.php`
- `components/todays_priorities.php`
- `components/recent_conversations.php`
- `components/intelligence_timeline.php`
- `components/record_header.php`
- `components/record_tabs.php`
- `components/list_toolbar.php`
- `components/command_widgets.php`
- `components/platform_health.php`
- `components/empty_state.php`

## Record Workspace Standard

Major record detail pages should use the same operator pattern:

- `record_header.php`
- `record_tabs.php`
- `action_first.php`
- `recent_conversations.php` when communication records exist
- `intelligence_timeline.php`
- contextual panels for scorecards, capacity, documents, risks, or handoff context

The action-first block should answer:

- What This Is
- Why It Matters
- Next Step
- Risk of Inaction

The following record types are expected to follow this pattern:

- Strategic Account
- Contact
- Organization
- Opportunity / Pursuit
- Subcontractor
- Acquisition Target
- Preconstruction Profile
- Project Package
- Generic activity record fallback

## Operator List View Standard

Major list pages should behave as work queues, not database exports.

Default columns should prioritize:

- Record
- Region
- Owner
- Status
- Score or priority
- Last Activity
- Next Action
- Actions

Supported filters should be real server-side filters where possible:

- Search
- Owner
- Region
- Status
- Priority for priority-driven queues

IDs, raw source fields, and internal engine fields should stay out of the default scan path. Operators can open the record detail page when they need evidence, source details, or lower-frequency metadata.

Current shared list patterns:

- `components/list_toolbar.php`
- `crud/table.php`
- target work queue in `targets/index.php`
- recommendation work queue in `recommendations/index.php`

## Operator Action Feedback Standard

POST actions should give immediate operator feedback after redirect.

Use flash messages for:

- Notes, calls, email drafts, and follow-ups
- Daily action completion, dismissal, and follow-up creation
- Recommendation status updates and regeneration
- Target status changes, hunt assignment, and conversion
- Subcontractor scorecards, compliance, document records, and promotion
- Data quality issue creation and resolution
- Onboarding stages, reviews, and document records
- Content review and distribution status changes
- SyncERP handoff package rebuilds

Feedback copy should state whether the action did or did not create external side effects. Examples:

- Draft only. Nothing was sent.
- No automated posting occurred.
- No execution workflow was created.
- Source history was preserved.

## Empty State Rule

Production mode may have no business records after demo purge. Empty states must look intentional and must not show fake placeholders.

Use language such as:

- No real records yet.
- Start by adding a strategic account.
- Import or create your first contact.
- Run a connector to begin collecting signals.
- Create the first capacity provider.

## Verification Gate

Run after each UI chunk:

```bash
php scripts/migrate.php
php scripts/seed.php
php scripts/run_acquisition_cycle.php
php scripts/check_data_integrity.php
php scripts/smoke_routes.php
php scripts/validate_erp_contract.php
php -l app/Views/layouts/app.php
git diff --check
```

Manual checks:

- Admin login
- Mike mode
- Ron mode
- Viewer/read-only behavior
- Direct URL authorization
- CSRF failure and success paths
- Add Note
- Log Call
- Draft Email
- Complete Action
- Resolve Data Quality Issue
- Command Center
- Each workspace home
- Major record detail pages
