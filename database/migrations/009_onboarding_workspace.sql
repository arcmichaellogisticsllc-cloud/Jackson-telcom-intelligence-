CREATE TABLE IF NOT EXISTS subcontractor_onboarding (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  subcontractor_id INTEGER NOT NULL UNIQUE,
  region_id INTEGER,
  onboarding_status TEXT DEFAULT 'Prospect' CHECK(onboarding_status IN ('Prospect','Qualified','Documents Requested','Compliance Review','Capacity Review','Approved','Preferred','Strategic Partner','Rejected')),
  onboarding_score INTEGER DEFAULT 0,
  readiness_category TEXT DEFAULT 'Not Ready' CHECK(readiness_category IN ('Not Ready','Developing','Ready','Preferred','Strategic')),
  assigned_owner TEXT,
  w9_status TEXT DEFAULT 'Missing',
  coi_status TEXT DEFAULT 'Missing',
  msa_status TEXT DEFAULT 'Missing',
  nda_status TEXT DEFAULT 'Missing',
  safety_program_status TEXT DEFAULT 'Missing',
  coverage_area TEXT,
  disciplines TEXT,
  crew_counts TEXT,
  equipment_counts TEXT,
  missing_items TEXT,
  approval_notes TEXT,
  risk_flags TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(subcontractor_id) REFERENCES subcontractors(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS workforce_onboarding (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  workforce_profile_id INTEGER NOT NULL UNIQUE,
  region_id INTEGER,
  onboarding_status TEXT DEFAULT 'Candidate' CHECK(onboarding_status IN ('Candidate','Contacted','Interview','Evaluation','Offer','Accepted','Declined','Archived')),
  role TEXT NOT NULL,
  market TEXT,
  skills TEXT,
  certifications TEXT,
  experience TEXT,
  recruitability_score INTEGER DEFAULT 0,
  availability TEXT,
  onboarding_score INTEGER DEFAULT 0,
  readiness_category TEXT DEFAULT 'Not Ready' CHECK(readiness_category IN ('Not Ready','Developing','Ready','Preferred','Strategic')),
  assigned_owner TEXT,
  missing_items TEXT,
  risk_flags TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(workforce_profile_id) REFERENCES workforce_profiles(id) ON DELETE CASCADE,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS strategic_account_onboarding (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  strategic_account_id INTEGER NOT NULL UNIQUE,
  region_id INTEGER,
  onboarding_status TEXT DEFAULT 'Identified' CHECK(onboarding_status IN ('Identified','Researching','Relationship Mapping','Influence Mapping','Opportunity Mapping','Owner Assigned','Active Strategic Account')),
  account_owner TEXT,
  relationship_coverage INTEGER DEFAULT 0,
  influence_coverage INTEGER DEFAULT 0,
  opportunity_count INTEGER DEFAULT 0,
  capacity_demand INTEGER DEFAULT 0,
  account_readiness_score INTEGER DEFAULT 0,
  readiness_category TEXT DEFAULT 'Not Ready' CHECK(readiness_category IN ('Not Ready','Developing','Ready','Preferred','Strategic')),
  missing_items TEXT,
  risk_flags TEXT,
  next_action TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(strategic_account_id) REFERENCES strategic_accounts(id) ON DELETE CASCADE,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS market_onboarding (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  market_profile_id INTEGER NOT NULL UNIQUE,
  region_id INTEGER,
  market TEXT NOT NULL,
  onboarding_status TEXT DEFAULT 'Identified' CHECK(onboarding_status IN ('Identified','Researching','Utility Mapping','Prime Mapping','Capacity Mapping','Relationship Mapping','Market Ready')),
  utilities TEXT,
  engineering_firms TEXT,
  primes TEXT,
  subcontractors TEXT,
  workforce TEXT,
  strategic_accounts TEXT,
  opportunity_density INTEGER DEFAULT 0,
  market_readiness_score INTEGER DEFAULT 0,
  readiness_category TEXT DEFAULT 'Not Ready' CHECK(readiness_category IN ('Not Ready','Developing','Ready','Preferred','Strategic')),
  assigned_owner TEXT,
  missing_items TEXT,
  risk_flags TEXT,
  next_action TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(market_profile_id) REFERENCES market_intelligence_profiles(id) ON DELETE CASCADE,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS onboarding_reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  onboarding_type TEXT NOT NULL CHECK(onboarding_type IN ('Subcontractor','Workforce','Strategic Account','Market')),
  onboarding_id INTEGER NOT NULL,
  review_type TEXT NOT NULL CHECK(review_type IN ('Compliance Review','Capacity Review','Relationship Review','Strategic Review')),
  region_id INTEGER,
  status TEXT DEFAULT 'Pending' CHECK(status IN ('Pending','Approved','Rejected','Needs Information')),
  reviewer TEXT,
  review_notes TEXT,
  follow_up_action TEXT,
  reviewed_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS onboarding_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  onboarding_type TEXT NOT NULL CHECK(onboarding_type IN ('Subcontractor','Workforce','Strategic Account','Market')),
  onboarding_id INTEGER NOT NULL,
  region_id INTEGER,
  document_type TEXT NOT NULL CHECK(document_type IN ('W9','COI','NDA','MSA','Safety Program','Certifications','Coverage Maps','Workforce Documents','Other')),
  file_name TEXT NOT NULL,
  status TEXT DEFAULT 'Missing' CHECK(status IN ('Missing','Requested','Submitted','Approved','Expired','Rejected')),
  uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP,
  expires_at TEXT,
  reviewed_by TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE INDEX IF NOT EXISTS idx_subcontractor_onboarding_status ON subcontractor_onboarding(region_id, onboarding_status);
CREATE INDEX IF NOT EXISTS idx_workforce_onboarding_status ON workforce_onboarding(region_id, onboarding_status);
CREATE INDEX IF NOT EXISTS idx_account_onboarding_status ON strategic_account_onboarding(region_id, onboarding_status);
CREATE INDEX IF NOT EXISTS idx_market_onboarding_status ON market_onboarding(region_id, onboarding_status);
CREATE INDEX IF NOT EXISTS idx_onboarding_reviews_status ON onboarding_reviews(region_id, status);
CREATE INDEX IF NOT EXISTS idx_onboarding_documents_status ON onboarding_documents(region_id, status);
