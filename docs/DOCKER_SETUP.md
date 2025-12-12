# Docker Setup Guide

Complete step-by-step guide for setting up OpenProfilingAgent using Docker and Docker Compose.

> **⚠️ Early Stage Development**: This project is currently in early development. Some features may be buggy, incomplete, or not yet implemented. Use at your own risk and expect breaking changes.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Detailed Setup](#detailed-setup)
- [Service Configuration](#service-configuration)
- [PHP Application Integration](#php-application-integration)
- [CLI Profiling](#cli-profiling)
- [Verification](#verification)
- [Troubleshooting](#troubleshooting)
- [Service URLs and Ports](#service-urls-and-ports)

## Prerequisites

### System Requirements

- **Docker**: Version 20.10 or later
- **Docker Compose**: Version 2.0 or later (or docker-compose v1.29+)
- **Operating System**: Linux, macOS, or Windows with WSL2
- **Memory**: 2GB RAM minimum, 4GB recommended
- **Disk Space**: 10GB free space minimum
- **Network**: Ports 3000, 8081, 8082, 8123, 9000, 9090, 10090, 2112 available

### Verify Prerequisites

```bash
# Check Docker version
docker --version

# Check Docker Compose version
docker-compose --version
# or
docker compose version

# Verify Docker is running
docker ps
```

## Quick Start

For users who want to get started quickly:

```bash
# 1. Clone the repository
git clone https://github.com/TheGrimmChester/OpenProfilingAgent.git
cd OpenProfilingAgent

# 2. Create the Docker network
docker network create opa_network

# 3. Copy environment configuration
cp env.example .env

# 4. Start all services
docker-compose up -d

# 5. Verify services are running
docker-compose ps

# 6. Check agent health
curl http://localhost:8081/api/health
```

After completing these steps, you can access:
- **Dashboard**: http://localhost:3000
- **Agent API**: http://localhost:8081
- **ClickHouse**: http://localhost:8123
- **Prometheus**: http://localhost:9090

## Detailed Setup

### Step 1: Clone the Repository

```bash
git clone https://github.com/TheGrimmChester/OpenProfilingAgent.git
cd OpenProfilingAgent
```

### Step 2: Create Docker Network

OpenProfilingAgent services communicate over a Docker network. Create it before starting services:

```bash
docker network create opa_network
```

**Note**: If the network already exists, you'll see an error. This is normal and can be ignored.

### Step 3: Configure Environment Variables

Copy the example environment file and customize as needed:

```bash
cp env.example .env
```

Edit `.env` file to adjust configuration:

```bash
# For PHP applications running on the host (outside Docker)
OPA_SOCKET_PATH=host.docker.internal:10090

# For PHP applications running in Docker (same network)
# OPA_SOCKET_PATH=agent:9090

# Adjust sampling rate for production
OPA_SAMPLING_RATE=0.1
```

### Step 4: Build and Start Services

Build the Docker images:

```bash
docker-compose build
```

Start all services in detached mode:

```bash
docker-compose up -d
```

This will start:
- **ClickHouse**: Database for storing trace data
- **Agent**: Go service that processes and stores traces
- **Prometheus**: Metrics collection
- **Dashboard** (optional): Web UI for visualization

### Step 5: Verify Services

Check that all services are running:

```bash
docker-compose ps
```

You should see all services with status "Up" or "Up (healthy)".

View logs to ensure services started correctly:

```bash
# View all logs
docker-compose logs

# View specific service logs
docker-compose logs agent
docker-compose logs clickhouse

# Follow logs in real-time
docker-compose logs -f agent
```

### Step 6: Verify Health

Check agent health:

```bash
curl http://localhost:8081/api/health
```

Expected response:
```json
{"status":"ok"}
```

Check agent statistics:

```bash
curl http://localhost:8081/api/stats
```

## Service Configuration

### ClickHouse Service

ClickHouse stores all trace data. Configuration:

```yaml
ports:
  - "8123:8123"  # HTTP interface
  - "9000:9000"  # Native protocol
volumes:
  - clickhouse_data:/var/lib/clickhouse  # Persistent storage
```

**Access ClickHouse**:
- HTTP: http://localhost:8123
- Native: `clickhouse-client --host localhost --port 9000`

### Agent Service

The agent processes traces from PHP applications and stores them in ClickHouse.

**Configuration via Environment Variables**:
- `CLICKHOUSE_URL`: ClickHouse connection URL (default: `http://clickhouse:8123`)
- `SOCKET_PATH`: Unix socket path (default: `/var/run/opa.sock`)
- `TRANSPORT_TCP`: TCP address to listen on (default: `:9090`)
- `WS_PORT`: WebSocket port for real-time updates (default: `8082`)

**Ports**:
- `2112`: Metrics endpoint (Prometheus format)
- `8081`: HTTP API (mapped from container port 8080)
- `8082`: WebSocket for real-time updates
- `10090`: TCP transport for PHP extension (mapped from container port 9090)

### Dashboard Service

The dashboard provides a web UI for viewing traces and metrics.

**Access**: http://localhost:3000

**Profiles**:
- Production: `docker-compose --profile prod up dashboard`
- Development: `docker-compose up dashboard-dev`

### Prometheus Service

Prometheus collects metrics from the agent.

**Access**: http://localhost:9090

**Configuration**: `prometheus/prometheus.yml`

## PHP Application Integration

### Option 1: PHP Application in Docker (Recommended)

If your PHP application runs in Docker, add it to the same network:

```yaml
services:
  your-php-app:
    build: ./your-app
    networks:
      - opa_network
    environment:
      - OPA_ENABLED=1
      - OPA_SOCKET_PATH=agent:9090
      - OPA_SAMPLING_RATE=0.1
      - OPA_SERVICE=my-app
```

**Key Points**:
- Use `agent:9090` as the socket path (service name resolution)
- Ensure the PHP extension is installed in your PHP image
- Share the same `opa_network`

### Option 2: PHP Application on Host

If your PHP application runs on the host (outside Docker):

1. **Install PHP Extension**: See [Installation Guide](INSTALLATION.md#php-extension-installation)

2. **Configure Connection**: Use `host.docker.internal:10090` as the socket path:

```ini
; php.ini or opa.ini
opa.enabled=1
opa.socket_path=host.docker.internal:10090
opa.sampling_rate=0.1
```

**Note**: On Linux, `host.docker.internal` may not work. Use one of:
- Add `--add-host=host.docker.internal:host-gateway` to docker-compose
- Use the host's IP address directly
- Use Unix socket with volume mount

### Option 3: Unix Socket (Linux)

For better performance on Linux, use Unix socket:

1. **Mount socket volume** in your PHP application container:

```yaml
services:
  your-php-app:
    volumes:
      - opa_socket:/var/run
    environment:
      - OPA_SOCKET_PATH=/var/run/opa.sock
```

2. **Configure PHP extension**:

```ini
opa.socket_path=/var/run/opa.sock
```

## CLI Profiling

OpenProfilingAgent supports enabling profiling for command-line scripts using environment variables, without modifying code or configuration files.

### Basic Usage

Enable profiling for a single CLI command:

```bash
OPA_ENABLE=1 php script.php
```

This will profile the script even if `OPA_ENABLED=0` in your INI configuration.

### With Additional Configuration

You can combine `OPA_ENABLE` with other environment variables:

```bash
OPA_ENABLE=1 OPA_SAMPLING_RATE=1.0 php artisan migrate

OPA_ENABLE=1 OPA_SOCKET_PATH=agent:9090 php bin/console cache:clear
```

### Use Cases

**1. Debugging Specific Commands**

```bash
# Profile a migration to see performance
OPA_ENABLE=1 php artisan migrate

# Profile a cache clear operation
OPA_ENABLE=1 php bin/console cache:clear
```

**2. Performance Analysis**

```bash
# Profile with full sampling for detailed analysis
OPA_ENABLE=1 OPA_SAMPLING_RATE=1.0 php long-running-script.php
```

**3. Selective Profiling**

```bash
# Only profile when needed, not all CLI commands
OPA_ENABLE=1 php important-script.php
# Regular command without profiling
php regular-script.php
```

### How It Works

- The PHP extension checks for `OPA_ENABLE` environment variable at runtime (both CLI and web modes)
- If `OPA_ENABLE=1` or `OPA_ENABLE=true`, profiling is enabled
- If `OPA_ENABLE=0` or `OPA_ENABLE=false`, profiling is disabled
- This overrides the `OPA_ENABLED` INI setting for that specific execution
- **All OPA_* environment variables override INI settings** at runtime (not just OPA_ENABLE)
- No code changes or INI file modifications required

### Environment Variable Reference

| Variable | Values | Description |
|----------|--------|-------------|
| `OPA_ENABLE` | `1`, `true`, `0`, `false` | Enable/disable profiling (overrides `OPA_ENABLED` INI setting) |
| `OPA_SOCKET_PATH` | `host:port` or `/path/to/socket` | Agent connection path (overrides `opa.socket_path`) |
| `OPA_SAMPLING_RATE` | `0.0` to `1.0` | Sampling rate (overrides `opa.sampling_rate`) |
| `OPA_SERVICE` | string | Service name identifier (overrides `opa.service`) |
| `OPA_FULL_CAPTURE_THRESHOLD_MS` | integer | Full capture threshold in ms (overrides `opa.full_capture_threshold_ms`) |
| `OPA_STACK_DEPTH` | integer | Maximum stack depth (overrides `opa.stack_depth`) |
| `OPA_BUFFER_SIZE` | integer | Buffer size in bytes (overrides `opa.buffer_size`) |
| `OPA_DEBUG_LOG` | `1`, `0` | Enable debug logging (overrides `opa.debug_log`) |
| `OPA_ORGANIZATION_ID` | string | Organization identifier (overrides `opa.organization_id`) |
| `OPA_PROJECT_ID` | string | Project identifier (overrides `opa.project_id`) |

**Note**: All `OPA_*` environment variables override their corresponding INI settings at runtime. This works for both CLI and web modes.

## Verification

### 1. Check Service Status

```bash
docker-compose ps
```

All services should show "Up" status.

### 2. Check Agent Health

```bash
curl http://localhost:8081/api/health
```

Expected: `{"status":"ok"}`

### 3. Check Agent Statistics

```bash
curl http://localhost:8081/api/stats
```

This shows agent metrics including:
- Messages received
- Messages processed
- Queue size
- Errors

### 4. Test PHP Extension Connection

Create a test PHP script:

```php
<?php
// test_opa.php
if (function_exists('opa_start_span')) {
    echo "OPA extension is loaded\n";
    
    $spanId = opa_start_span('test_operation', ['test' => 'true']);
    sleep(1);
    opa_end_span($spanId);
    
    echo "Trace sent!\n";
} else {
    echo "OPA extension is NOT loaded\n";
}
```

Run it:

```bash
php test_opa.php
```

### 5. Check Dashboard

Open http://localhost:3000 in your browser. You should see:
- Service list
- Recent traces
- Metrics and charts

### 6. Verify ClickHouse

Check that ClickHouse is receiving data:

```bash
# Using HTTP interface
curl "http://localhost:8123/?query=SELECT count() FROM opa.spans"

# Or using clickhouse-client
docker exec -it clickhouse clickhouse-client --query "SELECT count() FROM opa.spans"
```

## Troubleshooting

### Services Won't Start

**Problem**: Services fail to start or exit immediately.

**Solutions**:
1. Check Docker logs:
   ```bash
   docker-compose logs
   ```

2. Verify network exists:
   ```bash
   docker network ls | grep opa_network
   ```
   If missing, create it:
   ```bash
   docker network create opa_network
   ```

3. Check port availability:
   ```bash
   # Check if ports are in use
   netstat -tulpn | grep -E '3000|8081|8123|9090'
   ```

4. Verify Docker has enough resources:
   ```bash
   docker system df
   docker system prune  # Clean up if needed
   ```

### Agent Not Receiving Traces

**Problem**: PHP application sends traces but agent doesn't receive them.

**Solutions**:
1. Check agent logs:
   ```bash
   docker-compose logs agent
   ```

2. Verify agent is healthy:
   ```bash
   curl http://localhost:8081/api/health
   ```

3. Check PHP extension configuration:
   ```bash
   php --ri opa
   ```
   Verify `opa.socket_path` is correct.

4. Test connection from PHP container:
   ```bash
   # If PHP app is in Docker
   docker exec -it your-php-container php -r "echo opa_socket_path();"
   ```

5. Verify network connectivity:
   ```bash
   # From PHP container
   docker exec -it your-php-container ping agent
   docker exec -it your-php-container nc -zv agent 9090
   ```

### ClickHouse Connection Issues

**Problem**: Agent cannot connect to ClickHouse.

**Solutions**:
1. Check ClickHouse is running:
   ```bash
   docker-compose ps clickhouse
   curl http://localhost:8123/ping
   ```

2. Verify ClickHouse URL in agent:
   ```bash
   docker-compose exec agent env | grep CLICKHOUSE_URL
   ```

3. Check ClickHouse logs:
   ```bash
   docker-compose logs clickhouse
   ```

4. Test connection from agent container:
   ```bash
   docker exec -it opa-agent wget -qO- http://clickhouse:8123/ping
   ```

### PHP Extension Not Loading

**Problem**: PHP extension is not loaded.

**Solutions**:
1. Check extension is installed:
   ```bash
   php -m | grep opa
   ```

2. Verify INI file:
   ```bash
   php --ini
   # Check for opa.ini in the scan directory
   ```

3. Check PHP error log:
   ```bash
   tail -f /var/log/php-fpm/error.log
   # or
   tail -f /var/log/php8.4-fpm.log
   ```

4. Verify extension file exists:
   ```bash
   ls -la $(php-config --extension-dir)/opa.so
   ```

### High Memory Usage

**Problem**: Services use too much memory.

**Solutions**:
1. Reduce sampling rate:
   ```ini
   opa.sampling_rate=0.1  # 10% sampling
   ```

2. Reduce buffer size:
   ```ini
   opa.buffer_size=32768  # Smaller buffer
   ```

3. Reduce stack depth:
   ```ini
   opa.stack_depth=10  # Shallow stack
   ```

4. Monitor with Docker stats:
   ```bash
   docker stats
   ```

### Dashboard Not Loading

**Problem**: Dashboard shows errors or doesn't load.

**Solutions**:
1. Check dashboard logs:
   ```bash
   docker-compose logs dashboard
   ```

2. Verify agent API is accessible:
   ```bash
   curl http://localhost:8081/api/health
   ```

3. Check browser console for errors (F12)

4. Verify dashboard is using correct API URL:
   ```bash
   docker-compose exec dashboard env | grep VITE_API
   ```

### Permission Issues (Unix Socket)

**Problem**: Permission denied when using Unix socket.

**Solutions**:
1. Check socket permissions:
   ```bash
   docker exec opa-agent ls -la /var/run/opa.sock
   ```

2. Ensure PHP-FPM user can access socket:
   ```bash
   # Check PHP-FPM user
   ps aux | grep php-fpm | head -1
   
   # Add user to opa group (if exists) or adjust permissions
   ```

3. Use TCP instead of Unix socket:
   ```ini
   opa.socket_path=agent:9090
   ```

## Service URLs and Ports

### Service Access Points

| Service | URL | Port | Description |
|---------|-----|------|-------------|
| Dashboard | http://localhost:3000 | 3000 | Web UI for traces and metrics |
| Agent API | http://localhost:8081 | 8081 | REST API for agent |
| Agent WebSocket | ws://localhost:8082 | 8082 | Real-time updates |
| Agent TCP | - | 10090 | TCP transport for PHP extension |
| Agent Metrics | http://localhost:2112/metrics | 2112 | Prometheus metrics |
| ClickHouse HTTP | http://localhost:8123 | 8123 | ClickHouse HTTP interface |
| ClickHouse Native | - | 9000 | ClickHouse native protocol |
| Prometheus | http://localhost:9090 | 9090 | Prometheus UI |

### Container-to-Container Communication

When services communicate within Docker network:

| Service | Internal Address | Port |
|---------|------------------|------|
| Agent API | `http://agent:8080` | 8080 |
| Agent TCP | `agent:9090` | 9090 |
| Agent WebSocket | `ws://agent:8082` | 8082 |
| ClickHouse | `http://clickhouse:8123` | 8123 |
| Dashboard | `http://opa-dashboard:80` | 80 |

### Network Configuration

All services use the `opa_network` Docker network:

```bash
# View network details
docker network inspect opa_network

# List connected containers
docker network inspect opa_network | grep -A 10 Containers
```

## Next Steps

- See [User Guide](GUIDE.md) for usage examples
- See [Runtime Configuration Guide](../php-extension/docs/RUNTIME_CONFIGURATION.md) for advanced configuration
- See [Installation Guide](INSTALLATION.md) for manual installation
- See [Technical Documentation](TECHNICAL.md) for architecture details

