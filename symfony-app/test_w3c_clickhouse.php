<?php
/**
 * Simple PHP test script to validate W3C Trace Context data reaches ClickHouse
 * 
 * Usage: php test_w3c_clickhouse.php
 */

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

echo "=== W3C Trace Context ClickHouse Validation Test ===\n\n";

// Test traceparent and tracestate values
$test_traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
$test_tracestate = 'opa=1,test=value';

echo "1. Testing opa_set_w3c_context() function...\n";
if (!function_exists('opa_set_w3c_context')) {
    die("ERROR: opa_set_w3c_context() function not found. Is the OPA extension loaded?\n");
}

$result = opa_set_w3c_context($test_traceparent, $test_tracestate);
echo "   Function call result: " . ($result ? "SUCCESS" : "FAILED") . "\n\n";

if (!$result) {
    die("ERROR: Failed to set W3C context\n");
}

echo "2. Making HTTP request to generate a span...\n";
$kernel = new Kernel('dev', true);
$kernel->boot();

$request = Request::create(
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

// Set W3C context again in the request (simulating controller behavior)
if (function_exists('opa_set_w3c_context')) {
    opa_set_w3c_context($test_traceparent, $test_tracestate);
}

$response = $kernel->handle($request);
echo "   Response status: " . $response->getStatusCode() . "\n";
$kernel->terminate($request, $response);

echo "\n3. Waiting for span to be processed...\n";
sleep(10);

echo "\n4. Querying ClickHouse for W3C data...\n";

// Connect to ClickHouse
$ch = curl_init('http://clickhouse:8123/');
if (!$ch) {
    die("ERROR: Failed to initialize cURL\n");
}

// Query for spans with W3C data (check recent spans)
$query = "
SELECT 
    trace_id,
    span_id,
    name,
    start_ts,
    w3c_traceparent IS NOT NULL as has_traceparent,
    w3c_tracestate IS NOT NULL as has_tracestate,
    w3c_traceparent,
    w3c_tracestate,
    substring(w3c_traceparent, 1, 60) as tp_preview,
    substring(w3c_tracestate, 1, 50) as ts_preview
FROM opa.spans_full 
WHERE start_ts >= now() - INTERVAL 10 MINUTE
ORDER BY start_ts DESC 
LIMIT 20
FORMAT JSONEachRow
";

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $query,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("ERROR: ClickHouse query failed with HTTP code: $httpCode\nResponse: $response\n");
}

echo "\n5. Results:\n";
echo str_repeat("=", 80) . "\n";

$spans = [];
$lines = explode("\n", trim($response));
foreach ($lines as $line) {
    if (empty(trim($line))) continue;
    $span = json_decode($line, true);
    if ($span) {
        $spans[] = $span;
    }
}

if (empty($spans)) {
    echo "WARNING: No spans found in the last minute\n";
    echo "This might mean:\n";
    echo "  - The span hasn't been processed yet (wait longer)\n";
    echo "  - The agent isn't receiving spans\n";
    echo "  - There's an issue with span generation\n";
    exit(1);
}

$found_w3c = false;
foreach ($spans as $span) {
    echo sprintf(
        "Trace ID: %s | Span ID: %s | Name: %s\n",
        $span['trace_id'] ?? 'N/A',
        $span['span_id'] ?? 'N/A',
        $span['name'] ?? 'N/A'
    );
    
    $has_tp = $span['has_traceparent'] ?? false;
    $has_ts = $span['has_tracestate'] ?? false;
    
    echo sprintf(
        "  W3C Traceparent: %s\n",
        $has_tp ? "✓ PRESENT" : "✗ MISSING"
    );
    
    if ($has_tp) {
        echo sprintf("    Value: %s\n", $span['tp_preview'] ?? $span['w3c_traceparent'] ?? 'N/A');
        $found_w3c = true;
    }
    
    echo sprintf(
        "  W3C Tracestate: %s\n",
        $has_ts ? "✓ PRESENT" : "✗ MISSING"
    );
    
    if ($has_ts) {
        echo sprintf("    Value: %s\n", $span['ts_preview'] ?? $span['w3c_tracestate'] ?? 'N/A');
        $found_w3c = true;
    }
    
    echo "\n";
}

echo str_repeat("=", 80) . "\n";

if ($found_w3c) {
    echo "\n✓ SUCCESS: W3C Trace Context data is present in ClickHouse!\n";
    exit(0);
} else {
    echo "\n✗ FAILURE: No W3C Trace Context data found in ClickHouse spans\n";
    echo "\nPossible issues:\n";
    echo "  1. opa_set_w3c_context() is not being called\n";
    echo "  2. W3C context is not persisting until RSHUTDOWN\n";
    echo "  3. W3C fields are not being added to span JSON\n";
    echo "  4. Agent is not storing W3C fields in ClickHouse\n";
    exit(1);
}
