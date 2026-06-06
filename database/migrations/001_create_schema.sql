CREATE TABLE IF NOT EXISTS regions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  owner TEXT NOT NULL,
  owner_name TEXT,
  owner_email TEXT,
  hub_city TEXT,
  hub_state TEXT,
  states TEXT NOT NULL,
  states_covered TEXT,
  priority_tier TEXT DEFAULT 'Tier 1',
  operating_status TEXT DEFAULT 'Active',
  strategic_notes TEXT,
  coverage_score INTEGER DEFAULT 0,
  capacity_score INTEGER DEFAULT 0,
  relationship_score INTEGER DEFAULT 0,
  opportunity_score INTEGER DEFAULT 0,
  traffic_score INTEGER DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS capacity_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER NOT NULL,
  service_type TEXT NOT NULL,
  target_crews INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1,
  UNIQUE(region_id, service_type),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS capacity_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  profile_name TEXT NOT NULL,
  profile_type TEXT NOT NULL CHECK(profile_type IN ('Internal','Subcontractor','Vendor','Equipment Provider','Specialty Provider')),
  organization_id INTEGER,
  subcontractor_id INTEGER,
  region_id INTEGER,
  market TEXT,
  state TEXT,
  city TEXT,
  owner TEXT CHECK(owner IN ('Mike','Ron','Mike/Ron Shared','Future Southwest Owner','Admin')),
  status TEXT DEFAULT 'Prospect' CHECK(status IN ('Prospect','Qualified','Approved','Preferred','Strategic Partner')),
  primary_mobilization_readiness TEXT DEFAULT '30 Days',
  max_travel_radius_miles INTEGER DEFAULT 0,
  states_served TEXT,
  markets_served TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(subcontractor_id) REFERENCES subcontractors(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS capacity_discipline_counts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  capacity_profile_id INTEGER NOT NULL,
  discipline TEXT NOT NULL,
  total_crews INTEGER DEFAULT 0,
  available_now INTEGER DEFAULT 0,
  available_24_hours INTEGER DEFAULT 0,
  available_72_hours INTEGER DEFAULT 0,
  available_1_week INTEGER DEFAULT 0,
  available_2_weeks INTEGER DEFAULT 0,
  available_30_days INTEGER DEFAULT 0,
  available_60_days INTEGER DEFAULT 0,
  booked_count INTEGER DEFAULT 0,
  unknown_count INTEGER DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(capacity_profile_id) REFERENCES capacity_profiles(id)
);

CREATE TABLE IF NOT EXISTS capacity_equipment (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  capacity_profile_id INTEGER NOT NULL,
  equipment_type TEXT NOT NULL,
  count INTEGER DEFAULT 0,
  condition TEXT DEFAULT 'Unknown' CHECK(condition IN ('Unknown','Poor','Fair','Good','Excellent')),
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(capacity_profile_id) REFERENCES capacity_profiles(id)
);

CREATE TABLE IF NOT EXISTS capacity_trust_scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  capacity_profile_id INTEGER NOT NULL UNIQUE,
  safety_score INTEGER DEFAULT 0,
  quality_score INTEGER DEFAULT 0,
  communication_score INTEGER DEFAULT 0,
  responsiveness_score INTEGER DEFAULT 0,
  production_score INTEGER DEFAULT 0,
  documentation_score INTEGER DEFAULT 0,
  relationship_history_score INTEGER DEFAULT 0,
  trust_score INTEGER DEFAULT 0,
  trust_category TEXT DEFAULT 'Developing',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(capacity_profile_id) REFERENCES capacity_profiles(id)
);

