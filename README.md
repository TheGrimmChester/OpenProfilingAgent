# OpenProfilingAgent

A complete, self-hosted Application Performance Monitoring (APM) system for PHP applications and beyond. Built with a PHP extension (C), Go agent, ClickHouse storage, and React dashboard.

> **⚠️ Early Stage Development**: This project is currently in early development. Some features may be buggy, incomplete, or not yet implemented. Use at your own risk and expect breaking changes. We welcome feedback and contributions to help improve the project.

## Overview

OpenProfilingAgent provides comprehensive performance monitoring with minimal overhead. It automatically instruments your PHP applications to capture traces, metrics, and performance data, storing everything in ClickHouse for fast querying and analysis.

### Key Features

- **🌟 Live Dumps**: Real-time variable inspection and debugging with live dump capture during execution
- **Automatic Instrumentation**: Zero-code monitoring of function calls, SQL queries, HTTP requests, and file I/O
- **Low Overhead**: Minimal performance impact with configurable sampling
- **High Performance**: Efficient span processing and storage
- **Self-Hosted**: Complete control over your data
- **PHP Support**: PHP extension with helper library
- **Real-Time Dashboard**: Modern React-based UI for trace visualization
- **Scalable**: Horizontal scaling with multiple agents

## Featured: Live Dumps

Capture and inspect variables in real-time during execution. Live Dumps allow you to see the exact state of your application at any point in the execution flow, making debugging faster and more intuitive.

![Live Dumps - Real-time variable inspection](https://raw.githubusercontent.com/TheGrimmChester/OPA-Dashboard/main/doc/img/live-dumps.png)

## Screenshots

### Service Overview
![Service Overview](https://raw.githubusercontent.com/TheGrimmChester/OPA-Dashboard/main/doc/img/service-overview.png)

### Execution Tree
![Execution Tree](https://raw.githubusercontent.com/TheGrimmChester/OPA-Dashboard/main/doc/img/execution-tree.png)

### Flame Graph
![Flame Graph](https://raw.githubusercontent.com/TheGrimmChester/OPA-Dashboard/main/doc/img/flame-graph.png)

## Quick Start

### Prerequisites

- Docker and Docker Compose
- PHP 8.0-8.5 (for your application)

### Installation

```bash
# Clone the repository
git clone https://github.com/TheGrimmChester/OpenProfilingAgent.git
cd OpenProfilingAgent

# Copy environment configuration
cp env.example .env

# Start all services
docker-compose up -d

# Check service status
docker-compose ps
```

### Services

After starting, the following services are available:

- **Dashboard**: http://localhost:3000
- **Prometheus Metrics**: http://localhost:9090
- **ClickHouse**: http://localhost:8123
- **Agent API**: http://localhost:8081
- **Agent Metrics**: http://localhost:2112/metrics

### Verify Installation

```bash
# Check agent health
curl http://localhost:8081/api/health

# Check agent statistics
curl http://localhost:8081/api/stats
```

## Architecture

```
PHP Application
    ↓
PHP Extension (C) → Unix Socket / TCP → Go Agent
    ↓                                    ↓
Helper Library                    ClickHouse
    ↓                                    ↓
    └───────────────────────────────────┘
                    ↓
            React Dashboard
```

### Components

1. **PHP Extension**: Low-level instrumentation using Zend Observer API
2. **Go Agent**: Processes, buffers, and stores trace data
3. **ClickHouse**: High-performance time-series database
4. **React Dashboard**: Web UI for visualization and analysis

## Documentation

- **[User Guide](docs/GUIDE.md)** - Complete guide to using OpenProfilingAgent
- **[Installation Guide](docs/INSTALLATION.md)** - Detailed installation instructions
- **[Technical Documentation](docs/TECHNICAL.md)** - Architecture, internals, and protocol
- **[Features](docs/FEATURES.md)** - Complete feature list

## Configuration

### PHP Extension

Configure via INI settings or environment variables:

```ini
; php.ini or opa.ini
extension=opa.so
opa.enabled=1
opa.socket_path=agent:9090
opa.sampling_rate=0.1
```

Or via environment variables:

```yaml
# docker-compose.yml
environment:
  - OPA_ENABLED=1
  - OPA_SAMPLING_RATE=0.1
  - OPA_SERVICE=my-service
```

### Agent

```bash
./opa-agent \
  -socket /var/run/opa.sock \
  -tcp :9090 \
  -clickhouse http://clickhouse:8123 \
  -batch 100 \
  -sampling_rate 0.1
```

See [Runtime Configuration Guide](docs/RUNTIME_CONFIGURATION.md) for complete details.

## Basic Usage

### Automatic Instrumentation

Once installed and configured, OpenProfilingAgent automatically instruments your PHP application. No code changes required.

### Manual Spans

```php
<?php
// Start a span
$spanId = opa_start_span('operation_name', ['tag' => 'value']);

// Do work
performOperation();

// Add tags
opa_add_tag($spanId, 'user_id', '123');

// End span
opa_end_span($spanId);
```

### PHP Helper Library

Install via Composer:

```bash
composer require thegrimmchester/openprofilingagent-helper
```

Usage:

```php
<?php
require 'vendor/autoload.php';
use OpenProfilingAgent\Client;

$client = new Client('agent:9090');

$span = $client->createSpan('GET /users', [
    'endpoint' => '/users',
    'method' => 'GET'
]);

// Do work
$users = fetchUsers();

$client->endSpan($span);
```

## API Endpoints

### Agent API (Port 8081)

- `GET /api/health` - Health check
- `GET /api/stats` - Agent statistics
- `GET /api/traces/{trace_id}` - Get complete trace
- `POST /api/control/keep` - Mark trace for full capture
- `POST /api/control/sampling` - Update sampling rate

### Metrics (Port 2112)

- `GET /metrics` - Prometheus metrics

## Deployment Considerations

### Recommended Settings

```ini
; High-traffic environments
opa.sampling_rate=0.1
opa.debug_log=0
opa.stack_depth=15
opa.buffer_size=131072
```

### Monitoring

- **Prometheus**: Monitor agent metrics at `http://localhost:2112/metrics`
- **Health Check**: `curl http://localhost:8081/api/health`
- **Statistics**: `curl http://localhost:8081/api/stats`

### Scaling

- **Multiple Agents**: Run multiple agent instances behind a load balancer
- **ClickHouse Cluster**: Use ClickHouse cluster for high availability
- **Horizontal Scaling**: Scale PHP workers independently

## Security

- **Data Sanitization**: Remove sensitive data from spans
- **Access Control**: Restrict agent API access
- **Network Security**: Use TLS for ClickHouse in secure environments

## Troubleshooting

See [Installation Guide - Troubleshooting](docs/INSTALLATION.md#troubleshooting) for common issues and solutions.

## Contributing

Contributions are welcome! Please see our contributing guidelines.

## License

European Union Public Licence v. 1.2 (EUPL-1.2)

This work is licensed under the European Union Public Licence v. 1.2. You may obtain a copy of the Licence at:

https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12

See [LICENSE](LICENSE) file for the full text of the licence.
