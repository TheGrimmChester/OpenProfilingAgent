-- External Service Monitoring
CREATE TABLE IF NOT EXISTS opa.external_services (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  service_name String,
  service_type String, -- 'http', 'grpc', 'database', etc.
  base_url String,
  created_at DateTime DEFAULT now()
) ENGINE = MergeTree()
ORDER BY (organization_id, project_id, service_name);
