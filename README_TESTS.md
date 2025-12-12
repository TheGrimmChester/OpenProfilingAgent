# Running Tests in Docker Containers

All E2E tests can now be run inside Docker containers with all dependencies pre-configured. This ensures consistent test environments and eliminates dependency issues.

## Quick Start

### Run All Tests

```bash
./run-tests.sh
```

### Run a Specific Test

```bash
./run-tests.sh php-extension/tests/e2e/http_errors/test_http_errors_e2e.sh
```

### Run with Verbose Output

```bash
./run-tests.sh --verbose
```

### Keep Services Running After Tests

```bash
./run-tests.sh --no-cleanup
```

## Test Infrastructure

The test infrastructure includes:

- **ClickHouse**: Database for storing test data
- **Agent**: OPA agent for processing spans
- **MySQL**: Database for SQL tests
- **PHP Test Container**: PHP 8.4 with OPA extension and all dependencies
- **Nginx**: Web server for HTTP endpoint tests

## Test Container Features

The PHP test container (`opa_php_test`) includes:

- PHP 8.4 with FPM
- OPA extension (built from source)
- All PHP extensions (sockets, mysqli, PDO, APCu, Redis)
- System tools (curl, wget, jq, bash, netcat)
- Composer
- All test scripts mounted at `/app/tests`

## Environment Detection

Tests automatically detect if they're running in a container and adjust URLs:

- **In Container**: Uses service names (e.g., `http://agent:8080`, `http://nginx-test`)
- **On Host**: Uses localhost (e.g., `http://localhost:8081`, `http://localhost:8088`)

## Manual Test Execution

You can also run tests manually inside the container:

```bash
# Start services
docker-compose -f docker-compose.test.yml up -d

# Run a test
docker exec -it opa_php_test bash -c "cd /app/tests && bash e2e/http_errors/test_http_errors_e2e.sh"

# Stop services
docker-compose -f docker-compose.test.yml down
```

## Test Endpoints

Test endpoints are available at:
- HTTP endpoints: `http://nginx-test` (in container) or `http://localhost:8088` (on host)
- Agent API: `http://agent:8080` (in container) or `http://localhost:8081` (on host)

## Available Tests

All E2E tests are located in `php-extension/tests/e2e/`:

- `http_errors/` - HTTP 4xx and 5xx error tests
- `curl/` - cURL profiling tests
- `sql/` - SQL query profiling tests
- `errors/` - Error tracking tests
- `spans/` - Span generation tests

## Troubleshooting

### Services Not Starting

```bash
# Check service status
docker-compose -f docker-compose.test.yml ps

# View logs
docker-compose -f docker-compose.test.yml logs

# Rebuild containers
docker-compose -f docker-compose.test.yml build --no-cache
```

### Network Issues

Ensure the `opa_network` exists:

```bash
docker network create opa_network
```

### Permission Issues

If test endpoints can't be created, ensure the test container has write access or create them manually.

