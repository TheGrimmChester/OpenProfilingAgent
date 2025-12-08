-- External Service Metrics
CREATE TABLE IF NOT EXISTS opa.external_service_metrics (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  service_name String,
  date Date,
  hour DateTime,
  request_count UInt64,
  total_duration_ms Float64,
  avg_duration_ms Float32,
  error_count UInt64,
  error_rate Float32,
  bytes_sent UInt64,
  bytes_received UInt64
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, service_name, date, hour);