CREATE TABLE IF NOT EXISTS regional_capacity_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER,
  market TEXT,
  discipline TEXT NOT NULL,
  target_crews_now INTEGER DEFAULT 0,
  target_crews_30_days INTEGER DEFAULT 0,
  target_crews_60_days INTEGER DEFAULT 0,
  strategic_notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('Admin','Southeast Owner','Great Lakes Owner','Southwest Owner')),
  region_id INTEGER NULL,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS organizations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  type TEXT NOT NULL,
  region_id INTEGER NOT NULL,
  state TEXT,
  city TEXT,
  website TEXT,
  phone TEXT,
  notes TEXT,
  status TEXT DEFAULT 'Active',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS contacts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  title TEXT,
  email TEXT,
  phone TEXT,
  organization_id INTEGER,
  region_id INTEGER NOT NULL,
  relationship_owner TEXT,
  influence_level TEXT,
  relationship_strength TEXT,
  last_contact_date TEXT,
  next_action TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS subcontractors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  organization_id INTEGER NOT NULL,
  region_id INTEGER NOT NULL,
  company_name TEXT,
  legal_name TEXT,
  years_in_business INTEGER DEFAULT 0,
  website TEXT,
  phone TEXT,
  email TEXT,
  owner_name TEXT,
  primary_contact TEXT,
  contact_title TEXT,
  states_served TEXT,
  markets_served TEXT,
  services_offered TEXT,
  crew_count INTEGER DEFAULT 0,
  available_crew_count INTEGER DEFAULT 0,
  aerial_crew_count INTEGER DEFAULT 0,
  underground_crew_count INTEGER DEFAULT 0,
  fiber_splicing_crew_count INTEGER DEFAULT 0,
  directional_boring_crew_count INTEGER DEFAULT 0,
  emergency_restoration_crew_count INTEGER DEFAULT 0,
  traffic_control_crew_count INTEGER DEFAULT 0,
  mowing_row_crew_count INTEGER DEFAULT 0,
  inspection_crew_count INTEGER DEFAULT 0,
  qc_crew_count INTEGER DEFAULT 0,
  engineering_crew_count INTEGER DEFAULT 0,
  make_ready_crew_count INTEGER DEFAULT 0,
  drop_crew_count INTEGER DEFAULT 0,
  bucket_trucks INTEGER DEFAULT 0,
  digger_derricks INTEGER DEFAULT 0,
  directional_drills INTEGER DEFAULT 0,
  splicing_trailers INTEGER DEFAULT 0,
  fusion_splicers INTEGER DEFAULT 0,
  reel_trailers INTEGER DEFAULT 0,
  vac_trucks INTEGER DEFAULT 0,
  insurance_status TEXT,
  w9_status TEXT,
  approval_stage TEXT,
  availability TEXT,
  performance_score INTEGER DEFAULT 0,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS subcontractor_qualification_scorecards (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  subcontractor_id INTEGER NOT NULL UNIQUE,
  service_fit INTEGER DEFAULT 0,
  geographic_fit INTEGER DEFAULT 0,
  crew_capacity INTEGER DEFAULT 0,
  mobilization_speed INTEGER DEFAULT 0,
  equipment_availability INTEGER DEFAULT 0,
  insurance_readiness INTEGER DEFAULT 0,
  w9_readiness INTEGER DEFAULT 0,
  communication INTEGER DEFAULT 0,
  experience INTEGER DEFAULT 0,
  safety INTEGER DEFAULT 0,
  qualification_score INTEGER DEFAULT 0,
  qualification_result TEXT DEFAULT 'Not Fit',
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(subcontractor_id) REFERENCES subcontractors(id)
);

CREATE TABLE IF NOT EXISTS subcontractor_compliance_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  subcontractor_id INTEGER NOT NULL,
  document_type TEXT NOT NULL,
  status TEXT DEFAULT 'Missing' CHECK(status IN ('Missing','Requested','Submitted','Approved','Expired')),
  expiration_date TEXT,
  review_date TEXT,
  reviewed_by TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(subcontractor_id) REFERENCES subcontractors(id)
);

CREATE TABLE IF NOT EXISTS subcontractor_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  subcontractor_id INTEGER NOT NULL,
  file_name TEXT NOT NULL,
  document_type TEXT NOT NULL,
  uploaded_date TEXT DEFAULT CURRENT_TIMESTAMP,
  expiration_date TEXT,
  status TEXT DEFAULT 'Submitted',
  storage_path TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(subcontractor_id) REFERENCES subcontractors(id)
);

CREATE TABLE IF NOT EXISTS subcontractor_network_scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  subcontractor_id INTEGER NOT NULL UNIQUE,
  network_level TEXT DEFAULT 'Prospect',
  capacity_contribution_score INTEGER DEFAULT 0,
  capacity_contribution_category TEXT DEFAULT 'Low',
  promotion_recommendation TEXT,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(subcontractor_id) REFERENCES subcontractors(id)
);

