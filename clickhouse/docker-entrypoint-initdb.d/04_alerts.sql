-- Alerts system tables
CREATE TABLE IF NOT EXISTS opa.alerts (
  id String,
  name String,
  description String,
  enabled UInt8 DEFAULT 1,
  condition_type String, -- 'duration', 'error_rate', 'throughput', 'custom'
  condition_config String, -- JSON config for condition
  action_type String, -- 'email', 'webhook', 'slack'
  action_config String, -- JSON config for action
  service Nullable(String),
  language Nullable(String),
  framework Nullable(String),
  created_at DateTime DEFAULT now(),
  updated_at DateTime DEFAULT now()
) ENGINE = MergeTree()
ORDER BY (enabled, created_at);

-- Alert history - tracks when alerts were triggered
CREATE TABLE IF NOT EXISTS opa.alert_history (
  alert_id String,
  triggered_at DateTime,
  condition_value String, -- JSON with actual values that triggered
  action_result String, -- JSON with action execution result
  resolved_at Nullable(DateTime)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(triggered_at)
ORDER BY (alert_id, triggered_at)
TTL triggered_at + INTERVAL 30 DAY;

