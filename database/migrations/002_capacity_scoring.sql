CREATE TABLE IF NOT EXISTS capacity_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER NOT NULL,
  service_type TEXT NOT NULL,
  target_crews INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1,
  UNIQUE(region_id, service_type),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