CREATE TABLE IF NOT EXISTS opportunities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  organization_id INTEGER,
  region_id INTEGER NOT NULL,
  market TEXT,
  opportunity_type TEXT,
  customer_type TEXT,
  funding_source TEXT,
  estimated_value REAL DEFAULT 0,
  estimated_margin REAL DEFAULT 0,
  probability INTEGER DEFAULT 0,
  stage TEXT,
  capacity_required INTEGER DEFAULT 0,
  decision_makers TEXT,
  next_action TEXT,
  owner TEXT,
  strategic_alignment_score INTEGER DEFAULT 0,
  relationship_score INTEGER DEFAULT 0,
  capacity_score INTEGER DEFAULT 0,
  demand_score INTEGER DEFAULT 0,
  risk_score INTEGER DEFAULT 0,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS strategic_alignment_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  opportunity_id INTEGER NOT NULL UNIQUE,
  fiber_backbone_alignment_score INTEGER DEFAULT 0,
  strategic_market_score INTEGER DEFAULT 0,
  relationship_alignment_score INTEGER DEFAULT 0,
  capacity_alignment_score INTEGER DEFAULT 0,
  strategic_alignment_score INTEGER DEFAULT 0,
  category TEXT DEFAULT 'Moderate' CHECK(category IN ('Core','Strong','Moderate','Weak','Avoid')),
  classification TEXT DEFAULT 'Supporting' CHECK(classification IN ('Core','Supporting','Adjacent','Non-Strategic')),
  reason TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(opportunity_id) REFERENCES opportunities(id)
);

CREATE TABLE IF NOT EXISTS pursuit_scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  opportunity_id INTEGER NOT NULL UNIQUE,
  relationship_fit_score INTEGER DEFAULT 0,
  capacity_fit_score INTEGER DEFAULT 0,
  market_fit_score INTEGER DEFAULT 0,
  margin_score INTEGER DEFAULT 0,
  risk_score INTEGER DEFAULT 0,
  pursuit_score INTEGER DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(opportunity_id) REFERENCES opportunities(id)
);

