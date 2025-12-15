# Call All Controllers Command

This command calls all Symfony controller endpoints to generate test data for testing and database population.

## Features

- **Multiple Iterations**: Repeat database operations multiple times to generate large datasets
- **Data Size Multiplier**: Scale the size of generated data
- **Comprehensive Coverage**: Calls all endpoints from all 8 controllers
- **Progress Tracking**: Real-time progress bars and summary statistics
- **Error Handling**: Graceful error handling with detailed reporting

## Usage

### Basic Usage

```bash
php bin/console app:call-all-controllers
```

### With Options

```bash
# Custom base URL
php bin/console app:call-all-controllers --base-url=http://localhost:8080

# Skip cURL endpoints (to avoid segfaults)
php bin/console app:call-all-controllers --skip-curl

# Multiple iterations for big database data
php bin/console app:call-all-controllers --iterations=50

# Increase data size multiplier
php bin/console app:call-all-controllers --data-size=5

# Combine all options
php bin/console app:call-all-controllers \
    --base-url=http://localhost:8080 \
    --iterations=100 \
    --data-size=2 \
    --skip-curl
```

## Options

- `--base-url` / `-u`: Base URL for the Symfony app (default: `http://localhost:8080`)
- `--skip-curl`: Skip endpoints that use cURL (may cause segfaults)
- `--iterations` / `-i`: Number of iterations to repeat database operations (default: `10`)
- `--data-size` / `-s`: Size multiplier for data generation, 1-100 (default: `1`)

## What It Does

The command calls endpoints from all controllers:

### 1. Redis Profiling Test Controller
- All Redis operations (SET, GET, DELETE, EXISTS, INCR/DECR, Hash, List, Set, Expire)
- Multiple iterations of key operations

### 2. HTTP Methods Test Controller
- All HTTP methods (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
- Various response sizes (100B to 512KB)
- Multiple iterations with different payloads

### 3. Comprehensive Profiling Test Controller
- Comprehensive profiling with all features
- Multiple iterations with varied data

### 4. MySQLi Profiling Test Controller
- Database operations (CREATE, INSERT, SELECT, UPDATE, DELETE)
- Multiple iterations for data generation
- Complex queries and batch operations

### 5. PDO Profiling Test Controller
- PDO operations (query, exec, prepare/execute, transactions)
- Multiple iterations for data generation
- Complex queries and batch operations

### 6. Request/Response Test Controller
- Request/response size testing
- Various payload sizes
- Multiple iterations with different data

### 7. Service Map Test Controller
- HTTP calls to various external hosts (httpbin.org, jsonplaceholder, reqres.in, etc.)
- Multiple HTTP methods testing (GET, POST, PUT, PATCH, DELETE)
- Various status codes testing (200, 201, 400, 401, 403, 404, 500, 503)
- Latency testing with delays (0.1s to 5s)
- Generates service map dependencies and relationships

### 8. Dump Test Controller
- Dump functionality testing

## Generating Big Database Data

To generate large amounts of database data:

```bash
# 100 iterations with 3x data size
php bin/console app:call-all-controllers --iterations=100 --data-size=3

# 200 iterations for maximum data
php bin/console app:call-all-controllers --iterations=200 --data-size=5
```

This will:
- Create multiple database records through INSERT operations
- Execute SELECT queries to read data
- Perform UPDATE operations
- Run complex queries
- Execute transactions
- Generate varied payload sizes

## Example Output

```
Calling All Symfony Controllers for Test Data Generation
  Iterations: 50
  Data Size Multiplier: 2x

Redis Profiling Test Controller
 50/50 [████████████████████████] 100% - GET /api/test/redis/multiple - 200
  ✓ Success: 61
  ✗ Errors: 0

MySQLi Profiling Test Controller
 150/150 [████████████████████████] 100% - GET /api/test/mysqli/insert - 200
  ✓ Success: 150
  ✗ Errors: 0

...

Summary
  Redis: 61 success, 0 errors
  HTTP Methods: 245 success, 0 errors
  Comprehensive: 54 success, 0 errors
  MySQLi: 150 success, 0 errors
  PDO: 180 success, 0 errors
  Request/Response: 165 success, 0 errors
  Service Map: 120 success, 0 errors
  Dump: 1 success, 0 errors

  Total Calls: 976
  Total Success: 976
```

## Service Map Generation

The Service Map Test Controller makes HTTP calls to various external hosts to generate service map data:

- **httpbin.org**: HTTP testing service with various endpoints
- **jsonplaceholder.typicode.com**: Fake REST API
- **reqres.in**: Sample REST API
- **httpstat.us**: Status code testing
- Various HTTP methods (GET, POST, PUT, PATCH, DELETE)
- Various status codes (200, 201, 400, 401, 403, 404, 500, 503)
- Various latencies (0.1s to 5s delays)

These calls create spans that track external service dependencies, which appear in the service map visualization.

### Example: Generate Service Map Data

```bash
# Generate service map data with multiple iterations
php bin/console app:call-all-controllers --iterations=20

# Note: Service map tests require cURL, so don't use --skip-curl
```

## Requirements

- Symfony 7.0
- PHP 8.2+
- Symfony HTTP Client component (automatically installed via composer)
- cURL extension (for service map tests)

## Installation

The HTTP Client component is automatically added to `composer.json`. Install it:

```bash
cd symfony-app
composer require symfony/http-client:^7.0
```

## Testing

Run the test script:

```bash
cd symfony-app
./test_call_all_controllers.sh
```

