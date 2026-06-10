CREATE TABLE IF NOT EXISTS enrichment_sources (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  source_name TEXT NOT NULL UNIQUE,
  source_type TEXT NOT NULL,
  source_url TEXT,
  region_id INTEGER,
  state TEXT,
  market TEXT,
  purpose TEXT NOT NULL CHECK(purpose IN ('Work','Capacity','Need','Influence','Competitive','Market','Executive Strategy')),
  collection_method TEXT NOT NULL CHECK(collection_method IN ('Official Fetch','RSS/Feed','Public Page Monitor','Search Query','CSV Import','Manual Review','Connector Import')),
  cadence TEXT NOT NULL CHECK(cadence IN ('Daily','Weekly','Monthly','Quarterly')),
  backfill_days INTEGER DEFAULT 90,
  confidence_baseline INTEGER DEFAULT 50,
  active INTEGER DEFAULT 1,
  last_run_at TEXT,
  next_run_at TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS scheduled_enrichment_runs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  enrichment_source_id INTEGER NOT NULL,
  started_at TEXT DEFAULT CURRENT_TIMESTAMP,
  finished_at TEXT,
  status TEXT DEFAULT 'Pending' CHECK(status IN ('Pending','Running','Completed','Failed','Review Required')),
  records_found INTEGER DEFAULT 0,
  raw_signals_created INTEGER DEFAULT 0,
  candidate_records_created INTEGER DEFAULT 0,
  data_quality_issues_created INTEGER DEFAULT 0,
  skipped_count INTEGER DEFAULT 0,
  error_count INTEGER DEFAULT 0,
  error_message TEXT,
  run_notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(enrichment_source_id) REFERENCES enrichment_sources(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS search_query_registry (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  query TEXT NOT NULL,
  purpose TEXT NOT NULL CHECK(purpose IN ('Work','Capacity','Need','Influence','Competitive','Market','Workforce')),
  region_id INTEGER,
  state TEXT,
  market TEXT,
  cadence TEXT NOT NULL CHECK(cadence IN ('Daily','Weekly','Monthly','Quarterly')),
  backfill_days INTEGER DEFAULT 90,
  source_type TEXT,
  active INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS manual_research_tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  enrichment_source_id INTEGER,
  search_query_id INTEGER,
  source TEXT,
  query TEXT,
  purpose TEXT NOT NULL,
  region_id INTEGER,
  due_date TEXT,
  assigned_owner TEXT,
  instructions TEXT NOT NULL,
  status TEXT DEFAULT 'Open' CHECK(status IN ('Open','In Progress','Completed','Skipped')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(enrichment_source_id) REFERENCES enrichment_sources(id) ON DELETE SET NULL,
  FOREIGN KEY(search_query_id) REFERENCES search_query_registry(id) ON DELETE SET NULL,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS source_confidence_rules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  source_category TEXT NOT NULL UNIQUE,
  min_score INTEGER NOT NULL,
  max_score INTEGER NOT NULL,
  review_required INTEGER DEFAULT 0,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS intelligence_growth_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  target_name TEXT NOT NULL UNIQUE,
  record_type TEXT NOT NULL,
  milestone TEXT NOT NULL CHECK(milestone IN ('30 Days','90 Days','12 Months','Long Term')),
  target_count INTEGER NOT NULL,
  verified_count INTEGER DEFAULT 0,
  pending_review_count INTEGER DEFAULT 0,
  current_count INTEGER DEFAULT 0,
  progress_percentage INTEGER DEFAULT 0,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_enrichment_sources_due ON enrichment_sources(active, cadence, next_run_at);
CREATE INDEX IF NOT EXISTS idx_scheduled_enrichment_runs_source ON scheduled_enrichment_runs(enrichment_source_id, started_at);
CREATE INDEX IF NOT EXISTS idx_search_query_registry_active ON search_query_registry(active, cadence, region_id);
CREATE INDEX IF NOT EXISTS idx_manual_research_tasks_status ON manual_research_tasks(status, due_date, region_id);
CREATE INDEX IF NOT EXISTS idx_intelligence_growth_targets_record ON intelligence_growth_targets(record_type, milestone);