CREATE TABLE IF NOT EXISTS opportunity_pursuit_decisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  opportunity_id INTEGER NOT NULL UNIQUE,
  region_id INTEGER,
  recommended_decision TEXT DEFAULT 'Monitor' CHECK(recommended_decision IN ('Pursue Aggressively','Pursue','Pursue Selectively','Monitor','Avoid')),
  decision_reason TEXT,
  relationship_gap TEXT,
  capacity_gap TEXT,
  next_best_action TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(opportunity_id) REFERENCES opportunities(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS opportunity_watchlists (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  opportunity_id INTEGER NOT NULL UNIQUE,
  region_id INTEGER,
  status TEXT DEFAULT 'Watch' CHECK(status IN ('Watch','Pursue Later','Active Pursuit','Avoid')),
  reason TEXT,
  next_review_date TEXT,
  owner TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(opportunity_id) REFERENCES opportunities(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS signals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT,
  signal_type TEXT NOT NULL CHECK(signal_type IN ('Capacity','Opportunity','Relationship','Market','SEO','Content','Outreach')),
  source_type TEXT NOT NULL CHECK(source_type IN ('Google Search','Google Business Profile','Facebook Marketplace','LinkedIn','Industry Forum','YouTube','Broadband Grant','Utility Announcement','Equipment Listing','New Business Filing','Hiring Activity','Manual Entry','Industry News','Referral','Conference','Website Form','Government Data','Contractor Intelligence','Other')),
  source_url TEXT,
  region_id INTEGER NOT NULL,
  state TEXT,
  city TEXT,
  organization_name TEXT,
  contact_name TEXT,
  confidence_score INTEGER DEFAULT 0,
  impact_score INTEGER DEFAULT 0,
  priority TEXT NOT NULL DEFAULT 'Medium' CHECK(priority IN ('Low','Medium','High','Critical')),
  owner TEXT DEFAULT 'Unassigned' CHECK(owner IN ('Admin','Mike','Ron','Unassigned')),
  status TEXT NOT NULL DEFAULT 'New' CHECK(status IN ('New','Reviewed','Assigned','Converted','Ignored')),
  recommended_next_action TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS signal_quality_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  signal_id INTEGER NOT NULL UNIQUE,
  source_quality_score INTEGER DEFAULT 0,
  signal_value_score INTEGER DEFAULT 0,
  strategic_value_score INTEGER DEFAULT 0,
  capacity_value_score INTEGER DEFAULT 0,
  opportunity_value_score INTEGER DEFAULT 0,
  relationship_value_score INTEGER DEFAULT 0,
  revenue_value_score INTEGER DEFAULT 0,
  confidence_score INTEGER DEFAULT 0,
  impact_score INTEGER DEFAULT 0,
  accumulation_score INTEGER DEFAULT 0,
  classification TEXT NOT NULL DEFAULT 'Watch' CHECK(classification IN ('Escalate','Hunt','Watch','Archive')),
  reason_for_classification TEXT,
  reviewed_by TEXT,
  reviewed_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(signal_id) REFERENCES signals(id)
);

CREATE TABLE IF NOT EXISTS source_quality_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  signal_source_id INTEGER NOT NULL UNIQUE,
  total_signals INTEGER DEFAULT 0,
  escalated_signals INTEGER DEFAULT 0,
  hunt_signals INTEGER DEFAULT 0,
  watch_signals INTEGER DEFAULT 0,
  archived_signals INTEGER DEFAULT 0,
  converted_targets INTEGER DEFAULT 0,
  converted_opportunities INTEGER DEFAULT 0,
  converted_subcontractors INTEGER DEFAULT 0,
  source_quality_score INTEGER DEFAULT 0,
  last_updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(signal_source_id) REFERENCES signal_sources(id)
);

CREATE TABLE IF NOT EXISTS signal_accumulation_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  organization_name TEXT,
  contact_name TEXT,
  region_id INTEGER,
  accumulated_signal_count INTEGER DEFAULT 0,
  accumulated_capacity_score INTEGER DEFAULT 0,
  accumulated_opportunity_score INTEGER DEFAULT 0,
  accumulated_relationship_score INTEGER DEFAULT 0,
  accumulated_confidence_score INTEGER DEFAULT 0,
  escalation_threshold INTEGER DEFAULT 80,
  current_status TEXT DEFAULT 'Watch' CHECK(current_status IN ('Escalate','Hunt','Watch','Archive')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS watchlist_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  organization_name TEXT,
  contact_name TEXT,
  signal_id INTEGER,
  accumulation_profile_id INTEGER,
  region_id INTEGER,
  status TEXT DEFAULT 'Monitoring' CHECK(status IN ('Monitoring','Escalated','Archived')),
  purpose TEXT,
  last_signal_at TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(signal_id) REFERENCES signals(id),
  FOREIGN KEY(accumulation_profile_id) REFERENCES signal_accumulation_profiles(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS keywords (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  keyword TEXT NOT NULL,
  intent_type TEXT NOT NULL,
  region_id INTEGER,
  state TEXT,
  city TEXT,
  priority TEXT DEFAULT 'Medium',
  current_rank INTEGER,
  target_rank INTEGER,
  search_intent_notes TEXT,
  status TEXT DEFAULT 'New',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS content_ideas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  content_type TEXT NOT NULL,
  region_id INTEGER,
  target_keyword TEXT,
  audience TEXT,
  status TEXT DEFAULT 'Idea',
  recommended_channel TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS outreach_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  organization TEXT,
  target_type TEXT NOT NULL,
  region_id INTEGER,
  state TEXT,
  source TEXT,
  status TEXT DEFAULT 'New',
  recommended_message TEXT,
  next_action TEXT,
  owner TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS outreach_sequences (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  target_type TEXT NOT NULL,
  region_id INTEGER,
  purpose TEXT NOT NULL,
  step_number INTEGER NOT NULL,
  channel TEXT NOT NULL,
  message_template TEXT,
  delay_days INTEGER DEFAULT 0,
  status TEXT DEFAULT 'Planned',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS outreach_intelligence (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  outreach_title TEXT NOT NULL,
  target_type TEXT NOT NULL,
  linked_record_type TEXT,
  linked_record_id INTEGER,
  region_id INTEGER,
  owner TEXT,
  channel TEXT NOT NULL,
  outreach_goal TEXT NOT NULL,
  priority TEXT DEFAULT 'Medium',
  reason TEXT,
  recommended_opening TEXT,
  discovery_questions TEXT,
  desired_outcome TEXT,
  status TEXT DEFAULT 'Draft',
  notes TEXT,
  due_date TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS outreach_scripts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  outreach_intelligence_id INTEGER NOT NULL,
  script_type TEXT NOT NULL,
  subject_line TEXT,
  body TEXT,
  review_status TEXT DEFAULT 'Draft',
  human_review_required INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(outreach_intelligence_id) REFERENCES outreach_intelligence(id)
);

CREATE TABLE IF NOT EXISTS outreach_discovery_questions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  target_type TEXT NOT NULL,
  question TEXT NOT NULL,
  sort_order INTEGER DEFAULT 0,
  active INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS outreach_outcomes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  outreach_intelligence_id INTEGER NOT NULL,
  outcome_type TEXT NOT NULL,
  outcome_notes TEXT,
  follow_up_date TEXT,
  created_by TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(outreach_intelligence_id) REFERENCES outreach_intelligence(id)
);

CREATE TABLE IF NOT EXISTS signal_sources (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  source_type TEXT NOT NULL,
  region_id INTEGER,
  state TEXT,
  city TEXT,
  target_category TEXT NOT NULL,
  collection_method TEXT NOT NULL,
  source_url TEXT,
  search_query TEXT,
  frequency TEXT DEFAULT 'Weekly',
  status TEXT DEFAULT 'Active',
  last_run_at TEXT,
  next_run_at TEXT,
  records_found_last_run INTEGER DEFAULT 0,
  records_created_last_run INTEGER DEFAULT 0,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS harvester_runs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  signal_source_id INTEGER,
  started_at TEXT DEFAULT CURRENT_TIMESTAMP,
  finished_at TEXT,
  status TEXT DEFAULT 'Pending',
  records_found INTEGER DEFAULT 0,
  records_created INTEGER DEFAULT 0,
  records_updated INTEGER DEFAULT 0,
  errors_count INTEGER DEFAULT 0,
  summary TEXT,
  raw_payload_path TEXT,
  raw_payload_text TEXT,
  created_by TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(signal_source_id) REFERENCES signal_sources(id)
);

CREATE TABLE IF NOT EXISTS raw_signal_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  harvester_run_id INTEGER,
  signal_source_id INTEGER,
  raw_title TEXT,
  raw_description TEXT,
  raw_url TEXT,
  raw_company_name TEXT,
  raw_contact_name TEXT,
  raw_phone TEXT,
  raw_email TEXT,
  raw_location TEXT,
  raw_state TEXT,
  raw_city TEXT,
  raw_source_date TEXT,
  raw_payload_json TEXT,
  processing_status TEXT DEFAULT 'New',
  duplicate_key TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(harvester_run_id) REFERENCES harvester_runs(id),
  FOREIGN KEY(signal_source_id) REFERENCES signal_sources(id)
);

CREATE TABLE IF NOT EXISTS acquisition_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  target_name TEXT NOT NULL,
  target_type TEXT NOT NULL,
  source_signal_id INTEGER,
  source_type TEXT,
  source_url TEXT,
  organization_name TEXT,
  contact_name TEXT,
  email TEXT,
  phone TEXT,
  website TEXT,
  region_id INTEGER,
  state TEXT,
  city TEXT,
  owner TEXT DEFAULT 'Unassigned',
  acquisition_score INTEGER DEFAULT 0,
  confidence_score INTEGER DEFAULT 0,
  strategic_value_score INTEGER DEFAULT 0,
  urgency_score INTEGER DEFAULT 0,
  capacity_value_score INTEGER DEFAULT 0,
  relationship_value_score INTEGER DEFAULT 0,
  opportunity_value_score INTEGER DEFAULT 0,
  status TEXT DEFAULT 'New',
  priority TEXT DEFAULT 'Medium',
  reason_to_pursue TEXT,
  recommended_next_action TEXT,
  notes TEXT,
  duplicate_key TEXT,
  last_touched_at TEXT,
  next_action_due_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(source_signal_id) REFERENCES signals(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS hunts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hunt_name TEXT NOT NULL,
  hunt_type TEXT NOT NULL,
  region_id INTEGER,
  owner TEXT,
  objective TEXT,
  target_count_goal INTEGER DEFAULT 0,
  start_date TEXT,
  end_date TEXT,
  status TEXT DEFAULT 'Draft',
  success_metric TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS acquisition_playbooks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  playbook_name TEXT NOT NULL,
  playbook_type TEXT NOT NULL,
  target_type TEXT,
  region_id INTEGER,
  objective TEXT,
  opening_script TEXT,
  qualification_questions TEXT,
  disqualification_rules TEXT,
  required_documents TEXT,
  conversion_goal TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS playbook_steps (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  playbook_id INTEGER NOT NULL,
  step_number INTEGER NOT NULL,
  step_name TEXT NOT NULL,
  channel TEXT,
  instructions TEXT,
  expected_outcome TEXT,
  delay_days INTEGER DEFAULT 0,
  required_before_next_step TEXT,
  creates_task INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(playbook_id) REFERENCES acquisition_playbooks(id)
);

CREATE TABLE IF NOT EXISTS hunt_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hunt_id INTEGER NOT NULL,
  acquisition_target_id INTEGER NOT NULL,
  playbook_id INTEGER,
  assigned_owner TEXT,
  hunt_status TEXT DEFAULT 'Added',
  current_step_id INTEGER,
  qualification_score INTEGER DEFAULT 0,
  qualification_result TEXT,
  outcome TEXT,
  outcome_date TEXT,
  outcome_notes TEXT,
  converted_record_type TEXT,
  converted_record_id INTEGER,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(hunt_id) REFERENCES hunts(id),
  FOREIGN KEY(acquisition_target_id) REFERENCES acquisition_targets(id),
  FOREIGN KEY(playbook_id) REFERENCES acquisition_playbooks(id),
  FOREIGN KEY(current_step_id) REFERENCES playbook_steps(id)
);

CREATE TABLE IF NOT EXISTS hunt_tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hunt_target_id INTEGER NOT NULL,
  acquisition_target_id INTEGER NOT NULL,
  task_title TEXT NOT NULL,
  task_type TEXT NOT NULL,
  owner TEXT,
  due_date TEXT,
  status TEXT DEFAULT 'Open',
  instructions TEXT,
  outcome_notes TEXT,
  playbook_step_id INTEGER,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(hunt_target_id) REFERENCES hunt_targets(id),
  FOREIGN KEY(acquisition_target_id) REFERENCES acquisition_targets(id),
  FOREIGN KEY(playbook_step_id) REFERENCES playbook_steps(id)
);

CREATE TABLE IF NOT EXISTS intelligence_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  signal_id INTEGER,
  region_id INTEGER,
  title TEXT NOT NULL,
  summary TEXT,
  market TEXT,
  state TEXT,
  owner TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(signal_id) REFERENCES signals(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS relationship_intelligence_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  contact_id INTEGER,
  organization_id INTEGER,
  region_id INTEGER,
  owner TEXT DEFAULT 'Unassigned',
  decision_authority_score INTEGER DEFAULT 0,
  influence_score INTEGER DEFAULT 0,
  access_score INTEGER DEFAULT 0,
  trust_score INTEGER DEFAULT 0,
  strategic_value_score INTEGER DEFAULT 0,
  relationship_value_score INTEGER DEFAULT 0,
  relationship_priority TEXT DEFAULT 'Low',
  relationship_status TEXT DEFAULT 'Unknown',
  relationship_summary TEXT,
  known_context TEXT,
  next_best_action TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(contact_id, organization_id),
  FOREIGN KEY(contact_id) REFERENCES contacts(id),
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS relationship_objectives (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  relationship_profile_id INTEGER NOT NULL,
  objective_type TEXT NOT NULL,
  priority TEXT DEFAULT 'Primary',
  status TEXT DEFAULT 'New',
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(relationship_profile_id) REFERENCES relationship_intelligence_profiles(id)
);

CREATE TABLE IF NOT EXISTS influence_roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  contact_id INTEGER,
  organization_id INTEGER,
  influence_role TEXT DEFAULT 'Unknown',
  influence_scope TEXT DEFAULT 'Unknown',
  influence_notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(contact_id, organization_id, influence_role),
  FOREIGN KEY(contact_id) REFERENCES contacts(id),
  FOREIGN KEY(organization_id) REFERENCES organizations(id)
);

CREATE TABLE IF NOT EXISTS relationship_wins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  relationship_profile_id INTEGER NOT NULL,
  win_type TEXT NOT NULL,
  win_status TEXT DEFAULT 'Potential',
  win_notes TEXT,
  win_date TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(relationship_profile_id) REFERENCES relationship_intelligence_profiles(id)
);

CREATE TABLE IF NOT EXISTS relationship_risks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  relationship_profile_id INTEGER NOT NULL,
  risk_type TEXT NOT NULL,
  severity TEXT DEFAULT 'Medium',
  reason TEXT,
  recommended_mitigation TEXT,
  status TEXT DEFAULT 'Open',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(relationship_profile_id) REFERENCES relationship_intelligence_profiles(id)
);

