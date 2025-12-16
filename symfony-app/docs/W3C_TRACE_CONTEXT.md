# W3C Trace Context - Symfony Application

## Overview

The Symfony application provides integration with the OPA PHP extension for W3C Trace Context support.

## Components

### Event Listener

**File**: `src/EventListener/W3CTraceContextListener.php`

Automatically bridges HTTP headers to the PHP extension.

**How it works:**
1. Listens to `KernelEvents::REQUEST` event (priority 1000)
2. Reads `traceparent` and `tracestate` headers from Symfony's `Request` object
3. Calls `opa_set_w3c_context()` to set the context in the extension

**Registration:**
Automatically registered via Symfony's service autoconfiguration in `config/services.yaml`.

### Manual Usage

You can also manually set W3C context in controllers:

```php
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/my-endpoint')]
public function myAction(Request $request): JsonResponse
{
    $traceparent = $request->headers->get('traceparent');
    $tracestate = $request->headers->get('tracestate');
    
    if ($traceparent && function_exists('opa_set_w3c_context')) {
        opa_set_w3c_context($traceparent, $tracestate);
    }
    
    // ... rest of controller logic
}
```

## Configuration

### Nginx

Ensure Nginx forwards W3C headers to PHP-FPM:

**File**: `docker/nginx.conf`

```nginx
location ~ \.php$ {
    fastcgi_pass symfony-php:9000;
    # ... other settings ...
    
    # Forward W3C Trace Context headers
    fastcgi_param HTTP_TRACEPARENT $http_traceparent;
    fastcgi_param HTTP_TRACESTATE $http_tracestate;
}
```

## Testing

### Test Script

**File**: `test_w3c_clickhouse.php`

Comprehensive test script that:
1. Tests `opa_set_w3c_context()` function
2. Makes an HTTP request to generate a span
3. Queries ClickHouse to verify W3C data is stored

**Usage:**
```bash
docker-compose exec symfony-php php /var/www/symfony/test_w3c_clickhouse.php
```

### Manual Testing

1. **Send request with W3C headers:**
```bash
curl -H "traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01" \
     -H "tracestate: opa=1" \
     http://localhost:8080/api/test/pdo/simple
```

2. **Verify in ClickHouse:**
```sql
SELECT trace_id, span_id, name, w3c_traceparent, w3c_tracestate
FROM opa.spans_full
WHERE start_ts > now() - INTERVAL 1 MINUTE
  AND parent_id IS NULL
ORDER BY start_ts DESC
LIMIT 1;
```

## Troubleshooting

### Listener Not Being Called

1. **Check registration:**
```bash
docker-compose exec symfony-php php bin/console debug:event-dispatcher kernel.request
```

2. **Clear cache:**
```bash
docker-compose exec symfony-php php bin/console cache:clear
```

3. **Use manual approach:**
   - Call `opa_set_w3c_context()` directly in controller
   - See "Manual Usage" section above

### Headers Not Available

1. **Check Nginx configuration:**
   - Verify `fastcgi_param` directives are present
   - Check Nginx logs for incoming headers

2. **Check Symfony Request:**
```php
$request->headers->get('traceparent'); // Should return header value
```

## References

- Main documentation: `docs/W3C_TRACE_CONTEXT.md`
- [W3C Trace Context Specification](https://www.w3.org/TR/trace-context/)

