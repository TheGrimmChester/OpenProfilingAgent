# OpenProfilingAgent Features Documentation

Complete list of OpenProfilingAgent features and capabilities.

## Core Features

### Automatic Instrumentation

- ✅ **Function Call Tracking**: Automatic tracking of all function calls
- ✅ **PDO SQL Queries**: Automatic capture of SQL queries
- ✅ **cURL HTTP Requests**: Automatic tracking of HTTP requests
- ✅ **File I/O Operations**: Automatic tracking of file operations
- ✅ **Cache Operations**: APCu, Symfony Cache, Redis tracking
- ✅ **Network Metrics**: Bytes sent/received, latency

### Manual Instrumentation

- ✅ **Custom Spans**: Create spans programmatically
- ✅ **Tags and Annotations**: Add metadata to spans
- ✅ **Parent-Child Relationships**: Build distributed traces
- ✅ **Variable Dumping**: Dump variables to spans

### Performance Metrics

- ✅ **Duration Tracking**: Precise timing measurements
- ✅ **CPU Time**: CPU usage per span
- ✅ **Memory Usage**: Memory delta tracking
- ✅ **Network Bytes**: Sent and received bytes
- ✅ **Call Stack**: Complete function call hierarchy

### Storage and Querying

- ✅ **ClickHouse Storage**: High-performance time-series storage
- ✅ **Trace Reconstruction**: Complete trace assembly
- ✅ **Aggregated Metrics**: Fast queries on spans_min
- ✅ **Detailed Data**: Full data in spans_full
- ✅ **Retention Policies**: Configurable TTL

### Visualization

- ✅ **Waterfall View**: Visual span hierarchy
- ✅ **Trace Search**: Search by ID, service, time
- ✅ **Performance Charts**: Duration, CPU, memory graphs
- ✅ **SQL Query Analysis**: Query performance breakdown
- ✅ **Network Metrics**: HTTP request/response analysis

### Agent Features

- ✅ **Circuit Breaker**: Overload protection
- ✅ **Batch Processing**: Efficient ClickHouse writes
- ✅ **Prometheus Metrics**: Comprehensive observability
- ✅ **Admin API**: Control and monitoring
- ✅ **WebSocket Support**: Real-time updates

### Multi-Language Support

- ✅ **PHP**: Full support (extension + helper)

## Configuration Options

### PHP Extension

| Feature | INI Setting | Default |
|---------|------------|---------|
| Enable/Disable | `opa.enabled` | `1` |
| Sampling Rate | `opa.sampling_rate` | `1.0` |
| Socket Path | `opa.socket_path` | `/var/run/opa.sock` |
| Full Capture Threshold | `opa.full_capture_threshold_ms` | `100` |
| Stack Depth | `opa.stack_depth` | `20` |
| Buffer Size | `opa.buffer_size` | `65536` |
| Internal Functions | `opa.collect_internal_functions` | `1` |
| Debug Logging | `opa.debug_log` | `0` |

### Agent

| Feature | Flag | Default |
|---------|------|---------|
| Socket Path | `-socket` | `/var/run/opa.sock` |
| ClickHouse URL | `-clickhouse` | `http://clickhouse:8123` |
| Batch Size | `-batch` | `100` |
| Batch Interval | `-batch_interval_ms` | `1000` |
| Sampling Rate | `-sampling_rate` | `1.0` |
| Metrics Port | `-metrics` | `:2112` |
| API Port | `-api` | `:8080` |

## Use Cases

### Application Performance Monitoring

- Monitor request latency
- Identify slow queries
- Track API response times
- Analyze error rates

### Debugging

- Variable dumping
- Call stack inspection
- SQL query analysis
- Network request debugging

### Performance Optimization

- Identify bottlenecks
- Optimize database queries
- Reduce network calls
- Memory leak detection

### Distributed Tracing

- Cross-service tracing
- Microservices monitoring
- API gateway integration
- Service mesh support

## Next Steps

- See [User Guide](GUIDE.md) for usage examples
- See [INTEGRATION.md](INTEGRATION.md) for SDK integration

