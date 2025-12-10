-- Service Map: Track service dependencies and relationships
CREATE TABLE IF NOT EXISTS opa.service_dependencies (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  from_service String,
  to_service String,
  dependency_type String DEFAULT 'service', -- 'service', 'database', 'http', 'redis', 'cache'
  dependency_target String DEFAULT '', -- e.g., "mysql://db.example.com", "https://api.example.com"
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
ORDER BY (organization_id, project_id, from_service, to_service, dependency_type, dependency_target, date, hour);

-- Service map metadata (current state)
CREATE TABLE IF NOT EXISTS opa.service_map_metadata (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  from_service String,
  to_service String,
  last_seen DateTime,
  avg_latency_ms Float32,
  p95_latency_ms Float32 DEFAULT 0,
  p99_latency_ms Float32 DEFAULT 0,
  error_rate Float32,
  call_count UInt64,
  throughput Float32 DEFAULT 0, -- requests per second
  bytes_sent UInt64 DEFAULT 0,
  bytes_received UInt64 DEFAULT 0,
  health_status String DEFAULT 'healthy', -- healthy, degraded, down
  service_type String DEFAULT '', -- optional: api, database, cache, etc.
  environment String DEFAULT '' -- optional: production, staging, etc.
) ENGINE = ReplacingMergeTree(last_seen)
ORDER BY (organization_id, project_id, from_service, to_service);

-- Add new columns to existing table (for migrations)
ALTER TABLE opa.service_map_metadata ADD COLUMN IF NOT EXISTS p95_latency_ms Float32 DEFAULT 0;
ALTER TABLE opa.service_map_metadata ADD COLUMN IF NOT EXISTS p99_latency_ms Float32 DEFAULT 0;
ALTER TABLE opa.service_map_metadata ADD COLUMN IF NOT EXISTS throughput Float32 DEFAULT 0;
ALTER TABLE opa.service_map_metadata ADD COLUMN IF NOT EXISTS bytes_sent UInt64 DEFAULT 0;
ALTER TABLE opa.service_map_metadata ADD COLUMN IF NOT EXISTS bytes_received UInt64 DEFAULT 0;
ALTER TABLE opa.service_map_metadata ADD COLUMN IF NOT EXISTS service_type String DEFAULT '';
ALTER TABLE opa.service_map_metadata ADD COLUMN IF NOT EXISTS environment String DEFAULT '';

-- Health threshold configuration table
CREATE TABLE IF NOT EXISTS opa.service_map_thresholds (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  degraded_error_rate Float32 DEFAULT 10.0, -- percentage
  down_error_rate Float32 DEFAULT 50.0, -- percentage
  degraded_latency_ms Float32 DEFAULT 1000.0, -- milliseconds
  down_latency_ms Float32 DEFAULT 5000.0, -- milliseconds
  updated_at DateTime DEFAULT now()
) ENGINE = ReplacingMergeTree(updated_at)
ORDER BY (organization_id, project_id);

-- Insert default thresholds
INSERT INTO opa.service_map_thresholds (organization_id, project_id, degraded_error_rate, down_error_rate, degraded_latency_ms, down_latency_ms)
SELECT 'default-org', 'default-project', 10.0, 50.0, 1000.0, 5000.0
WHERE NOT EXISTS (SELECT 1 FROM opa.service_map_thresholds WHERE organization_id = 'default-org' AND project_id = 'default-project');

-- Add new columns to existing table (for migrations)
ALTER TABLE opa.service_dependencies ADD COLUMN IF NOT EXISTS dependency_type String DEFAULT 'service';
ALTER TABLE opa.service_dependencies ADD COLUMN IF NOT EXISTS dependency_target String DEFAULT '';

-- Indexes for efficient lookups
ALTER TABLE opa.service_dependencies ADD INDEX IF NOT EXISTS idx_services (from_service, to_service) TYPE bloom_filter GRANULARITY 1;
ALTER TABLE opa.service_dependencies ADD INDEX IF NOT EXISTS idx_dependency_type (dependency_type) TYPE bloom_filter GRANULARITY 1;
ALTER TABLE opa.service_map_metadata ADD INDEX IF NOT EXISTS idx_services_meta (from_service, to_service) TYPE bloom_filter GRANULARITY 1;