CREATE TABLE IF NOT EXISTS relationship_actions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  relationship_profile_id INTEGER NOT NULL,
  action_type TEXT NOT NULL,
  owner TEXT DEFAULT 'Unassigned',
  due_date TEXT,
  status TEXT DEFAULT 'Open',
  recommended_script TEXT,
  notes TEXT,
  outcome TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(relationship_profile_id) REFERENCES relationship_intelligence_profiles(id)
);

CREATE TABLE IF NOT EXISTS relationship_creation_signals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  source TEXT NOT NULL,
  region_id INTEGER,
  organization_name TEXT,
  contact_name TEXT,
  title TEXT,
  notes TEXT,
  confidence_score INTEGER DEFAULT 0,
  recommended_next_action TEXT,
  status TEXT DEFAULT 'New',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS channels (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  channel_name TEXT NOT NULL,
  channel_type TEXT NOT NULL,
  audience_type TEXT NOT NULL,
  region_id INTEGER,
  quality_score INTEGER DEFAULT 0,
  relationship_generation_score INTEGER DEFAULT 0,
  capacity_generation_score INTEGER DEFAULT 0,
  opportunity_generation_score INTEGER DEFAULT 0,
  quality_category TEXT DEFAULT 'Noise',
  status TEXT DEFAULT 'Testing',
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS content_opportunities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  content_type TEXT NOT NULL,
  audience TEXT,
  region_id INTEGER,
  source_signal_id INTEGER,
  source_type TEXT,
  strategic_value INTEGER DEFAULT 0,
  expected_capacity_impact INTEGER DEFAULT 0,
  expected_relationship_impact INTEGER DEFAULT 0,
  expected_opportunity_impact INTEGER DEFAULT 0,
  status TEXT DEFAULT 'Idea',
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id),
  FOREIGN KEY(source_signal_id) REFERENCES signals(id)
);

