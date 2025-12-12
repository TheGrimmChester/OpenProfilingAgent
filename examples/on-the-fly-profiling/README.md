# On-the-Fly Profiling Example

This example demonstrates how to use OpenProfilingAgent with **on-the-fly profiling**, where profiling is disabled by default and enabled dynamically when needed.

## What is On-the-Fly Profiling?

On-the-fly profiling allows you to:
- Keep profiling **disabled by default** to minimize overhead
- **Enable profiling conditionally** via PHP functions (`opa_enable()`) or CLI environment variables (`OPA_ENABLE=1`)
- Profile specific requests, routes, or commands without modifying code or configuration files

## Prerequisites

1. **Main OpenProfilingAgent services must be running**:
   ```bash
   cd ../..
   docker network create opa_network
   docker-compose up -d
   ```

2. Verify services are healthy:
   ```bash
   curl http://localhost:8081/api/health
   ```

## Quick Start

### 1. Start the Example Container

```bash
docker-compose up -d
```

### 2. Test Web Request Profiling

The `index.php` file demonstrates several ways to enable profiling:

```bash
# Test without profiling (default)
docker exec -it opa-example-php php /app/index.php

# Test with profiling enabled via header simulation
docker exec -it opa-example-php php -r "
\$_SERVER['HTTP_X_ENABLE_PROFILING'] = 'true';
\$_SERVER['REQUEST_URI'] = '/api/users';
include '/app/index.php';
"
```

### 3. Test CLI Profiling

```bash
# Without profiling (default)
docker exec -it opa-example-php php /app/cli-example.php

# With profiling enabled via environment variable
docker exec -it opa-example-php sh -c "OPA_ENABLE=1 php /app/cli-example.php"
```

## Examples Explained

### Example 1: Enable via HTTP Header

```php
if (isset($_SERVER['HTTP_X_ENABLE_PROFILING']) && 
    $_SERVER['HTTP_X_ENABLE_PROFILING'] === 'true') {
    opa_enable();
}
```

**Usage**: Send a request with header `X-Enable-Profiling: true`

### Example 2: Enable for Specific Routes

```php
$profileRoutes = ['/api/users', '/api/orders', '/admin'];
if (in_array($requestUri, $profileRoutes)) {
    opa_enable();
}
```

**Usage**: Automatically profiles requests to specific routes

### Example 3: Enable for Slow Requests

```php
$duration = (microtime(true) - $startTime) * 1000;
if ($duration > 50) {
    opa_enable();
}
```

**Usage**: Automatically profiles requests that exceed a time threshold

### Example 4: Enable on Errors

```php
try {
    performOperation();
} catch (Exception $e) {
    opa_enable();
    // Re-process with profiling to capture error context
}
```

**Usage**: Automatically profiles when errors occur

### Example 5: CLI Environment Variable

```bash
# Enable profiling for a single command
OPA_ENABLE=1 php script.php

# With additional configuration
OPA_ENABLE=1 OPA_SAMPLING_RATE=1.0 php artisan migrate
```

**Usage**: Profile specific CLI commands without code changes

## Configuration

### Docker Compose Configuration

Key setting in `docker-compose.yml`:

```yaml
environment:
  # Profiling disabled by default
  - OPA_ENABLED=0
  
  # Agent connection configured
  - OPA_SOCKET_PATH=agent:9090
  
  # Other settings ready for when profiling is enabled
  - OPA_SAMPLING_RATE=1.0
  - OPA_STACK_DEPTH=20
```

### PHP INI Configuration

If configuring via INI file:

```ini
; Disable profiling by default
opa.enabled=0

; Configure agent connection
opa.socket_path=agent:9090

; Other settings
opa.sampling_rate=1.0
opa.stack_depth=20
```

## Viewing Traces

After running examples with profiling enabled:

1. **Open Dashboard**: http://localhost:3000
2. **Look for traces** from service `php-example`
3. **Filter by trace ID** if you captured it in the output

## Best Practices

### When to Use On-the-Fly Profiling

✅ **Good for**:
- Production environments where you want minimal overhead
- Debugging specific issues or slow requests
- Profiling specific user segments or routes
- CLI commands that run occasionally

❌ **Not ideal for**:
- Development environments (use always-on with sampling)
- High-frequency monitoring (use always-on with low sampling rate)
- Real-time performance monitoring (use always-on)

### Performance Considerations

- **Overhead when disabled**: Near zero (just a function call check)
- **Overhead when enabled**: Similar to always-on profiling
- **Recommendation**: Use sampling rate (`OPA_SAMPLING_RATE=0.1`) even when enabling on-the-fly

### Security Considerations

- Don't enable profiling based on user input without validation
- Use secure headers or environment variables for enabling profiling
- Consider rate limiting for profiling activation

## Troubleshooting

### Profiling Not Working

1. **Check extension is loaded**:
   ```bash
   docker exec opa-example-php php -m | grep opa
   ```

2. **Check configuration**:
   ```bash
   docker exec opa-example-php php --ri opa
   ```

3. **Verify agent connection**:
   ```bash
   docker exec opa-example-php ping agent
   docker exec opa-example-php nc -zv agent 9090
   ```

4. **Check agent logs**:
   ```bash
   docker logs opa-agent
   ```

### CLI Environment Variable Not Working

1. **Verify variable is set**:
   ```bash
   docker exec opa-example-php sh -c "OPA_ENABLE=1 env | grep OPA_ENABLE"
   ```

2. **Check extension checks for variable** (requires implementation):
   - Extension must check `OPA_ENABLE` in CLI mode
   - See implementation in `php-extension/src/opa.c` RINIT function

## Next Steps

- See [Docker Setup Guide](../../docs/DOCKER_SETUP.md) for complete setup instructions
- See [Runtime Configuration Guide](../../php-extension/docs/RUNTIME_CONFIGURATION.md) for advanced configuration
- See [User Guide](../../docs/GUIDE.md) for more usage examples

