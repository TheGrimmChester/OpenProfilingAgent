-- Service Map: Track service dependencies and relationships
CREATE TABLE IF NOT EXISTS opa.service_dependencies (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  from_service String,
  to_service String,
  date Date,
  hour DateTime,
  call_count UInt64,
  total_duration_ms Float64,
  avg_duration_ms Float32,
  max_duration_ms Float32,
  error_count UInt64,
  error_rate Float32,
  bytes_sent UInt64 DEFAULT 0,
  bytes_received UInt64 DEFAULT 0
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, from_service, to_service, date, hour);

-- Service map metadata (current state)
CREATE TABLE IF NOT EXISTS opa.service_map_metadata (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  from_service String,
  to_service String,
  last_seen DateTime,
  avg_latency_ms Float32,
  error_rate Float32,
  call_count UInt64,
  health_status String DEFAULT 'healthy' -- healthy, degraded, down
) ENGINE = ReplacingMergeTree(last_seen)
ORDER BY (organization_id, project_id, from_service, to_service);

-- Indexes for efficient lookups
ALTER TABLE opa.service_dependencies ADD INDEX IF NOT EXISTS idx_services (from_service, to_service) TYPE bloom_filter GRANULARITY 1;
ALTER TABLE opa.service_map_metadata ADD INDEX IF NOT EXISTS idx_services_meta (from_service, to_service) TYPE bloom_filter GRANULARITY 1;

