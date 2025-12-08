-- Custom dashboards table
CREATE TABLE IF NOT EXISTS opa.dashboards (
  id String,
  name String,
  description String,
  config String, -- JSON configuration for widgets
  user_id Nullable(String), -- NULL for shared dashboards
  is_shared UInt8 DEFAULT 0,
  created_at DateTime DEFAULT now(),
  updated_at DateTime DEFAULT now()
) ENGINE = MergeTree()
ORDER BY (is_shared, created_at);

