# ERP Integration Contract

This document defines the future SyncERP handoff contract.

SyncERP is not built in this repository. No execution, billing, labor, equipment tracking, or production reporting workflows are implemented here.

## Customer

- customer_name
- organization_id
- region
- market
- state
- key relationship contacts
- relationship objectives
- relationship scores
- relationship notes

## Project

- project_package_id
- opportunity_id
- preconstruction_profile_id
- package_name
- package_status
- estimated_value
- estimated_margin
- package_owner
- opportunity summary
- pursuit decision
- decision support history
- learning insights

## Capacity

- crews assigned
- disciplines required
- required duration
- preferred source
- current available capacity
- projected gap
- mobilization assumptions

## Subcontractors

- selected subcontractors
- preferred subcontractors
- recommended role
- fit score
- trust score
- capacity contribution score
- mobilization readiness
- risk notes

## Margin Forecast

- estimated revenue
- estimated labor cost
- estimated subcontractor cost
- estimated equipment cost
- estimated material cost
- estimated travel cost
- estimated overhead
- estimated profit
- estimated margin percent
- confidence score

## Risk

- risk type
- severity
- reason
- mitigation
- status

## Scenario

- scenario name
- scenario type
- revenue estimate
- margin estimate
- crew requirement
- capacity gap
- risk summary
- recommendation

## Integration Status

- status
- exported_at
- imported_at
- execution_started_at

Status tracking exists only for handoff readiness. Future SyncERP implementation must consume this package without requiring re-entry or losing acquisition context.
