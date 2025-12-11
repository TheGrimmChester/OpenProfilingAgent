# OpenProfilingAgent Configuration Guide

Complete configuration reference for OpenProfilingAgent PHP extension and agent.

## PHP Extension Configuration

### Span Expansion Mode

The `opa.expand_spans` setting controls how call stack data is represented in traces.

#### Multiple Spans Mode (Default)

```ini
opa.expand_spans=1
```

**Behavior**:
- Call stack nodes with significant operations (SQL queries, HTTP requests, cache operations, Redis operations, or calls > 10ms) are converted to separate child spans
- Provides better trace visualization with hierarchical span structure
- Each significant operation appears as its own span in trace views
- Better for understanding request flow and identifying bottlenecks

**Example Trace Structure**:
```
Root Span: GET /users
  ├─ Child Span: UserRepository::findAll() (SQL query)
  ├─ Child Span: Cache::get() (cache operation)
  └─ Child Span: HttpClient::request() (HTTP request)
```

#### Full Span Mode

```ini
opa.expand_spans=0
```

**Behavior**:
- Single span contains the entire call stack nested inside
- Original behavior for backward compatibility
- Simpler trace structure
- All call stack data is available in the `stack` field of the root span

**Example Trace Structure**:
```
Root Span: GET /users
  └─ stack: [call_node_1, call_node_2, ...]
```

### Configuration Priority

1. **PHP INI Setting**: `opa.expand_spans` in php.ini (applies to all spans)
2. **Per-Span Override**: Can be overridden per-span via manual span creation with tags

### Backward Compatibility

- **Default Behavior**: If `expand_spans` tag is not present in a span, the agent defaults to multiple spans mode (new behavior)
- **Old Spans**: Spans created before this feature was added will automatically use multiple spans mode
- **Explicit Control**: Set `opa.expand_spans=0` to use the original single-span behavior

## Other Configuration Options

See [INSTALLATION.md](INSTALLATION.md) for complete configuration reference including:
- `opa.enabled` - Enable/disable extension
- `opa.socket_path` - Agent connection path
- `opa.sampling_rate` - Request sampling rate
- `opa.full_capture_threshold_ms` - Threshold for full capture
- `opa.stack_depth` - Maximum call stack depth
- And more...

## Agent Configuration

The agent automatically detects the `expand_spans` flag from span tags and processes traces accordingly. No agent-side configuration is needed.

## Examples

### Enable Multiple Spans Mode (Default)

```ini
; php.ini or opa.ini
opa.expand_spans=1
```

### Use Full Span Mode

```ini
; php.ini or opa.ini
opa.expand_spans=0
```

### Runtime Configuration

```php
// Set via ini_set (applies to current request)
ini_set('opa.expand_spans', '1'); // Multiple spans
ini_set('opa.expand_spans', '0'); // Full span
```

### Manual Span Override

```php
// Create span with explicit expand_spans tag
$span = $client->createSpan('custom-operation', [
    'expand_spans' => false  // Override INI setting for this span
]);
```

## Performance Considerations

- **Memory**: Both modes use the same memory in PHP extension (one span sent)
- **Network**: Both modes send the same data (one compressed message)
- **Agent Processing**: Expansion happens on agent side during trace retrieval
- **Storage**: No difference in ClickHouse storage (one span per request stored)
- **User Impact**: Zero - span sending happens after user response via `fastcgi_finish_request()`

## Troubleshooting

### Traces Show Only One Span

- Check `opa.expand_spans` setting: `php --ri opa | grep expand_spans`
- Verify span tags include `expand_spans` field
- Check agent logs for expansion errors

### Want to Switch Modes

1. Update `opa.expand_spans` in php.ini
2. Restart PHP-FPM: `sudo systemctl restart php8.4-fpm`
3. New requests will use the new mode
4. Existing traces in ClickHouse are not affected (expansion happens at retrieval time)
