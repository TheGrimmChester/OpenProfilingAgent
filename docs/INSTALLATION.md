# OpenProfilingAgent Installation Guide

Complete installation instructions for OpenProfilingAgent in various environments.

> **⚠️ Early Stage Development**: This project is currently in early development. Some features may be buggy, incomplete, or not yet implemented. Use at your own risk and expect breaking changes.

## Table of Contents

- [System Requirements](#system-requirements)
- [Docker Installation](#docker-installation)
- [Manual Installation](#manual-installation)
- [PHP Extension Installation](#php-extension-installation)
- [Agent Installation](#agent-installation)
- [ClickHouse Setup](#clickhouse-setup)
- [Configuration](#configuration)
- [Verification](#verification)
- [Troubleshooting](#troubleshooting)

## System Requirements

### Minimum Requirements

- **PHP**: 8.0, 8.1, 8.2, 8.3, 8.4, or 8.5
- **Go**: 1.21 or later (for agent)
- **ClickHouse**: 23.3 or later
- **Linux**: Ubuntu 20.04+, Debian 11+, or similar
- **Memory**: 2GB RAM minimum, 4GB recommended
- **Disk**: 10GB free space minimum

### Required Libraries

- **LZ4**: For compression support (optional but recommended)
- **pthread**: For thread-safe operations
- **Build tools**: gcc, make, autoconf, libtool, pkg-config

## Docker Installation

The easiest way to get started with OpenProfilingAgent is using Docker Compose.

### Quick Start

```bash
# Clone the repository
git clone https://github.com/TheGrimmChester/OpenProfilingAgent.git
cd OpenProfilingAgent

# Copy environment configuration
cp env.example .env

# Build and start all services (--no-cache ensures everything is updated)
docker-compose build --no-cache
docker-compose up -d

# Check service status
docker-compose ps

# View logs
docker-compose logs -f agent
```

### Services

After starting, the following services will be available:

- **Dashboard**: http://localhost:3000
- **Prometheus**: http://localhost:9090
- **ClickHouse**: http://localhost:8123
- **Agent API**: http://localhost:8081
- **Agent Metrics**: http://localhost:2112/metrics

### Stopping Services

```bash
# Stop all services
docker-compose down

# Stop and remove volumes
docker-compose down -v
```

## Manual Installation

### PHP Extension Installation

#### Using Pre-built Extension

1. Download the extension for your PHP version from [GitHub Releases](https://github.com/TheGrimmChester/OpenProfilingAgent/releases)

2. Copy to PHP extensions directory:
```bash
sudo cp opa.so /usr/lib/php/$(php-config --extension-dir)/
```

3. Enable the extension:
```bash
echo "extension=opa.so" | sudo tee /etc/php/8.4/mods-available/opa.ini
sudo phpenmod opa
```

4. Configure INI settings (see [Configuration](#configuration))

5. Restart PHP-FPM:
```bash
sudo systemctl restart php8.4-fpm
```

#### Building from Source

1. Install dependencies:
```bash
sudo apt-get update
sudo apt-get install -y php8.4-dev liblz4-dev build-essential autoconf libtool pkg-config
```

2. Build the extension:
```bash
cd php-extension
./build.sh 8.4
```

3. Install:
```bash
sudo make install
```

4. Enable and configure (see above)

### Agent Installation

#### Using Pre-built Binary

1. Download from [GitHub Releases](https://github.com/TheGrimmChester/OpenProfilingAgent/releases)

2. Make executable:
```bash
chmod +x opa-agent
sudo mv opa-agent /usr/local/bin/
```

3. Create systemd service (see below)

#### Building from Source

```bash
cd agent
go build -o opa-agent .
sudo mv opa-agent /usr/local/bin/
```

#### Systemd Service

Create `/etc/systemd/system/opa-agent.service`:

```ini
[Unit]
Description=OpenProfilingAgent Agent
After=network.target clickhouse.service

[Service]
Type=simple
User=opa
ExecStart=/usr/local/bin/opa-agent \
  -socket /var/run/opa.sock \
  -tcp :9090 \
  -clickhouse http://localhost:8123 \
  -batch 100 \
  -batch_interval_ms 1000 \
  -sampling_rate 1.0
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable opa-agent
sudo systemctl start opa-agent
```

### ClickHouse Setup

#### Using Docker

```bash
docker run -d \
  --name clickhouse \
  -p 8123:8123 \
  -p 9000:9000 \
  -v clickhouse_data:/var/lib/clickhouse \
  clickhouse/clickhouse-server:23.3
```

#### Manual Installation

See [ClickHouse Documentation](https://clickhouse.com/docs/en/install)

#### Initialize Schema

```bash
# Copy schema files
docker cp clickhouse/docker-entrypoint-initdb.d clickhouse:/docker-entrypoint-initdb.d

# Or manually execute SQL files
clickhouse-client < clickhouse/docker-entrypoint-initdb.d/01_schema.sql
```

## Configuration

### PHP Extension

Edit `/etc/php/8.4/mods-available/opa.ini`:

```ini
extension=opa.so

; Enable/disable extension
opa.enabled=1

; Socket path (Unix socket or TCP address)
opa.socket_path=agent:9090

; Sampling rate (0.0 to 1.0)
opa.sampling_rate=1.0

; Full capture threshold (milliseconds)
opa.full_capture_threshold_ms=100

; Maximum stack depth
opa.stack_depth=20

; Buffer size (bytes)
opa.buffer_size=65536

; Collect internal functions
opa.collect_internal_functions=1

; Enable debug logging
opa.debug_log=0

; Organization and project metadata
opa.organization_id=default-org
opa.project_id=default-project

; Service name
opa.service=php-app

; Language metadata (auto-detected if not set)
opa.language=php
opa.language_version=

; Framework metadata (optional)
opa.framework=
opa.framework_version=

; Span expansion mode (1 = multiple spans from call stack, 0 = single span with nested call stack)
; Default: 1 (multiple spans mode)
opa.expand_spans=1
```

**Note**: `opa.socket_path` supports both Unix sockets (e.g., `/var/run/opa.sock`) and TCP/IP (e.g., `agent:9090` or `127.0.0.1:9090`). Detection is automatic: paths starting with `/` are Unix sockets, otherwise TCP/IP.

**Span Expansion Modes**:
- `opa.expand_spans=1` (default): Call stack nodes are converted to separate child spans. This provides better trace visualization with multiple spans per request.
- `opa.expand_spans=0`: Keeps the original behavior with a single span containing the full call stack nested inside. Useful for backward compatibility or when you prefer a simpler trace structure.

### Agent

Command-line flags:

```bash
-socket string          Unix socket path (default "/var/run/opa.sock")
-tcp string             TCP address to listen on (e.g., ":9090" or "0.0.0.0:9090")
-clickhouse string      ClickHouse URL (default "http://clickhouse:8123")
-batch int              Batch size (default 100)
-batch_interval_ms int  Batch interval in ms (default 1000)
-sampling_rate float    Default sampling rate (default 1.0)
-metrics string         Metrics address (default ":2112")
-api string             API address (default ":8080")
```

### Environment Variables

See `env.example` for all available environment variables.

For runtime configuration, see [Runtime Configuration Guide](RUNTIME_CONFIGURATION.md).

## Verification

### Check PHP Extension

```bash
# Check if extension is loaded
php -m | grep opa

# Check extension configuration
php --ri opa
```

### Check Agent

```bash
# Check agent health
curl http://localhost:8081/api/health

# Check agent statistics
curl http://localhost:8081/api/stats

# Check metrics
curl http://localhost:2112/metrics
```

### Test Connection

```bash
# Test Unix socket (if using)
nc -U /var/run/opa.sock

# Test TCP connection (if using)
telnet localhost 9090
```

### Generate Test Trace

Create a simple PHP script:

```php
<?php
// test.php
$spanId = opa_start_span('test_operation', ['test' => 'true']);
sleep(1);
opa_end_span($spanId);
echo "Trace sent!\n";
```

Run it:
```bash
php test.php
```

Then check the dashboard at http://localhost:3000 to see the trace.

## Troubleshooting

### Extension Not Loading

1. Check PHP error log:
```bash
tail -f /var/log/php8.4-fpm.log
```

2. Verify extension is installed:
```bash
php -m | grep opa
```

3. Check INI file syntax:
```bash
php --ini
```

4. Verify extension file exists:
```bash
ls -la $(php-config --extension-dir)/opa.so
```

### Agent Not Receiving Data

1. Check socket exists and permissions:
```bash
ls -la /var/run/opa.sock
```

2. Check agent logs:
```bash
journalctl -u opa-agent -f
# Or for Docker:
docker-compose logs -f agent
```

3. Test socket connection:
```bash
nc -U /var/run/opa.sock
```

4. Check agent configuration:
```bash
curl http://localhost:8081/api/stats
```

### ClickHouse Connection Issues

1. Test connection:
```bash
curl http://localhost:8123/ping
```

2. Check ClickHouse logs:
```bash
docker logs clickhouse
```

3. Verify network connectivity:
```bash
telnet localhost 8123
```

4. Check ClickHouse schema:
```bash
clickhouse-client --query "SHOW TABLES"
```

### Performance Issues

1. **High CPU usage**: Reduce sampling rate
   ```ini
   opa.sampling_rate=0.1
   ```

2. **Memory issues**: Reduce buffer size and stack depth
   ```ini
   opa.buffer_size=32768
   opa.stack_depth=10
   ```

3. **Slow writes**: Increase batch size and interval
   ```bash
   -batch 200 -batch_interval_ms 2000
   ```

4. **Queue overflow**: Increase MAX_QUEUE_SIZE or add more agents

### Common Errors

**"Permission denied" on socket**
```bash
sudo chmod 666 /var/run/opa.sock
# Or add PHP-FPM user to opa group
sudo usermod -aG opa www-data
```

**"Extension not found"**
- Verify extension path in php.ini
- Check PHP version compatibility
- Rebuild extension if needed

**"ClickHouse connection refused"**
- Verify ClickHouse is running
- Check firewall rules
- Verify URL in agent configuration

**"Agent not listening"**
- Check agent is running: `systemctl status opa-agent`
- Verify ports are not in use: `netstat -tulpn | grep 9090`
- Check agent logs for errors

## Next Steps

- See [User Guide](GUIDE.md) for usage examples
- See [Technical Documentation](TECHNICAL.md) for architecture details
- See [Features](FEATURES.md) for complete feature list
