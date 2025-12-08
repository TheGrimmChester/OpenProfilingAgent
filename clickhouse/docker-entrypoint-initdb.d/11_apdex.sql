-- Apdex Scores: Application Performance Index tracking
CREATE TABLE IF NOT EXISTS opa.apdex_scores (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  service String,
  endpoint String,
  date Date,
  hour DateTime,
  satisfied_count UInt64, -- Requests < satisfied threshold (default 500ms)
  tolerating_count UInt64, -- Requests between satisfied and tolerating threshold (default 2000ms)
  frustrated_count UInt64, -- Requests > tolerating threshold
  total_count UInt64,
  apdex_score Float32, -- (satisfied + tolerating/2) / total
  satisfied_threshold_ms Float32 DEFAULT 500,
  tolerating_threshold_ms Float32 DEFAULT 2000
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, service, endpoint, date, hour);

-- Index for efficient lookups
ALTER TABLE opa.apdex_scores ADD INDEX IF NOT EXISTS idx_apdex (service, endpoint, date) TYPE bloom_filter GRANULARITY 1;