CREATE TABLE IF NOT EXISTS content_drafts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  content_opportunity_id INTEGER NOT NULL,
  draft_title TEXT NOT NULL,
  draft_body TEXT,
  draft_summary TEXT,
  draft_keywords TEXT,
  draft_cta TEXT,
  review_status TEXT DEFAULT 'Draft',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(content_opportunity_id) REFERENCES content_opportunities(id)
);

CREATE TABLE IF NOT EXISTS distribution_plans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  content_id INTEGER NOT NULL,
  channel_id INTEGER NOT NULL,
  distribution_reason TEXT,
  audience_match_score INTEGER DEFAULT 0,
  priority TEXT DEFAULT 'Medium',
  status TEXT DEFAULT 'Planned',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(content_id) REFERENCES content_opportunities(id),
  FOREIGN KEY(channel_id) REFERENCES channels(id)
);

CREATE TABLE IF NOT EXISTS demand_signals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  topic TEXT NOT NULL,
  demand_score INTEGER DEFAULT 0,
  trend_direction TEXT DEFAULT 'Stable',
  region_id INTEGER,
  audience TEXT,
  suggested_content TEXT,
  suggested_distribution TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS content_attributions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  content_id INTEGER NOT NULL,
  channel_id INTEGER,
  signals_created INTEGER DEFAULT 0,
  targets_created INTEGER DEFAULT 0,
  relationships_created INTEGER DEFAULT 0,
  subcontractors_created INTEGER DEFAULT 0,
  opportunities_created INTEGER DEFAULT 0,
  attribution_notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(content_id) REFERENCES content_opportunities(id),
  FOREIGN KEY(channel_id) REFERENCES channels(id)
);

