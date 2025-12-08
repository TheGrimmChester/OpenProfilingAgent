-- Response Time Breakdown: Track time spent in different components
CREATE TABLE IF NOT EXISTS opa.response_breakdown (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  service String,
  endpoint String,
  date Date,
  hour DateTime,
  db_time_ms Float64,
  external_time_ms Float64,
  cache_time_ms Float64,
  redis_time_ms Float64,
  application_time_ms Float64,
  total_time_ms Float64,
  request_count UInt64
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, service, endpoint, date, hour);

-- Index for efficient lookups (Note: ALTER TABLE ADD INDEX must be run separately)

