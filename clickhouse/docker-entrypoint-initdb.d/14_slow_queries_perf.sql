-- Query Performance Analysis (aggregated by fingerprint)
CREATE TABLE IF NOT EXISTS opa.query_performance (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  query_fingerprint String,
  db_system String,
  date Date,
  hour DateTime,
  execution_count UInt64,
  total_duration_ms Float64,
  avg_duration_ms Float32,
  p95_duration_ms Float32,
  p99_duration_ms Float32,
  max_duration_ms Float32,
  min_duration_ms Float32
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, query_fingerprint, date, hour);

