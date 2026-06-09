CREATE TABLE IF NOT EXISTS real_hunt_enrichment_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  enrichment_type TEXT NOT NULL,
  source_record_type TEXT NOT NULL,
  source_record_id INTEGER NOT NULL,
  enriched_record_type TEXT,
  enriched_record_id INTEGER,
  confidence_score INTEGER DEFAULT 0,
  review_status TEXT DEFAULT 'Pending Review',
  source_url TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_real_hunt_enrichment_source ON real_hunt_enrichment_records(source_record_type, source_record_id);
CREATE INDEX IF NOT EXISTS idx_real_hunt_enrichment_type ON real_hunt_enrichment_records(enrichment_type, review_status);
