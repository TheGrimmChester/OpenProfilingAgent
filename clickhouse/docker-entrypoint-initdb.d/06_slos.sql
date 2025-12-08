-- SLO/SLA definitions
CREATE TABLE IF NOT EXISTS opa.slos (
  id String,
  name String,
  description String,
  service String,
  slo_type String, -- 'availability', 'latency', 'error_rate'
  target_value Float32, -- Target percentage or value
  window_hours UInt32, -- Time window in hours (e.g., 30 days = 720 hours)
  created_at DateTime DEFAULT now(),
  updated_at DateTime DEFAULT now()
) ENGINE = MergeTree()
ORDER BY (service, created_at);

-- SLO metrics - calculated compliance metrics
CREATE TABLE IF NOT EXISTS opa.slo_metrics (
  slo_id String,
  service String,
  window_start DateTime,
  window_end DateTime,
  actual_value Float32, -- Actual measured value
  compliance_percentage Float32, -- Compliance percentage
  is_breach UInt8, -- 1 if SLO breached
  created_at DateTime DEFAULT now()
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(window_start)
ORDER BY (slo_id, window_start)
TTL window_start + INTERVAL 90 DAY;

-- Anomalies table
CREATE TABLE IF NOT EXISTS opa.anomalies (
  id String,
  type String, -- 'duration', 'error_rate', 'throughput'
  service String,
  metric String,
  value Float32,
  expected Float32,
  score Float32, -- 0-1, higher = more anomalous
  severity String, -- 'low', 'medium', 'high', 'critical'
  detected_at DateTime,
  metadata String, -- JSON
  created_at DateTime DEFAULT now()
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(detected_at)
ORDER BY (service, detected_at, severity)
TTL detected_at + INTERVAL 30 DAY;

-- Logs table for log/trace correlation
CREATE TABLE IF NOT EXISTS opa.logs (
  id String,
  trace_id String,
  span_id Nullable(String),
  service String,
  level String, -- debug, info, warn, error
  message String,
  timestamp DateTime64(3),
  fields String, -- JSON
  created_at DateTime DEFAULT now()
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(timestamp)
ORDER BY (trace_id, timestamp)
TTL toDateTime(timestamp) + INTERVAL 7 DAY;

-- Index for log lookups
ALTER TABLE opa.logs ADD INDEX IF NOT EXISTS idx_trace_id trace_id TYPE bloom_filter GRANULARITY 1;
ALTER TABLE opa.logs ADD INDEX IF NOT EXISTS idx_span_id span_id TYPE bloom_filter GRANULARITY 1;

