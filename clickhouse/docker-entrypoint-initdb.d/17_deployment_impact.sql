-- Deployment Impact Analysis
CREATE TABLE IF NOT EXISTS opa.deployment_impact (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  deployment_id String,
  service String,
  metric_type String, -- 'error_rate', 'latency', 'throughput'
  pre_value Float64,
  post_value Float64,
  change_percent Float64,
  measured_at DateTime
) ENGINE = MergeTree()
ORDER BY (organization_id, project_id, deployment_id, service);

