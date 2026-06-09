# First-Pass Real Hunt Report

## Purpose

This sprint begins production real hunting without reintroducing fake demo data. The imported records are public-source, source-tagged, review-gated, and organized around Jackson's four hunting questions:

- Who has work?
- Who has capacity?
- Who needs work?
- Who influences work?

The import pack lives in `storage/imports/real_hunt/` and is loaded with `php scripts/import_real_hunt.php`.

## Sources Used

The first pass prioritized official/public sources:

- Official strategic account websites: Comcast, Charter / Spectrum, Frontier, AT&T, Windstream.
- Official state broadband offices: Georgia, Florida, Alabama, Tennessee, North Carolina, South Carolina, Michigan, Ohio, Indiana, Texas.
- Official/public utility and municipal websites.
- Official/public contractor, engineering firm, and prime contractor websites.
- Official national broadband funding source: NTIA / Internet for All.

No gated/private sources were scraped. No personal contact details were guessed. Where a public person name was not safely verified, the import creates a review-gated relationship or workforce target rather than a trusted contact.

## Import Files

- `strategic_accounts_real_hunt.csv`
- `organizations_real_hunt.csv`
- `contacts_real_hunt.csv`
- `capacity_providers_real_hunt.csv`
- `engineering_firms_real_hunt.csv`
- `primes_competitors_real_hunt.csv`
- `workforce_real_hunt.csv`
- `opportunities_real_hunt.csv`
- `markets_real_hunt.csv`

Every row includes:

- `source_url`
- `source_type`
- `confidence_score`
- `review_status`
- `import_source=real_hunt`

## First-Pass Counts

Planned first-pass targets were intentionally treated as goals, not quotas. Accuracy and reviewability were prioritized over volume.

| Dataset | Target | First Pass | Notes |
| --- | ---: | ---: | --- |
| Strategic accounts | 5 | 5 | Comcast, Charter / Spectrum, Frontier, AT&T, Windstream. |
| Organizations | 100 | 40 | Official utilities, broadband offices, municipal/fiber entities, and infrastructure sources. |
| Relationships / contacts | 250 | 20 | Mostly role targets because named contacts require manual public verification. |
| Capacity providers | 100 | 20 | All imported as Prospect only. No provider is approved. |
| Engineering firms | 25 | 25 | Public engineering/OSP/utility engineering targets. |
| Prime / competitive organizations | 25 | 12 | Major national and regional primes/competitors. |
| Workforce / talent records | 50 | 20 | Role-based workforce targets, not trusted named talent records. |
| Opportunities | 25 | 15 | Official broadband/funding/fiber ecosystem opportunities. |
| Markets | 10 | 10 | Southeast, Great Lakes, and Houston/Texas target markets. |

## Top Strategic Accounts

- Comcast
- Charter / Spectrum
- Frontier
- AT&T
- Windstream

## Top Markets

- Georgia
- Michigan
- Houston / Texas
- Florida
- North Carolina
- Ohio

## Top Capacity Provider Categories

- Telecom construction contractors
- Utility construction contractors
- Fiber splicing contractors
- Directional boring / underground contractors
- Aerial construction providers

All capacity providers remain `Prospect` or review-gated. Crew counts, equipment counts, trust scores, and approval status must be verified manually before operational use.

## Top Opportunity Sources

- NTIA / Internet for All
- State broadband offices
- Municipal fiber/broadband entities
- Utility fiber ecosystems
- Public infrastructure offices

## Data Quality Issues Expected

The importer creates Data Quality Issues when:

- Confidence is below 75.
- Review status is not `Verified`.
- A contact/workforce row lacks a public person name.
- Source reliability or market fit needs human review.

Most contact and workforce rows intentionally create review items because the first pass should not fabricate people or direct contact details.

## Research Enrichment Pass

Run:

```bash
php scripts/enrich_real_hunt.php
```

The enrichment pass converts imported public research into review-gated workflow context. It does not create call notes, completed outreach, communication history, or Daily Actions.

Latest enrichment output:

| Enrichment Area | Count |
| --- | ---: |
| Signal Quality Profiles | 167 |
| Source Quality Profiles | 9 |
| Strategic Account Onboarding | 5 |
| Organization Work/Capacity/Influence Context | 75 |
| Capacity Provider Prospect Context | 20 |
| Opportunity Pursuit Context | 15 |
| Market Onboarding Context | 10 |
| Relationship Role Target Context | 20 |
| Executive Review Packages | 24 |
| Data Review Queue Items | 112 |

Important boundaries:

- Capacity providers remain prospects.
- Crew counts and equipment counts remain unknown unless public/verified.
- Relationship and workforce rows without public names remain role targets.
- Opportunity decisions remain watch/research/selective context until relationship and capacity fit are verified.
- Executive packages are review packages, not proof that outreach or qualification happened.

## Next Recommended Hunting Wave

1. Manually verify named public contacts for strategic accounts in Georgia, Michigan, and Houston.
2. Expand state broadband opportunity rows from official grant award pages once awardee/procurement details are verified.
3. Build a reviewed capacity-provider list from official contractor websites plus operator-confirmed referrals.
4. Add real business emails/phones only where published on official business pages.
5. Convert verified relationship targets into Contact records and assign primary relationship objectives.
6. Review capacity provider prospects for subcontractor onboarding readiness.
7. Monitor competitor hiring and award pages for real market pressure signals.

## Operating Rule

Do not promote these imported rows to trusted operating intelligence until a Jackson operator reviews the source, confirms the record, and resolves any associated Data Quality Issue.
