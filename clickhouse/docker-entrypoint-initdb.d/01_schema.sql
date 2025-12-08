CREATE DATABASE IF NOT EXISTS opa;

CREATE TABLE IF NOT EXISTS opa.spans_min (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  trace_id String,
  span_id String,
  parent_id Nullable(String),
  service String,
  name String,
  url_scheme Nullable(String),
  url_host Nullable(String),
  url_path Nullable(String),
  start_ts DateTime64(3),
  end_ts DateTime64(3),
  duration_ms Float32,
  cpu_ms Float32,
  status String,
  db_system Nullable(String),
  query_fingerprint Nullable(String),
  bytes_sent UInt64 DEFAULT 0,
  bytes_received UInt64 DEFAULT 0,
  http_requests_count UInt16 DEFAULT 0,
  cache_operations_count UInt16 DEFAULT 0,
  redis_operations_count UInt16 DEFAULT 0,
  cache_hit_rate Float32 DEFAULT 0
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(start_ts)
ORDER BY (organization_id, project_id, service, start_ts);

-- Index for trace lookups
ALTER TABLE opa.spans_min ADD INDEX IF NOT EXISTS idx_trace_id trace_id TYPE bloom_filter GRANULARITY 1;
