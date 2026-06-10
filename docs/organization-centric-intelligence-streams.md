# Organization-Centric Intelligence Streams

This layer turns public intelligence into Jackson operating records without creating orphan data.

The model is:

```text
Organization
Contact
Work / Capacity / Influence
Signal
Action
```

Every useful item must answer at least one question:

- Who has work?
- Who has capacity?
- Who influences work?

## Five Streams

1. Broadband Funding Intelligence
   - Purpose: who has future work.
   - Sources: NTIA BroadbandUSA, state broadband offices, USDA ReConnect, public funding programs.
   - Output: funding source organizations, state broadband office organizations, opportunity watches, market signals, public contacts when official.

2. Strategic Account Intelligence
   - Purpose: who has work and who influences work.
   - Accounts: Comcast, Charter / Spectrum, Frontier, AT&T, Windstream.
   - Output: strategic account organizations, work signals, hiring signals, influence signals, account coverage actions.

3. Engineering Firm Intelligence
   - Purpose: who knows about work before construction starts.
   - Targets: OSP engineering firms, fiber design firms, utility engineering firms, broadband consultants.
   - Output: engineering firm organizations, influence profiles, relationship actions, market signals.

4. Contractor Discovery Intelligence
   - Purpose: who has capacity.
   - Targets: aerial, underground, fiber splicing, directional boring, make ready, inspection, QC, and ROW providers.
   - Output: capacity provider organizations, subcontractor prospects, capacity profiles, onboarding/qualification actions.

5. Prime Contractor Intelligence
   - Purpose: where work is flowing and who else is chasing it.
   - Targets: MasTec, Congruex, Ervin, Ansco, SQUAN, Utilities One, National OnDemand, Tilson, Dycom.
   - Output: prime organizations, competitor profiles, competitive signals, work signals, recommended responses.

## Organization-First Rule

The importer must resolve or create an Organization before creating:

- Contacts
- Opportunities
- Capacity profiles
- Subcontractor prospects
- Influence profiles
- Acquisition targets
- Recommended actions

If organization matching is uncertain, the row remains review-gated and creates a Data Quality Issue. Do not create orphan contacts or orphan opportunities.

## Role And Access

Contacts are classified by:

- Role type
- Access category
- Decision authority
- Influence
- Access
- Trust
- Strategic value

If role/access is unclear, the record is marked `Needs Review` and scoring is conservative.

## Evidence Requirement

Every created or updated record should preserve:

- Source URL
- Source name
- Source type
- Collection time
- Confidence score
- Evidence summary
- Review status

No evidence means no trusted record.

## Import Templates

Templates live in:

```text
storage/imports/real_streams/
```

Files:

- `broadband_funding_stream.csv`
- `strategic_account_stream.csv`
- `engineering_firm_stream.csv`
- `contractor_discovery_stream.csv`
- `prime_contractor_stream.csv`

These templates are header-only by default. Fill them with source-backed public findings only.

## Import Commands

```bash
php scripts/import_intelligence_stream.php storage/imports/real_streams/broadband_funding_stream.csv broadband_funding
php scripts/import_intelligence_stream.php storage/imports/real_streams/strategic_account_stream.csv strategic_account
php scripts/import_intelligence_stream.php storage/imports/real_streams/engineering_firm_stream.csv engineering_firm
php scripts/import_intelligence_stream.php storage/imports/real_streams/contractor_discovery_stream.csv contractor_discovery
php scripts/import_intelligence_stream.php storage/imports/real_streams/prime_contractor_stream.csv prime_contractor
```

## Review Workflow

1. Import source-backed rows.
2. Review Data Quality Issues.
3. Review source evidence.
4. Confirm organization match.
5. Confirm contact role/access.
6. Promote capacity providers through onboarding only.
7. Convert opportunity watches only after enough source evidence exists.
8. Work recommended actions from the Command Center and record outcomes.

## Mike / Ron Usage

Mike should focus on:

- Strategic accounts
- Relationships
- Opportunities
- Engineering influence
- Prime contractor access

Ron should focus on:

- Capacity provider prospects
- Subcontractor readiness
- Workforce/capacity signals
- Field readiness implications
- Qualification and onboarding actions

This remains one Jackson operating system. Perspective changes priority, not the workflow.
