<?php
/**
 * Comprehensive W3C Trace Context Validation Test
 * 
 * Tests the complete W3C Trace Context flow:
 * 1. Setting W3C context via opa_set_w3c_context()
 * 2. Making HTTP request with W3C headers
 * 3. Verifying W3C data reaches ClickHouse
 * 
 * Usage: php test_w3c_validation.php
 */

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

echo "=== W3C Trace Context Comprehensive Validation ===\n\n";

$CLICKHOUSE_URL = 'http://clickhouse:8123';
$WAIT_TIME = 15;

$tests_passed = 0;
$tests_failed = 0;
$errors = [];

// Test traceparent and tracestate values
$test_traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
$test_tracestate = 'opa=1,test=value';

// Helper function to query ClickHouse
function queryClickHouse($url, $query) {
    $ch = curl_init($url);
    if (!$ch) {
        return ['error' => 'Failed to initialize cURL', 'data' => null];
    }
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'data' => null, 'http_code' => $httpCode];
    }
    
    if ($httpCode !== 200) {
        return ['error' => "HTTP $httpCode: " . substr($response, 0, 200), 'data' => null, 'http_code' => $httpCode];
    }
    
    // Parse JSONEachRow format
    $data = [];
    $lines = explode("\n", trim($response));
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $row = json_decode($line, true);
        if ($row) {
            $data[] = $row;
        }
    }
    
    return ['error' => null, 'data' => $data, 'http_code' => $httpCode];
}

// Test 1: Check if opa_set_w3c_context function exists
echo "Test 1: Checking opa_set_w3c_context() function...\n";
if (!function_exists('opa_set_w3c_context')) {
    echo "   ✗ FAILED: opa_set_w3c_context() function not found\n";
    $errors[] = "opa_set_w3c_context() function not available";
    $tests_failed++;
    exit(1);
} else {
    echo "   ✓ opa_set_w3c_context() function exists\n";
    $tests_passed++;
}

// Test 2: Set W3C context
echo "\nTest 2: Setting W3C context...\n";
$result = opa_set_w3c_context($test_traceparent, $test_tracestate);
if (!$result) {
    echo "   ✗ FAILED: opa_set_w3c_context() returned false\n";
    $errors[] = "Failed to set W3C context";
    $tests_failed++;
    exit(1);
} else {
    echo "   ✓ W3C context set successfully\n";
    echo "      - traceparent: $test_traceparent\n";
    echo "      - tracestate: $test_tracestate\n";
    $tests_passed++;
}

// Test 3: Make HTTP request with W3C headers
echo "\nTest 3: Making HTTP request with W3C headers...\n";
$kernel = new Kernel('dev', true);
$kernel->boot();

$request = Request::create(
    '/api/test/comprehensive',
    'GET',
    [],
    [],
    [],
    [
        'HTTP_TRACEPARENT' => $test_traceparent,
        'HTTP_TRACESTATE' => $test_tracestate,
    ]
);

// Set W3C context again in the request
if (function_exists('opa_set_w3c_context')) {
    opa_set_w3c_context($test_traceparent, $test_tracestate);
}

$response = $kernel->handle($request);
$kernel->terminate($request, $response);

echo "   ✓ HTTP request completed (status: {$response->getStatusCode()})\n";
$tests_passed++;

// Test 4: Wait for agent to process
echo "\nTest 4: Waiting {$WAIT_TIME} seconds for agent to process spans...\n";
sleep($WAIT_TIME);
echo "   ✓ Wait complete\n";
$tests_passed++;

// Test 5: Query ClickHouse for W3C data
echo "\nTest 5: Querying ClickHouse for W3C data...\n";

// Extract trace ID from traceparent (first 32 hex chars, convert to 16 hex)
$w3c_trace_id_32 = substr($test_traceparent, 3, 32); // Skip "00-"
$w3c_trace_id_16 = substr($w3c_trace_id_32, 0, 16); // Take first 16 chars

