-- Throughput Metrics: Requests per minute/hour tracking
CREATE TABLE IF NOT EXISTS opa.throughput_metrics (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  service String,
  endpoint String,
  date Date,
  hour DateTime,
  minute DateTime,
  requests_per_minute UInt64,
  requests_per_hour UInt64
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, service, endpoint, date, hour, minute);

-- Index for efficient lookups
ALTER TABLE opa.throughput_metrics ADD INDEX IF NOT EXISTS idx_throughput (service, endpoint, date) TYPE bloom_filter GRANULARITY 1;

