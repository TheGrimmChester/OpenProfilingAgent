<?php
/**
 * Simple test to verify W3C data flow
 */

require __DIR__ . '/vendor/autoload.php';

$test_traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
$test_tracestate = 'opa=1,test=value';

echo "=== Simple W3C Test ===\n\n";

// Test 1: Function exists
echo "1. Function exists: " . (function_exists('opa_set_w3c_context') ? 'YES' : 'NO') . "\n";

// Test 2: Call function
if (function_exists('opa_set_w3c_context')) {
    $result = opa_set_w3c_context($test_traceparent, $test_tracestate);
    echo "2. Function call result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
} else {
    echo "2. Function not available\n";
    exit(1);
}

// Test 3: Make a simple request
echo "\n3. Making HTTP request...\n";
$kernel = new App\Kernel('dev', true);
$kernel->boot();

$request = Symfony\Component\HttpFoundation\Request::create(
    '/api/test/pdo/simple',
    'GET',
    [],
    [],
    [],
    [
        'HTTP_TRACEPARENT' => $test_traceparent,
        'HTTP_TRACESTATE' => $test_tracestate,
    ]
);

// Set W3C context
opa_set_w3c_context($test_traceparent, $test_tracestate);

$response = $kernel->handle($request);
echo "   Status: " . $response->getStatusCode() . "\n";
$kernel->terminate($request, $response);

echo "\n4. Waiting 10 seconds for processing...\n";
sleep(10);

// Test 4: Query ClickHouse directly
echo "\n5. Querying ClickHouse...\n";
$ch = curl_init('http://clickhouse:8123/');
$query = "SELECT trace_id, span_id, name, w3c_traceparent, w3c_tracestate FROM opa.spans_full WHERE w3c_traceparent IS NOT NULL ORDER BY start_ts DESC LIMIT 3 FORMAT JSONEachRow";
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $query,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $lines = explode("\n", trim($response));
    $count = 0;
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $span = json_decode($line, true);
        if ($span && isset($span['w3c_traceparent'])) {
            $count++;
            echo "   Found span with W3C data:\n";
            echo "     Trace ID: " . ($span['trace_id'] ?? 'N/A') . "\n";
            echo "     W3C Traceparent: " . substr($span['w3c_traceparent'] ?? 'N/A', 0, 60) . "...\n";
            if (isset($span['w3c_tracestate'])) {
                echo "     W3C Tracestate: " . substr($span['w3c_tracestate'], 0, 50) . "...\n";
            }
        }
    }
    if ($count === 0) {
        echo "   ✗ No spans with W3C data found\n";
    } else {
        echo "\n   ✓ Found $count span(s) with W3C data\n";
    }
} else {
    echo "   ✗ Query failed: HTTP $httpCode\n";
}

echo "\n=== Test Complete ===\n";

