# Test Features Overview

This document lists all the features tested by the Symfony application and their corresponding endpoints.

## SQL Profiling

### PDO Queries
- **Endpoint**: `GET /api/sql/pdo`
- **Tests**: CREATE TABLE, INSERT, SELECT, UPDATE, COUNT
- **OPA Feature**: Automatic PDO query instrumentation

### MySQLi Queries
- **Endpoint**: `GET /api/sql/mysqli`
- **Tests**: CREATE TABLE, INSERT, SELECT, COUNT
- **OPA Feature**: Automatic MySQLi query instrumentation

### Prepared Statements
- **Endpoint**: `GET /api/sql/prepared`
- **Tests**: Prepared statements with parameters
- **OPA Feature**: Prepared statement tracking

## HTTP/cURL Profiling

### Simple Request
- **Endpoint**: `GET /api/curl/simple`
- **Tests**: Basic GET request to external API
- **OPA Feature**: Automatic cURL request instrumentation

### Multiple Requests
- **Endpoint**: `GET /api/curl/multiple`
- **Tests**: Multiple requests with different HTTP status codes (200, 404, 500)
- **OPA Feature**: Multiple request tracking

### POST Request
- **Endpoint**: `GET /api/curl/post`
- **Tests**: POST request with JSON data
- **OPA Feature**: POST request instrumentation

### Delayed Response
- **Endpoint**: `GET /api/curl/delayed`
- **Tests**: Request with delayed response (2 seconds)
- **OPA Feature**: Response time tracking

## Error Tracking

### Exception
- **Endpoint**: `GET /api/errors/exception`
- **Tests**: Throws and tracks RuntimeException
- **OPA Feature**: Automatic exception tracking

### Fatal Error
- **Endpoint**: `GET /api/errors/fatal`
- **Tests**: Division by zero (fatal error)
- **OPA Feature**: Fatal error tracking

### Warning
- **Endpoint**: `GET /api/errors/warning`
- **Tests**: File not found warning
- **OPA Feature**: Warning tracking

### Notice
- **Endpoint**: `GET /api/errors/notice`
- **Tests**: Undefined variable notice
- **OPA Feature**: Notice tracking

### Custom Error
- **Endpoint**: `GET /api/errors/custom`
- **Tests**: Custom error class
- **OPA Feature**: Custom error tracking

## Cache Operations

### Redis Cache
- **Endpoint**: `GET /api/cache/redis`
- **Tests**: Redis cache set/get operations
- **OPA Feature**: Cache operation instrumentation

### APCu Cache
- **Endpoint**: `GET /api/cache/apcu`
- **Tests**: APCu cache operations
- **OPA Feature**: APCu operation tracking

### Mixed Cache
- **Endpoint**: `GET /api/cache/mixed`
- **Tests**: Both Redis and APCu operations
- **OPA Feature**: Multiple cache backend tracking

## Custom Spans

### Custom Spans
- **Endpoint**: `GET /api/spans/custom`
- **Tests**: Manual span creation with tags
- **OPA Feature**: Manual instrumentation API

### Nested Spans
- **Endpoint**: `GET /api/spans/nested`
- **Tests**: 3-level nested span hierarchy
- **OPA Feature**: Parent-child span relationships

### Span Tags
- **Endpoint**: `GET /api/spans/tags`
- **Tests**: Adding multiple tags to spans
- **OPA Feature**: Span metadata/tags

## File I/O Operations

### File Read
- **Endpoint**: `GET /api/files/read`
- **Tests**: File read operations
- **OPA Feature**: File I/O instrumentation

### File Write
- **Endpoint**: `GET /api/files/write`
- **Tests**: File write and append operations
- **OPA Feature**: File write tracking

### Multiple Files
- **Endpoint**: `GET /api/files/multiple`
- **Tests**: Multiple file operations
- **OPA Feature**: Batch file operations

## Log Tracking

### Error Log
- **Endpoint**: `GET /api/logs/error`
- **Tests**: error_log() calls
- **OPA Feature**: Log tracking

### Log Levels
- **Endpoint**: `GET /api/logs/levels`
- **Tests**: Different log levels (ERROR, WARNING, INFO, DEBUG)
- **OPA Feature**: Log level tracking

### Log Context
- **Endpoint**: `GET /api/logs/context`
- **Tests**: Contextual logging with metadata
- **OPA Feature**: Contextual log tracking

## Comprehensive Test

### All Features
- **Endpoint**: `GET /api/comprehensive`
- **Tests**: All features together (SQL, Cache, cURL, File I/O, Spans, Logs)
- **OPA Feature**: Multiple feature integration

## Health Check

### Health
- **Endpoint**: `GET /health`
- **Tests**: Application health status
- **OPA Feature**: Basic request tracking

## Testing Strategy

Each endpoint is designed to:
1. Trigger specific OPA instrumentation
2. Return JSON response with test results
3. Be testable via PHPUnit
4. Generate traceable spans in OPA

## Viewing Results

After calling endpoints, you can view the results in:
- **OPA Dashboard**: http://localhost:3000
- **Agent API**: http://localhost:8081/api/traces
- **ClickHouse**: Direct queries to spans tables

## Example Usage

```bash
# Test SQL profiling
curl http://localhost:8080/api/sql/pdo

# Test error tracking
curl http://localhost:8080/api/errors/exception

# Test comprehensive features
curl http://localhost:8080/api/comprehensive

# View traces in OPA dashboard
open http://localhost:3000
```