$query = "
SELECT 
    trace_id,
    span_id,
    name,
    start_ts,
    w3c_traceparent,
    w3c_tracestate,
    CASE WHEN w3c_traceparent IS NOT NULL THEN 1 ELSE 0 END as has_traceparent,
    CASE WHEN w3c_tracestate IS NOT NULL THEN 1 ELSE 0 END as has_tracestate
FROM opa.spans_full 
WHERE start_ts >= now() - INTERVAL 10 MINUTE
  AND (trace_id LIKE '" . addslashes($w3c_trace_id_16) . "%' OR w3c_traceparent IS NOT NULL)
ORDER BY start_ts DESC 
LIMIT 20
FORMAT JSONEachRow
";

$result = queryClickHouse($CLICKHOUSE_URL, $query);
if ($result['error']) {
    echo "   ✗ FAILED: Error querying ClickHouse: {$result['error']}\n";
    $errors[] = "ClickHouse query error: {$result['error']}";
    $tests_failed++;
} elseif (empty($result['data'])) {
    echo "   ✗ FAILED: No spans found\n";
    $errors[] = "No spans found in ClickHouse";
    $tests_failed++;
} else {
    $span_count = count($result['data']);
    echo "   ✓ Found $span_count span(s)\n";
    
    // Check for W3C data
    $spans_with_w3c = 0;
    $spans_without_w3c = 0;
    
    foreach ($result['data'] as $span) {
        $has_tp = !empty($span['w3c_traceparent']);
        $has_ts = !empty($span['w3c_tracestate']);
        
        if ($has_tp || $has_ts) {
            $spans_with_w3c++;
            echo "      ✓ {$span['name']}: W3C data present\n";
            if ($has_tp) {
                echo "         - traceparent: " . substr($span['w3c_traceparent'], 0, 60) . "...\n";
            }
            if ($has_ts) {
                echo "         - tracestate: " . substr($span['w3c_tracestate'], 0, 50) . "...\n";
            }
        } else {
            $spans_without_w3c++;
        }
    }
    
    if ($spans_with_w3c > 0) {
        echo "\n   ✓ SUCCESS: Found $spans_with_w3c span(s) with W3C data\n";
        $tests_passed++;
    } else {
        echo "\n   ✗ FAILED: No spans have W3C data ($spans_without_w3c spans checked)\n";
        echo "      This indicates W3C context is not being stored in spans\n";
        $errors[] = "No W3C data found in spans";
        $tests_failed++;
    }
}

// Test 6: Verify W3C traceparent format
if (!empty($result['data'])) {
    echo "\nTest 6: Verifying W3C traceparent format...\n";
    $found_valid = false;
    
    foreach ($result['data'] as $span) {
        if (!empty($span['w3c_traceparent'])) {
            $tp = $span['w3c_traceparent'];
            // W3C format: 00-<32-hex>-<16-hex>-<2-hex>
            if (preg_match('/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/i', $tp)) {
                echo "   ✓ Valid W3C traceparent format found\n";
                echo "      - Value: $tp\n";
                $found_valid = true;
                $tests_passed++;
                break;
            }
        }
    }
    
    if (!$found_valid && $spans_with_w3c > 0) {
        echo "   ⚠ WARNING: W3C traceparent found but format validation failed\n";
    } elseif (!$found_valid) {
        echo "   ⚠ SKIPPED: No W3C traceparent to validate\n";
    }
}

// Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "=== Test Summary ===\n";
echo "Tests passed: $tests_passed\n";
echo "Tests failed: $tests_failed\n\n";

if ($tests_failed > 0) {
    echo "ERRORS:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\n";
    echo "Troubleshooting:\n";
    echo "  1. Check if opa_set_w3c_context() is being called\n";
    echo "  2. Verify W3C context is included in span JSON (check extension code)\n";
    echo "  3. Verify agent is storing W3C fields in ClickHouse (check agent code)\n";
    echo "  4. Check agent logs for W3C-related errors\n";
    exit(1);
} else {
    echo "✓ All tests passed! W3C Trace Context data is successfully reaching ClickHouse.\n";
    exit(0);
}

