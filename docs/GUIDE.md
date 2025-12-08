# OpenProfilingAgent User Guide

Complete guide to using OpenProfilingAgent in your applications.

> **⚠️ Early Stage Development**: This project is currently in early development. Some features may be buggy, incomplete, or not yet implemented. Use at your own risk and expect breaking changes.

## Table of Contents

- [Getting Started](#getting-started)
- [Configuration](#configuration)
- [PHP Extension Functions](#php-extension-functions)
- [PHP Helper Library](#php-helper-library)
- [Dashboard Usage](#dashboard-usage)
- [API Usage](#api-usage)
- [Multi-Language Integration](#multi-language-integration)
- [Best Practices](#best-practices)
- [Examples](#examples)

## Getting Started

### Basic Setup

1. Install the PHP extension (see [Installation Guide](INSTALLATION.md))
2. Configure INI settings or use environment variables
3. Start the agent
4. Your application will automatically start sending traces

### Verify Installation

```php
<?php
// Check if extension is loaded
if (extension_loaded('opa')) {
    echo "OpenProfilingAgent is loaded!\n";
} else {
    echo "OpenProfilingAgent is not loaded\n";
}

// Check configuration
echo "Enabled: " . ini_get('opa.enabled') . "\n";
echo "Socket: " . ini_get('opa.socket_path') . "\n";
```

## Configuration

### Runtime Configuration

OpenProfilingAgent supports on-the-fly configuration through environment variables and PHP functions. This allows you to enable, disable, and configure profiling without rebuilding containers.

**For complete documentation, see [Runtime Configuration Guide](RUNTIME_CONFIGURATION.md)**

#### Environment Variables (Docker Compose)

```yaml
environment:
  - OPA_ENABLED=1
  - OPA_SAMPLING_RATE=0.1
  - OPA_SERVICE=my-service
  - OPA_SOCKET_PATH=agent:9090
```

#### PHP Functions

```php
<?php
// Enable profiling at runtime
opa_enable();

// Check if profiling is active
if (opa_is_enabled()) {
    // Profiling is active
}

// Disable profiling
opa_disable();
```

### PHP Extension INI Settings

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
```

**Note**: `opa.socket_path` supports both Unix sockets (e.g., `/var/run/opa.sock`) and TCP/IP (e.g., `agent:9090` or `127.0.0.1:9090`). Detection is automatic: paths starting with `/` are Unix sockets, otherwise TCP/IP.

### Agent Configuration

```bash
./opa-agent \
  -socket /var/run/opa.sock \      # Unix socket (optional)
  -tcp :9090 \                      # TCP address (optional)
  -clickhouse http://clickhouse:8123 \
  -batch 100 \
  -batch_interval_ms 1000 \
  -sampling_rate 1.0
```

**Note**: The agent can listen on both Unix socket and TCP/IP simultaneously. At least one transport must be configured.

## PHP Extension Functions

### Manual Span Management

#### opa_start_span()

Create a new span manually.

```php
$spanId = opa_start_span('operation_name', ['tag' => 'value']);
// ... do work ...
opa_end_span($spanId);
```

**Parameters:**
- `name` (string): Operation name
- `tags` (array, optional): Key-value tags

**Returns:** Span ID (string)

#### opa_end_span()

End a span and send it to the agent.

```php
opa_end_span($spanId);
```

**Parameters:**
- `span_id` (string): Span ID from `opa_start_span()`

**Returns:** `true` on success, `false` on failure

#### opa_add_tag()

Add a tag to an existing span.

```php
opa_add_tag($spanId, 'user_id', '123');
opa_add_tag($spanId, 'environment', 'deployment');
```

**Parameters:**
- `span_id` (string): Span ID
- `key` (string): Tag key
- `value` (string): Tag value

**Returns:** `true` on success, `false` on failure

#### opa_set_parent()

Set parent span for distributed tracing.

```php
$parentId = opa_start_span('parent_operation');
$childId = opa_start_span('child_operation');
opa_set_parent($childId, $parentId);
```

**Parameters:**
- `span_id` (string): Child span ID
- `parent_id` (string): Parent span ID

**Returns:** `true` on success, `false` on failure

#### opa_dump()

Dump variables to the current span (like var_dump but sent to APM).

```php
$data = ['key' => 'value', 'count' => 42];
opa_dump($data);

$user = getUserById(123);
opa_dump($user, 'User object');
```

**Parameters:** Variable number of arguments (any type)

**Returns:** `null` (silent)

#### opa_enable() / opa_disable()

Enable or disable profiling at runtime.

```php
opa_enable();  // Enable profiling
opa_disable(); // Disable profiling
```

#### opa_is_enabled()

Check if profiling is currently enabled.

```php
if (opa_is_enabled()) {
    // Profiling is active
}
```

## PHP Helper Library

### Installation

Install via Composer:

```bash
composer require thegrimmchester/openprofilingagent-helper
```

Or clone and install manually:

```bash
cd php-helper
composer install
```

### Basic Usage

```php
<?php
require 'vendor/autoload.php';
use OpenProfilingAgent\Client;

// Unix socket
$client = new Client('/var/run/opa.sock');

// Or TCP/IP
$client = new Client('agent:9090');  // host:port format

// Create span
$span = $client->createSpan('GET /users', [
    'endpoint' => '/users',
    'method' => 'GET'
]);

// Add tags
$client->addTag($span['span_id'], 'user_id', '123');
$client->addTag($span['span_id'], 'ip_address', $_SERVER['REMOTE_ADDR']);

// Add annotation
$client->addAnnotation($span['span_id'], 'Database query started');

// Do work
$users = fetchUsers();

// End span
$client->endSpan($span);
```

### Advanced Usage

```php
// Create nested spans
$parent = $client->createSpan('API Request');
$child = $client->createSpan('Database Query');
$client->setParent($child['span_id'], $parent['span_id']);

// Search history
$history = $client->getHistory();
$filtered = $client->searchHistory(['service' => 'api']);

// Purge history
$client->purgeHistory();
```

## Dashboard Usage

### Accessing the Dashboard

1. Open http://localhost:3000 in your browser
2. Search for traces by trace ID
3. View waterfall visualization
4. Analyze performance metrics

### Features

- **Trace Search**: Search by trace ID, service, or time range
- **Waterfall View**: Visualize span hierarchy and timing
- **Performance Metrics**: View duration, CPU, memory metrics
- **SQL Queries**: See all SQL queries executed
- **Network Calls**: View HTTP requests and responses
- **Call Stack**: Inspect function call hierarchy

## API Usage

### Health Check

```bash
curl http://localhost:8081/api/health
```

Response:
```json
{
  "status": "ok",
  "version": "1.0.0"
}
```

### Get Statistics

```bash
curl http://localhost:8081/api/stats
```

Response:
```json
{
  "incoming_total": 12345,
  "dropped_total": 0,
  "queue_size": 10,
  "circuit_breaker_open": false
}
```

### Get Trace

```bash
curl http://localhost:8081/api/traces/{trace_id}
```

### Mark Trace for Full Capture

```bash
curl -X POST http://localhost:8081/api/control/keep \
  -H "Content-Type: application/json" \
  -d '{"trace_id": "abc123"}'
```

### Update Sampling Rate

```bash
curl -X POST http://localhost:8081/api/control/sampling \
  -H "Content-Type: application/json" \
  -d '{"rate": 0.1}'
```

## Multi-Language Integration

OpenProfilingAgent supports multiple programming languages through SDKs. The protocol is simple JSON over Unix sockets, TCP/IP, or HTTP.

### Protocol Overview

Each span is sent as a single line of ND-JSON:

```json
{"type":"span","trace_id":"abc123","span_id":"def456","name":"operation",...}
```

### Connection Methods

1. **Unix Socket** (Recommended for local): `/var/run/opa.sock`
2. **TCP/IP** (For remote): `host:port` (e.g., `agent:9090`)
3. **HTTP**: `POST http://agent:8081/api/spans`

### Go SDK

```go
package main

import (
    "github.com/TheGrimmChester/OpenProfilingAgent/agent"
    "time"
)

func main() {
    client := opa.NewClient("agent:9090")
    defer client.Close()
    
    span := client.StartSpan("operation", map[string]string{
        "service": "my-service",
    })
    defer span.End()
    
    time.Sleep(100 * time.Millisecond)
    span.AddTag("result", "success")
}
```

See [Go SDK Example](sdks/go-sdk/example.go) for complete example.

### Python SDK

```python
from opa import Client

client = Client('agent:9090')

with client.span('operation', tags={'service': 'my-service'}) as span:
    time.sleep(0.1)
    span.add_tag('result', 'success')
```

See [Python SDK](sdks/python-sdk/) for complete implementation.

### Node.js SDK

```javascript
const { Client } = require('opa-nodejs');

const client = new Client('agent:9090');

const span = client.startSpan('operation', {
    service: 'my-service'
});

await doWork();
span.addTag('result', 'success');
span.end();
```

See [Node.js SDK](sdks/nodejs-sdk/) for complete implementation.

For complete SDK documentation, see [SDKs README](sdks/README.md).

## Best Practices

### Recommended Settings

```ini
; High-traffic environments
opa.sampling_rate=0.1
opa.debug_log=0
opa.stack_depth=15
opa.buffer_size=131072
```

### Performance Optimization

1. **Sampling**: Use 0.1 (10%) for high-traffic environments
2. **Batch Size**: Increase for high-traffic (200-500)
3. **Stack Depth**: Reduce to 10-15 for performance
4. **Buffer Size**: Increase for large traces (131072+)

### Security

1. **Sanitize Data**: Remove sensitive data from spans
2. **Access Control**: Restrict agent API access
3. **Network Security**: Use TLS for ClickHouse in secure environments
4. **Authentication**: Enable auth for multi-tenant setups

### Monitoring

1. **Prometheus**: Monitor agent metrics
2. **Alerts**: Set up alerts for dropped messages
3. **Dashboards**: Create Grafana dashboards
4. **Logs**: Monitor agent and PHP logs

## Examples

### Symfony Integration

```php
// config/services.yaml
services:
    OpenProfilingAgent\Client:
        arguments:
            - '%env(OPA_SOCKET_PATH)%'
```

### Laravel Integration

```php
// app/Providers/AppServiceProvider.php
use OpenProfilingAgent\Client;

public function boot()
{
    $this->app->singleton(Client::class, function ($app) {
        return new Client(config('opa.socket_path'));
    });
}
```

### Custom Instrumentation

```php
// Instrument a specific function
function expensiveOperation() {
    $spanId = opa_start_span('expensive_operation');
    try {
        // ... do work ...
        opa_add_tag($spanId, 'result', 'success');
    } catch (Exception $e) {
        opa_add_tag($spanId, 'error', $e->getMessage());
        throw $e;
    } finally {
        opa_end_span($spanId);
    }
}
```

### Distributed Tracing

```php
// Extract trace context from HTTP headers
$traceId = $_SERVER['HTTP_X_TRACE_ID'] ?? null;
$spanId = $_SERVER['HTTP_X_SPAN_ID'] ?? null;

if ($traceId && $spanId) {
    // Set parent span for distributed tracing
    $currentSpanId = opa_start_span('service_operation');
    opa_set_parent($currentSpanId, $spanId);
}
```

## Next Steps

- See [Installation Guide](INSTALLATION.md) for setup instructions
- See [Technical Documentation](TECHNICAL.md) for architecture details
- See [Features](FEATURES.md) for complete feature list

