# Scheduled Enrichment Plan

Jackson Platform uses scheduled enrichment to grow verified operating intelligence without chasing raw volume.

The goal is to keep answering four questions:

- Who has work?
- Who has capacity?
- Who needs work?
- Who influences work?

## Source Categories

Daily sources:

- Strategic account official news and careers for Comcast, Charter / Spectrum, Frontier, AT&T, and Windstream.
- Official broadband funding and state broadband office sources.

Weekly sources:

- Contractor discovery for aerial, underground, fiber splicing, directional boring, and related capacity.
- OSP, broadband, and utility engineering firm discovery.
- Prime and competitor monitoring for hiring, awards, market movement, and subcontractor programs.

Monthly sources:

- Market readiness review by target state and market.
- Workforce movement and leadership review.

Quarterly sources:

- Regional dominance review.
- Strategic account coverage review.
- Source performance review.
- Capacity network coverage review.
- Competitive pressure review.

## Backfill Windows

- Markets, funding, and competition: 180 days.
- Strategic accounts: 120 days.
- Opportunities: 120 days.
- Engineering firms: 120 days.
- Prime contractors: 120 days.
- Capacity providers: 90 days.
- Workforce: 60 days.

Do not backfill years of stale data unless a specific executive review requires it.

## Confidence Rules

- Official government or public funding sources: 90-100.
- Official company press or careers pages: 75-95.
- Contractor websites or public directories: 60-85.
- Manual public research: 50-85.
- Social, forum, or marketplace sources: 20-70 and always review required.

Low-confidence or incomplete records create Data Quality issues. Uncertain records remain review-gated.

## Review-Gated Pipeline

The intended flow is:

```text
Enrichment Source
Scheduled Enrichment Run
Source Item / Manual Research Task
Data Quality Review when needed
Signal Quality
Candidate Target / Relationship / Capacity / Opportunity
```

Scheduled enrichment must not create approved subcontractors, trusted relationships, or pursuit-ready opportunities by itself.

Capacity providers discovered by enrichment start as Prospect or Needs Review. Approval happens through onboarding and human review.

## Running Enrichment

Dry run due sources:

```bash
php scripts/run_scheduled_enrichment.php --dry-run --due
```

Dry run by cadence:

```bash
php scripts/run_scheduled_enrichment.php --cadence=daily --dry-run
php scripts/run_scheduled_enrichment.php --cadence=weekly --dry-run
```

Run due enrichment:

```bash
php scripts/run_scheduled_enrichment.php --due
```

Run a single source:

```bash
php scripts/run_scheduled_enrichment.php --source=1
```

Run a backfill:

```bash
php scripts/run_scheduled_enrichment.php --source=1 --backfill
```

## Local Environments

Local development does not perform live web fetching.

When a live adapter is unavailable, the scheduler creates review-gated manual research tasks and source review items instead of failing or scraping unsafe sources.

Live public-source fetch adapters are disabled by default. Enable them only in a controlled production environment:

```bash
JIP_ENABLE_LIVE_FETCH=1 php scripts/run_scheduled_enrichment.php --due
```

Enabled adapters still create review-gated raw signal items. They do not create trusted organizations, approved subcontractors, contacts, or opportunities directly.

Production connector adapters can later be attached to the same Enrichment Source records, but they must still route imported data through source evidence, confidence scoring, Data Quality Review, and Signal Quality.

## Growth Targets

Long-term targets:

- 10,000 relationships.
- 3,000 organizations.
- 250 approved subcontractors.
- 500 active talent records.
- 500 opportunities tracked.

Milestones:

- 30 days: 100 organizations, 250 relationships, 25 qualified subcontractors, 25 opportunities.
- 90 days: 250 organizations, 500 relationships, 50 approved or near-approved subcontractors, 50 opportunities.
- 12 months: long-term coverage targets.

The Command Center emphasizes verified records and pending review counts, not raw collected volume.
