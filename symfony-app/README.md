# Symfony Test Application for OpenProfilingAgent

This is a comprehensive Symfony 7 application designed to test all features of the OpenProfilingAgent PHP extension.

## Features Tested

### SQL Profiling
- **PDO Queries**: `/api/sql/pdo` - Tests PDO database operations
- **MySQLi Queries**: `/api/sql/mysqli` - Tests MySQLi database operations
- **Prepared Statements**: `/api/sql/prepared` - Tests prepared statement execution

### HTTP/cURL Profiling
- **Simple Request**: `/api/curl/simple` - Basic GET request
- **Multiple Requests**: `/api/curl/multiple` - Multiple requests with different status codes
- **POST Request**: `/api/curl/post` - POST request with JSON data
- **Delayed Response**: `/api/curl/delayed` - Request with delayed response

### Error Tracking
- **Exception**: `/api/errors/exception` - Throws and tracks exceptions
- **Fatal Error**: `/api/errors/fatal` - Triggers fatal errors
- **Warning**: `/api/errors/warning` - Triggers warnings
- **Notice**: `/api/errors/notice` - Triggers notices
- **Custom Error**: `/api/errors/custom` - Custom error class

### Cache Operations
- **Redis**: `/api/cache/redis` - Redis cache operations
- **APCu**: `/api/cache/apcu` - APCu cache operations
- **Mixed**: `/api/cache/mixed` - Both Redis and APCu

### Custom Spans
- **Custom Spans**: `/api/spans/custom` - Manual span creation
- **Nested Spans**: `/api/spans/nested` - Nested span hierarchy
- **Span Tags**: `/api/spans/tags` - Adding tags to spans

### File I/O
- **File Read**: `/api/files/read` - File reading operations
- **File Write**: `/api/files/write` - File writing operations
- **Multiple Files**: `/api/files/multiple` - Multiple file operations

### Log Tracking
- **Error Log**: `/api/logs/error` - error_log() calls
- **Log Levels**: `/api/logs/levels` - Different log levels
- **Log Context**: `/api/logs/context` - Contextual logging

### Comprehensive Test
- **All Features**: `/api/comprehensive` - Tests all features together

## Running with Docker

### Prerequisites

1. Ensure the `opa_network` Docker network exists:
```bash
docker network create opa_network
```

2. Build the OPA PHP extension (if not already built):
```bash
cd php-extension
docker build -f docker/Dockerfile.test -t opa-php-extension:latest .
```

### Start Services

```bash
# Start all services (including Symfony app with Nginx)
docker-compose up -d

# Or start with Apache instead of Nginx
docker-compose --profile apache up -d symfony-apache
```

### Access the Application

- **Nginx**: http://localhost:8080
- **Apache**: http://localhost:8081
- **Health Check**: http://localhost:8080/health

### Test Endpoints

```bash
# Test SQL profiling
curl http://localhost:8080/api/sql/pdo
curl http://localhost:8080/api/sql/mysqli

# Test cURL profiling
curl http://localhost:8080/api/curl/simple

# Test error tracking
curl http://localhost:8080/api/errors/exception

# Test cache operations
curl http://localhost:8080/api/cache/redis

# Test comprehensive features
curl http://localhost:8080/api/comprehensive
```

## Running Tests

### PHPUnit Tests

```bash
# Run tests inside the PHP container
docker exec -it symfony-php bash -c "cd /var/www/symfony && composer install && php bin/phpunit"
```

### Manual Testing

You can test all endpoints manually using curl or a browser:

```bash
# Health check
curl http://localhost:8080/health

# SQL tests
curl http://localhost:8080/api/sql/pdo
curl http://localhost:8080/api/sql/mysqli
curl http://localhost:8080/api/sql/prepared

# cURL tests
curl http://localhost:8080/api/curl/simple
curl http://localhost:8080/api/curl/multiple
curl http://localhost:8080/api/curl/post
curl http://localhost:8080/api/curl/delayed

# Error tests
curl http://localhost:8080/api/errors/exception
curl http://localhost:8080/api/errors/warning

# Cache tests
curl http://localhost:8080/api/cache/redis
curl http://localhost:8080/api/cache/apcu
curl http://localhost:8080/api/cache/mixed

# Span tests
curl http://localhost:8080/api/spans/custom
curl http://localhost:8080/api/spans/nested
curl http://localhost:8080/api/spans/tags

# File tests
curl http://localhost:8080/api/files/read
curl http://localhost:8080/api/files/write
curl http://localhost:8080/api/files/multiple

# Log tests
curl http://localhost:8080/api/logs/error
curl http://localhost:8080/api/logs/levels
curl http://localhost:8080/api/logs/context

# Comprehensive test
curl http://localhost:8080/api/comprehensive
```

## Configuration

### Environment Variables

The application uses environment variables for configuration:

- `DATABASE_URL`: MySQL connection string
- `REDIS_URL`: Redis connection string
- `APP_ENV`: Application environment (dev, prod, test)
- `APP_SECRET`: Application secret key

### OPA Extension Configuration

**OPA is disabled by default** to avoid conflicts with Composer and other tools. To enable it:

1. Create a `.env.local` file in the `symfony-app` directory:
```bash
OPA_ENABLED=1
OPA_SOCKET_PATH=/var/run/opa.sock
OPA_SAMPLING_RATE=1.0
OPA_DEBUG_LOG=1
```

2. Or set environment variables in `docker-compose.yml`:
```yaml
environment:
  - OPA_ENABLED=1
  - OPA_SOCKET_PATH=/var/run/opa.sock
  - OPA_SAMPLING_RATE=1.0
  - OPA_DEBUG_LOG=1
```

3. Restart the container:
```bash
docker-compose restart symfony-php
```

**Available OPA settings:**
- `OPA_ENABLED`: Enable/disable OPA profiling (0 or 1, default: 0)
- `OPA_SOCKET_PATH`: OPA socket path (default: /var/run/opa.sock)
- `OPA_SAMPLING_RATE`: Sampling rate (0.0 to 1.0, default: 1.0)
- `OPA_DEBUG_LOG`: Enable debug logging (0 or 1, default: 0)

### Database Setup

The MySQL database is automatically created when the container starts. The application will create tables as needed.

## Architecture

- **PHP-FPM**: Handles PHP execution with OPA extension
- **Nginx**: Reverse proxy and web server (default)
- **Apache**: Alternative web server (optional, use `--profile apache`)
- **MySQL**: Database for SQL profiling tests
- **Redis**: Cache for cache operation tests

All services are connected to the `opa_network` Docker network and can communicate with the OPA agent.

## Development

### Local Development

1. Install dependencies:
```bash
cd symfony-app
composer install
```

2. Configure environment:
```bash
cp .env .env.local
# Edit .env.local with your settings
```

3. Run the application:
```bash
symfony server:start
```

### Docker Development

1. Make changes to the code
2. Changes are automatically reflected (volumes are mounted)
3. Restart the container if needed:
```bash
docker-compose restart symfony-php
```

## Troubleshooting

### OPA Extension Not Working

Check if the extension is loaded:
```bash
docker exec -it symfony-php php -m | grep opa
```

Check OPA configuration:
```bash
docker exec -it symfony-php php -i | grep opa
```

### Database Connection Issues

Check MySQL container:
```bash
docker exec -it mysql-symfony mysql -u symfony_user -psymfony_password symfony_db
```

### Redis Connection Issues

Check Redis container:
```bash
docker exec -it redis-symfony redis-cli ping
```

## License

MIT