CREATE TABLE IF NOT EXISTS daily_actions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  action_title TEXT NOT NULL,
  action_category TEXT NOT NULL,
  region_id INTEGER,
  owner TEXT,
  priority TEXT DEFAULT 'Medium',
  reason TEXT,
  recommended_next_step TEXT,
  linked_record_type TEXT,
  linked_record_id INTEGER,
  due_date TEXT,
  status TEXT DEFAULT 'Open',
  impact_score INTEGER DEFAULT 0,
  urgency_score INTEGER DEFAULT 0,
  confidence_score INTEGER DEFAULT 0,
  decision_score INTEGER DEFAULT 0,
  outcome_notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS regional_strategy_scorecards (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER,
  scorecard_date TEXT NOT NULL,
  capacity_score INTEGER DEFAULT 0,
  relationship_score INTEGER DEFAULT 0,
  opportunity_score INTEGER DEFAULT 0,
  demand_score INTEGER DEFAULT 0,
  signal_quality_score INTEGER DEFAULT 0,
  subcontractor_network_score INTEGER DEFAULT 0,
  hunt_execution_score INTEGER DEFAULT 0,
  risk_score INTEGER DEFAULT 0,
  overall_growth_score INTEGER DEFAULT 0,
  summary TEXT,
  top_blocker TEXT,
  recommended_focus TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS growth_blockers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  blocker_title TEXT NOT NULL,
  blocker_type TEXT NOT NULL,
  region_id INTEGER,
  severity TEXT DEFAULT 'Medium',
  reason TEXT,
  recommended_resolution TEXT,
  linked_record_type TEXT,
  linked_record_id INTEGER,
  status TEXT DEFAULT 'Open',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS opportunity_decisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  opportunity_id INTEGER NOT NULL,
  region_id INTEGER,
  pursue_score INTEGER DEFAULT 0,
  avoid_score INTEGER DEFAULT 0,
  recommended_decision TEXT DEFAULT 'Monitor',
  reason TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(opportunity_id) REFERENCES opportunities(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS capacity_recruitment_recommendations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER,
  discipline TEXT NOT NULL,
  needed_count INTEGER DEFAULT 0,
  urgency TEXT DEFAULT 'Medium',
  reason TEXT,
  linked_capacity_gap TEXT,
  suggested_sources TEXT,
  status TEXT DEFAULT 'Open',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS content_decisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  content_opportunity_id INTEGER,
  region_id INTEGER,
  audience TEXT,
  decision TEXT DEFAULT 'Review',
  reason TEXT,
  impact_score INTEGER DEFAULT 0,
  recommended_channel TEXT,
  status TEXT DEFAULT 'Open',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(content_opportunity_id) REFERENCES content_opportunities(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS relationship_decisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  relationship_profile_id INTEGER NOT NULL,
  region_id INTEGER,
  decision TEXT DEFAULT 'Monitor',
  reason TEXT,
  impact_score INTEGER DEFAULT 0,
  recommended_action TEXT,
  status TEXT DEFAULT 'Open',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(relationship_profile_id) REFERENCES relationship_intelligence_profiles(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS recommended_actions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  category TEXT NOT NULL,
  region_id INTEGER,
  priority TEXT NOT NULL,
  reason TEXT,
  recommended_next_action TEXT,
  assigned_owner TEXT,
  status TEXT DEFAULT 'Open',
  source_type TEXT,
  source_id INTEGER,
  source_module TEXT,
  recommendation_type TEXT,
  priority_score INTEGER DEFAULT 0,
  trigger_detail TEXT,
  why_it_matters TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS activities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  entity_type TEXT NOT NULL,
  entity_id INTEGER NOT NULL,
  region_id INTEGER,
  activity_type TEXT NOT NULL,
  title TEXT NOT NULL,
  notes TEXT,
  activity_date TEXT DEFAULT CURRENT_TIMESTAMP,
  owner TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);
