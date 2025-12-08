-- Slow Query Detection and Analysis
CREATE TABLE IF NOT EXISTS opa.slow_queries (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  query_id String,
  query_fingerprint String,
  query_text String,
  db_system String,
  trace_id String,
  span_id String,
  service String,
  duration_ms Float32,
  detected_at DateTime,
  stack_trace String DEFAULT ''
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(detected_at)
ORDER BY (organization_id, project_id, detected_at, duration_ms)
TTL detected_at + INTERVAL 30 DAY;
