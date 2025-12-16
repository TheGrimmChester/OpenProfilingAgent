# W3C Trace Context Implementation

## Overview

The OpenProfilingAgent (OPA) supports W3C Trace Context propagation, enabling distributed tracing across services. This implementation follows the [W3C Trace Context specification](https://www.w3.org/TR/trace-context/).

## Features

- **Automatic Header Parsing**: Automatically extracts `traceparent` and `tracestate` headers from HTTP requests
- **Header Propagation**: Automatically injects W3C headers into outgoing HTTP requests (cURL)
- **Userland API**: PHP function `opa_set_w3c_context()` for manual context setting
- **ClickHouse Storage**: W3C fields are stored in `w3c_traceparent` and `w3c_tracestate` columns
- **Symfony Integration**: Event listener for seamless integration with Symfony applications

## Architecture

### Components

1. **PHP Extension** (`php-extension/`): Parses and stores W3C headers, injects headers in outgoing requests
2. **Agent** (`agent/`): Receives W3C data and stores it in ClickHouse
3. **Symfony App** (`symfony-app/`): Optional event listener for header bridging

### Data Flow

```
HTTP Request with W3C Headers
    ↓
Nginx (forwards headers to PHP-FPM)
    ↓
PHP Extension (RINIT: captures headers)
    ↓
Symfony Event Listener (optional: bridges headers)
    ↓
PHP Extension (RSHUTDOWN: includes in span JSON)
    ↓
Agent (receives and stores in ClickHouse)
    ↓
ClickHouse (w3c_traceparent, w3c_tracestate columns)
```

## PHP Extension

### Automatic Header Capture

The extension automatically captures W3C headers during request initialization (RINIT):

- Searches `$_SERVER` for `HTTP_TRACEPARENT` and `HTTP_TRACESTATE`
- Falls back to `sapi_getenv()` if headers not in `$_SERVER`
- Stores headers in thread-safe global variables
- Includes headers in span JSON during request shutdown (RSHUTDOWN)

### Userland API

#### `opa_set_w3c_context(string $traceparent, ?string $tracestate = null): bool`

Manually set W3C Trace Context from PHP code.

**Parameters:**
- `$traceparent` (required): W3C traceparent header string (format: `00-<32-hex>-<16-hex>-<2-hex>`)
- `$tracestate` (optional): W3C tracestate header string

**Returns:**
- `true` on success
- `false` on failure (invalid traceparent format)

**Example:**
```php
$traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
$tracestate = 'opa=1,test=value';

if (function_exists('opa_set_w3c_context')) {
    $result = opa_set_w3c_context($traceparent, $tracestate);
    if ($result) {
        echo "W3C context set successfully\n";
    }
}
```

### Automatic Header Injection

The extension automatically injects W3C headers into outgoing cURL requests:

- When `curl_exec()` is called, the extension checks for W3C context
- If context exists, it injects `traceparent` and `tracestate` headers
- Headers are added before the request is sent

## Agent

### Data Storage

The agent receives W3C fields in span JSON and stores them in ClickHouse:

- **Table**: `opa.spans_full`
- **Columns**: 
  - `w3c_traceparent` (Nullable(String))
  - `w3c_tracestate` (Nullable(String))

### Querying W3C Data

```sql
-- Find spans with W3C Trace Context
SELECT 
    trace_id,
    span_id,
    name,
    w3c_traceparent,
    w3c_tracestate
FROM opa.spans_full
WHERE w3c_traceparent IS NOT NULL
ORDER BY start_ts DESC
LIMIT 10;
```

## Symfony Integration

### Event Listener

The `W3CTraceContextListener` bridges HTTP headers to the PHP extension:

**Location**: `symfony-app/src/EventListener/W3CTraceContextListener.php`

**How it works:**
1. Listens to `KernelEvents::REQUEST` event
2. Reads `traceparent` and `tracestate` from Symfony's `Request` object
3. Calls `opa_set_w3c_context()` to set the context in the extension

**Registration:**
The listener is automatically registered via Symfony's service autoconfiguration. Ensure it's in the `App\EventListener` namespace.

### Manual Usage in Controllers

You can also manually set W3C context in controllers:

```php
use Symfony\Component\HttpFoundation\Request;

public function myAction(Request $request): Response
{
    $traceparent = $request->headers->get('traceparent');
    $tracestate = $request->headers->get('tracestate');
    
    if ($traceparent && function_exists('opa_set_w3c_context')) {
        opa_set_w3c_context($traceparent, $tracestate);
    }
    
    // ... rest of controller logic
}
```

## Nginx Configuration

For proper header forwarding, ensure Nginx forwards W3C headers to PHP-FPM:

```nginx
location ~ \.php$ {
    fastcgi_pass symfony-php:9000;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    
    # Forward W3C Trace Context headers to PHP-FPM
    fastcgi_param HTTP_TRACEPARENT $http_traceparent;
    fastcgi_param HTTP_TRACESTATE $http_tracestate;
}
```

**Location**: `symfony-app/docker/nginx.conf`

## Testing

### Test Script

A test script is provided to validate W3C data flow:

**Location**: `symfony-app/test_w3c_clickhouse.php`

**Usage:**
```bash
docker-compose exec symfony-php php /var/www/symfony/test_w3c_clickhouse.php
```

The script:
1. Tests `opa_set_w3c_context()` function
2. Makes an HTTP request to generate a span
3. Queries ClickHouse to verify W3C data is stored

### Manual Testing

1. **Send a request with W3C headers:**
```bash
curl -H "traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01" \
     -H "tracestate: opa=1" \
     http://localhost:8080/api/test/pdo/simple
```

2. **Query ClickHouse:**
```sql
SELECT trace_id, span_id, name, w3c_traceparent, w3c_tracestate
FROM opa.spans_full
WHERE start_ts > now() - INTERVAL 1 MINUTE
  AND parent_id IS NULL
ORDER BY start_ts DESC
LIMIT 1;
```

## W3C Trace Context Format

### traceparent Header

Format: `00-<32-hex-trace-id>-<16-hex-parent-id>-<2-hex-flags>`

Example: `00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01`

- **Version**: `00` (current version)
- **Trace ID**: 32 hexadecimal characters (128 bits)
- **Parent ID**: 16 hexadecimal characters (64 bits)
- **Flags**: 2 hexadecimal characters (8 bits, bit 0 = sampled)

### tracestate Header

Format: Comma-separated list of key-value pairs

Example: `opa=1,test=value`

- Vendor-specific key-value pairs
- Used for vendor-specific trace data

## Implementation Details

### Thread Safety

W3C context variables are protected by mutexes:
- `w3c_context_mutex`: Protects W3C context globals
- `w3c_rinit_mutex`: Protects RINIT-captured headers

### Memory Management

- W3C context strings are allocated with `malloc()` (not `emalloc()`)
- Safe to use after `fastcgi_finish_request()`
- Freed in RSHUTDOWN and RINIT (before new request)

### Header Parsing

The extension validates W3C headers:
- Checks format and length
- Validates hexadecimal characters
- Extracts trace ID, parent ID, and flags
- Stores raw header for propagation

## Troubleshooting

### Headers Not Appearing in ClickHouse

1. **Check if headers are forwarded by Nginx:**
   - Verify `nginx.conf` includes `fastcgi_param` directives
   - Check Nginx access logs for incoming headers

2. **Check if extension is capturing headers:**
   - Use `opa_set_w3c_context()` manually in controller
   - Verify function exists: `function_exists('opa_set_w3c_context')`

3. **Check if agent is receiving data:**
   - Check agent logs for span processing
   - Verify ClickHouse schema includes `w3c_traceparent` and `w3c_tracestate` columns

### Listener Not Being Called

1. **Verify listener is registered:**
```bash
docker-compose exec symfony-php php bin/console debug:event-dispatcher kernel.request
```

2. **Check Symfony cache:**
```bash
docker-compose exec symfony-php php bin/console cache:clear
```

3. **Use manual controller approach:**
   - Call `opa_set_w3c_context()` directly in controller
   - See "Manual Usage in Controllers" section above

## References

- [W3C Trace Context Specification](https://www.w3.org/TR/trace-context/)
- [W3C Trace Context GitHub](https://github.com/w3c/trace-context)

