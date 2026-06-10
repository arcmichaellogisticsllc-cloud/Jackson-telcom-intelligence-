CREATE TABLE IF NOT EXISTS intelligence_streams (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stream_name TEXT NOT NULL UNIQUE,
  stream_type TEXT NOT NULL CHECK(stream_type IN ('Broadband Funding','Strategic Account','Engineering Firm','Contractor Discovery','Prime Contractor')),
  primary_question TEXT NOT NULL CHECK(primary_question IN ('Who Has Work','Who Has Capacity','Who Influences Work')),
  collection_method TEXT NOT NULL CHECK(collection_method IN ('Official Fetch','Public Page Monitor','Search Query','CSV Import','Manual Review','Connector Import')),
  cadence TEXT NOT NULL CHECK(cadence IN ('Daily','Weekly','Monthly')),
  backfill_days INTEGER DEFAULT 90,
  confidence_baseline INTEGER DEFAULT 50,
  active INTEGER DEFAULT 1,
  owner TEXT,
  region_scope TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS organization_classifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  organization_id INTEGER NOT NULL,
  classification TEXT NOT NULL CHECK(classification IN ('Has Work','Has Capacity','Needs Work','Influences Work','Competitor','Funding Source','Strategic Account','Engineering Firm','Prime Contractor','Capacity Provider','Municipal / Public Entity')),
  confidence_score INTEGER DEFAULT 50,
  source_url TEXT,
  review_status TEXT DEFAULT 'Pending Review' CHECK(review_status IN ('Pending Review','Verified','Needs Review','Rejected')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_organization_classifications_unique ON organization_classifications(organization_id, classification);

CREATE TABLE IF NOT EXISTS contact_role_access_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  contact_id INTEGER NOT NULL,
  organization_id INTEGER NOT NULL,
  role_type TEXT,
  access_category TEXT,
  decision_authority_score INTEGER DEFAULT 25,
  influence_score INTEGER DEFAULT 25,
  access_score INTEGER DEFAULT 25,
  trust_score INTEGER DEFAULT 20,
  strategic_value_score INTEGER DEFAULT 25,
  confidence_score INTEGER DEFAULT 50,
  source_url TEXT,
  review_status TEXT DEFAULT 'Pending Review' CHECK(review_status IN ('Pending Review','Verified','Needs Review','Rejected')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_contact_role_access_unique ON contact_role_access_profiles(contact_id, organization_id, role_type, access_category);

CREATE TABLE IF NOT EXISTS source_evidence_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  linked_record_type TEXT NOT NULL,
  linked_record_id INTEGER NOT NULL,
  intelligence_stream_id INTEGER,
  source_url TEXT,
  source_name TEXT,
  source_type TEXT,
  collected_at TEXT DEFAULT CURRENT_TIMESTAMP,
  confidence_score INTEGER DEFAULT 50,
  evidence_summary TEXT,
  review_status TEXT DEFAULT 'Pending Review' CHECK(review_status IN ('Pending Review','Verified','Needs Review','Rejected')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(intelligence_stream_id) REFERENCES intelligence_streams(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_source_evidence_linked ON source_evidence_records(linked_record_type, linked_record_id);
CREATE INDEX IF NOT EXISTS idx_source_evidence_stream ON source_evidence_records(intelligence_stream_id, review_status);

CREATE TABLE IF NOT EXISTS intelligence_stream_import_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stream_key TEXT NOT NULL,
  source_file_path TEXT,
  source_row INTEGER,
  organization_id INTEGER,
  contact_id INTEGER,
  signal_id INTEGER,
  opportunity_id INTEGER,
  subcontractor_id INTEGER,
  capacity_profile_id INTEGER,
  recommended_action_id INTEGER,
  raw_signal_item_id INTEGER,
  review_status TEXT DEFAULT 'Pending Review' CHECK(review_status IN ('Pending Review','Verified','Needs Review','Rejected','Imported','Skipped')),
  confidence_score INTEGER DEFAULT 50,
  source_url TEXT,
  evidence_summary TEXT,
  raw_payload_json TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
  FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
  FOREIGN KEY(signal_id) REFERENCES signals(id) ON DELETE SET NULL,
  FOREIGN KEY(opportunity_id) REFERENCES opportunities(id) ON DELETE SET NULL,
  FOREIGN KEY(subcontractor_id) REFERENCES subcontractors(id) ON DELETE SET NULL,
  FOREIGN KEY(capacity_profile_id) REFERENCES capacity_profiles(id) ON DELETE SET NULL,
  FOREIGN KEY(recommended_action_id) REFERENCES recommended_actions(id) ON DELETE SET NULL,
  FOREIGN KEY(raw_signal_item_id) REFERENCES raw_signal_items(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_intelligence_stream_import_records_stream ON intelligence_stream_import_records(stream_key, review_status);
CREATE INDEX IF NOT EXISTS idx_intelligence_stream_import_records_org ON intelligence_stream_import_records(organization_id);
