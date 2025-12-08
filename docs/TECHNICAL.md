# OpenProfilingAgent Technical Documentation

Deep dive into OpenProfilingAgent architecture, internals, and implementation details.

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Data Flow](#data-flow)
- [Protocol Specification](#protocol-specification)
- [ClickHouse Schema](#clickhouse-schema)
- [Extension Internals](#extension-internals)
- [Agent Internals](#agent-internals)
- [Performance Characteristics](#performance-characteristics)
- [Security Considerations](#security-considerations)

## Architecture Overview

```
┌─────────────────┐
│  PHP Application│
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌──────────────────────┐
│ PHP Extension   │────▶│  Unix Socket / TCP   │
│   (C Code)      │     │      (ND-JSON)       │
└─────────────────┘     └──────┬──────────────┘
                                │
                                ▼
                         ┌──────────────┐
                         │  Go Agent    │
                         │  (Processor) │
                         └──────┬───────┘
                                │
                ┌───────────────┼───────────────┐
                ▼               ▼               ▼
         ┌─────────────┐ ┌──────────┐  ┌─────────────┐
         │ ClickHouse  │ │Prometheus│  │   React     │
         │  (Storage)  │ │(Metrics) │  │  Dashboard │
         └─────────────┘ └──────────┘  └─────────────┘
```

### Components

1. **PHP Extension**: Low-level instrumentation using Zend Observer API
2. **Transport Layer**: Unix Socket (local) or TCP/IP (remote) communication (ND-JSON format)
3. **Go Agent**: Processes, buffers, and stores trace data (listens on both transports simultaneously)
4. **ClickHouse**: Time-series database for storage
5. **React Dashboard**: Web UI for visualization

## Data Flow

### 1. Request Processing

```
HTTP Request
    │
    ▼
PHP-FPM Worker
    │
    ▼
Zend Observer Hook (opa_execute_ex)
    │
    ├─▶ Create root span
    ├─▶ Track function calls
    ├─▶ Capture SQL queries
    ├─▶ Capture HTTP requests
    └─▶ Collect metrics
    │
    ▼
RSHUTDOWN
    │
    ├─▶ Serialize call stack
    ├─▶ Compress with LZ4
    └─▶ Send via Unix socket or TCP/IP
```

### 2. Agent Processing

```
Unix Socket / TCP/IP (ND-JSON)
    │
    ▼
Decompress (LZ4)
    │
    ▼
Parse JSON
    │
    ├─▶ Add to TailBuffer (trace reconstruction)
    ├─▶ Process span data
    ├─▶ Update Prometheus metrics
    └─▶ Batch write to ClickHouse
    │
    ▼
ClickHouse Storage
    ├─▶ spans_min (aggregated)
    └─▶ spans_full (detailed)
```

## Protocol Specification

### Span Format

```json
{
  "type": "span",
  "trace_id": "abc123...",
  "span_id": "def456...",
  "parent_id": "ghi789...",
  "service": "my-service",
  "name": "GET /users",
  "start_ts": 1234567890123,
  "end_ts": 1234567890124,
  "duration_ms": 1.0,
  "cpu_ms": 0.5,
  "status": "ok",
  "language": "php",
  "language_version": "8.4",
  "framework": "symfony",
  "framework_version": "7.0",
  "tags": {
    "user_id": "123",
    "environment": "deployment"
  },
  "net": {
    "bytes_sent": 1024,
    "bytes_received": 2048
  },
  "sql": [
    {
      "query": "SELECT * FROM users",
      "db_system": "mysql",
      "duration_ms": 10.5
    }
  ],
  "stack": [
    {
      "call_id": "call1",
      "function": "getUser",
      "class": "UserRepository",
      "file": "/app/src/Repository/UserRepository.php",
      "line": 42,
      "duration_ms": 1.0,
      "cpu_ms": 0.5,
      "parent_id": "call0"
    }
  ]
}
```

### Transport Layer

OpenProfilingAgent supports two transport methods for sending span data:

#### Unix Socket (Recommended for Local Communication)

- **Path Format**: `/path/to/socket` (e.g., `/var/run/opa.sock`)
- **Protocol**: `AF_UNIX` with `SOCK_STREAM`
- **Advantages**: 
  - Lowest latency (<1ms)
  - No network overhead
  - Simple file-based permissions
- **Use Case**: Same machine or shared volume (Docker)

#### TCP/IP (For Remote Communication)

- **Address Format**: `host:port` (e.g., `agent:9090`, `127.0.0.1:9090`, `:9090`)
- **Protocol**: `AF_INET` with `SOCK_STREAM` (IPv4)
- **Advantages**:
  - Works across network boundaries
  - Standard network security (firewalls, VPNs)
  - Can use service discovery (DNS)
- **Use Case**: Remote agents, Kubernetes, distributed systems

#### Auto-Detection

Transport type is automatically detected:
- Paths starting with `/` → Unix socket
- Otherwise → TCP/IP (must contain `:` for host:port)

#### Agent Configuration

The agent can listen on both transports simultaneously:
- Unix socket: `-socket /var/run/opa.sock` or `SOCKET_PATH` env var
- TCP/IP: `-tcp :9090` or `TRANSPORT_TCP` env var

Both listeners share the same processing pipeline and channel.

### Compression

Data is optionally compressed with LZ4:

```
[4 bytes: "LZ4"]
[8 bytes: original size (little-endian)]
[compressed data]
```

## ClickHouse Schema

### spans_min

Lightweight aggregated metrics for fast queries.

```sql
CREATE TABLE spans_min (
    trace_id String,
    span_id String,
    service String,
    name String,
    start_ts DateTime64(3),
    duration_ms Float64,
    cpu_ms Float64,
    status String,
    ...
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(start_ts)
ORDER BY (service, start_ts);
```

### spans_full

Detailed spans with JSON fields for complete data.

```sql
CREATE TABLE spans_full (
    trace_id String,
    span_id String,
    ...
    net String,      -- JSON
    sql String,      -- JSON
    stack String,    -- JSON
    tags String,     -- JSON
    dumps String     -- JSON
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(start_ts)
ORDER BY (service, start_ts);
```

## Extension Internals

### Hook Mechanism

Uses `zend_execute_ex` hook:

```c
original_zend_execute_ex = zend_execute_ex;
zend_execute_ex = opa_execute_ex;
```

### Call Stack Tracking

- Uses array-based stack (max 1024 depth)
- Tracks parent-child relationships
- Validates with magic numbers
- Thread-safe with mutexes

### Memory Management

- Uses `emalloc()`/`efree()` for PHP memory
- Uses `malloc()`/`free()` for persistent data
- Magic number validation prevents double-free
- Cleans up in RSHUTDOWN

### Automatic Instrumentation

- **PDO**: Hooks into `pdo_execute` and `pdo_query`
- **cURL**: Hooks into `curl_exec`
- **File I/O**: Hooks into `fopen`, `fread`, `fwrite`, `fclose`

## Agent Internals

### Worker Pool

8 concurrent workers process incoming messages:

```go
for i := 0; i < 8; i++ {
    go worker(inCh, tb, writer, wsHub)
}
```

### Circuit Breaker

Protects against ClickHouse overload:

- Opens after threshold failures
- Auto-recovers after timeout
- Drops messages when open

### Tail Buffer

In-memory buffer for trace reconstruction:

- TTL-based expiration
- Capacity limits
- Mark traces for full capture

### Batch Processing

- Buffers spans in memory
- Writes to ClickHouse in batches
- Configurable batch size and interval

## Performance Characteristics

### Overhead

- **CPU**: ~2-5% with 10% sampling
- **Memory**: ~50MB per PHP-FPM worker
- **Network**: ~1KB per span (compressed)

### Throughput

- **Spans/sec**: 10,000+ (single agent)
- **Latency**: <1ms (socket communication)
- **Storage**: 1MB per 1000 spans (compressed)

### Scalability

- **Horizontal**: Multiple agents with load balancing
- **Vertical**: Increase batch size and workers
- **Storage**: ClickHouse handles billions of rows

## Security Considerations

### Data Privacy

1. **Sanitization**: Remove sensitive data before sending
2. **Redaction**: Redact passwords, tokens, PII
3. **Sampling**: Reduce data collection in high-traffic environments

### Access Control

1. **Socket Permissions**: Restrict socket file access
2. **API Authentication**: Enable auth for agent API
3. **Network Security**: Use TLS for ClickHouse

### Best Practices

1. **Encryption**: Encrypt data in transit (TLS)
2. **Authentication**: Use strong API keys
3. **Audit Logging**: Log all API access
4. **Rate Limiting**: Prevent abuse

## Next Steps

- See [User Guide](GUIDE.md) for usage examples
- See [Features](FEATURES.md) for complete feature list
- See [Installation Guide](INSTALLATION.md) for setup instructions
