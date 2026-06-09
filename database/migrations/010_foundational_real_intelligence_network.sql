CREATE TABLE IF NOT EXISTS real_hunt_import_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  dataset TEXT NOT NULL,
  source_file TEXT NOT NULL,
  source_row INTEGER NOT NULL,
  import_source TEXT DEFAULT 'real_hunt',
  source_url TEXT,
  source_type TEXT,
  confidence_score INTEGER DEFAULT 0,
  review_status TEXT DEFAULT 'Pending Review',
  raw_signal_item_id INTEGER,
  signal_id INTEGER,
  created_record_type TEXT,
  created_record_id INTEGER,
  status TEXT DEFAULT 'Imported' CHECK(status IN ('Imported','Skipped','Needs Review','Error')),
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(raw_signal_item_id) REFERENCES raw_signal_items(id),
  FOREIGN KEY(signal_id) REFERENCES signals(id)
);

CREATE INDEX IF NOT EXISTS idx_real_hunt_import_dataset ON real_hunt_import_records(dataset, status);
CREATE INDEX IF NOT EXISTS idx_real_hunt_import_source ON real_hunt_import_records(import_source, review_status);
