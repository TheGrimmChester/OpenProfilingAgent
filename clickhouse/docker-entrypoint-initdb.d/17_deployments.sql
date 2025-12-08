-- Deployment Tracking
CREATE TABLE IF NOT EXISTS opa.deployments (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  deployment_id String,
  service String,
  version String,
  environment String,
  deployed_at DateTime,
  deployed_by String,
  description String DEFAULT ''
) ENGINE = MergeTree()
ORDER BY (organization_id, project_id, service, deployed_at);
