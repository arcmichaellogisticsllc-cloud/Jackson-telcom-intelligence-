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
  markets_served TEXT,
  services_offered TEXT,
  crew_count INTEGER DEFAULT 0,
  aerial_crew_count INTEGER DEFAULT 0,
  underground_crew_count INTEGER DEFAULT 0,
  fiber_splicing_crew_count INTEGER DEFAULT 0,
  emergency_restoration_crew_count INTEGER DEFAULT 0,
  traffic_control_crew_count INTEGER DEFAULT 0,
  bucket_trucks INTEGER DEFAULT 0,
  directional_drills INTEGER DEFAULT 0,
  splicing_trailers INTEGER DEFAULT 0,
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

CREATE TABLE IF NOT EXISTS opportunities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  organization_id INTEGER,
  region_id INTEGER NOT NULL,
  market TEXT,
  estimated_value REAL DEFAULT 0,
  estimated_margin REAL DEFAULT 0,
  probability INTEGER DEFAULT 0,
  stage TEXT,
  capacity_required INTEGER DEFAULT 0,
  decision_makers TEXT,
  next_action TEXT,
  owner TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
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
