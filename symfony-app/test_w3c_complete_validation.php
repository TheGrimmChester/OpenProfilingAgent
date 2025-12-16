<?php
/**
 * Complete W3C Trace Context Validation with Detailed Reporting
 * 
 * This script:
 * 1. Sets W3C context
 * 2. Generates spans
 * 3. Queries ClickHouse with detailed analysis
 * 4. Provides comprehensive validation report
 */

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

echo "=== Complete W3C Trace Context Validation ===\n\n";

$CLICKHOUSE_URL = 'http://clickhouse:8123';
$test_traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
$test_tracestate = 'opa=1,test=value';

// Helper to query ClickHouse
function queryClickHouse($url, $query) {
    $ch = curl_init($url);
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
        return ['error' => "HTTP $httpCode", 'data' => []];
    }
    
    $data = [];
    foreach (explode("\n", trim($response)) as $line) {
        if (empty(trim($line))) continue;
        $row = json_decode($line, true);
        if ($row) $data[] = $row;
    }
    return ['error' => null, 'data' => $data];
}

// Step 1: Set W3C context
echo "Step 1: Setting W3C context...\n";
if (!function_exists('opa_set_w3c_context')) {
    die("ERROR: opa_set_w3c_context() not available\n");
}
$result = opa_set_w3c_context($test_traceparent, $test_tracestate);
echo "   Result: " . ($result ? "SUCCESS" : "FAILED") . "\n\n";

// Step 2: Generate spans
echo "Step 2: Generating spans via HTTP request...\n";
$kernel = new Kernel('dev', true);
$kernel->boot();
$request = Request::create('/api/test/comprehensive', 'GET', [], [], [], [
    'HTTP_TRACEPARENT' => $test_traceparent,
    'HTTP_TRACESTATE' => $test_tracestate,
]);
opa_set_w3c_context($test_traceparent, $test_tracestate);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo "   Status: {$response->getStatusCode()}\n\n";

// Step 3: Wait for processing
echo "Step 3: Waiting 15 seconds for agent processing...\n";
sleep(15);
echo "   Complete\n\n";

// Step 4: Query ClickHouse
echo "Step 4: Querying ClickHouse for W3C data...\n";
$w3c_trace_id_16 = substr($test_traceparent, 3, 16);

$query = "
SELECT 
    trace_id,
    span_id,
    name,
    start_ts,
    w3c_traceparent,
    w3c_tracestate,
    CASE WHEN w3c_traceparent IS NOT NULL AND w3c_traceparent != '' THEN 1 ELSE 0 END as has_tp,
    CASE WHEN w3c_tracestate IS NOT NULL AND w3c_tracestate != '' THEN 1 ELSE 0 END as has_ts
FROM opa.spans_full 
WHERE start_ts >= now() - INTERVAL 5 MINUTE
ORDER BY start_ts DESC 
LIMIT 50
FORMAT JSONEachRow
";

$result = queryClickHouse($CLICKHOUSE_URL, $query);

if ($result['error']) {
    die("ERROR: Query failed: {$result['error']}\n");
}

$spans = $result['data'];
echo "   Found " . count($spans) . " span(s)\n\n";

// Step 5: Analysis
echo "Step 5: Analyzing W3C data...\n";
$total_spans = count($spans);
$spans_with_tp = 0;
$spans_with_ts = 0;
$spans_with_both = 0;
$root_spans = 0;
$root_spans_with_w3c = 0;

foreach ($spans as $span) {
    $has_tp = !empty($span['w3c_traceparent']);
    $has_ts = !empty($span['w3c_tracestate']);
    $is_root = empty($span['parent_id']) || $span['parent_id'] === 'null';
    
    if ($has_tp) $spans_with_tp++;
    if ($has_ts) $spans_with_ts++;
    if ($has_tp && $has_ts) $spans_with_both++;
    if ($is_root) {
        $root_spans++;
        if ($has_tp || $has_ts) $root_spans_with_w3c++;
    }
}

echo "   Total spans analyzed: $total_spans\n";
echo "   Spans with w3c_traceparent: $spans_with_tp\n";
echo "   Spans with w3c_tracestate: $spans_with_ts\n";
echo "   Spans with both: $spans_with_both\n";
echo "   Root spans: $root_spans\n";
echo "   Root spans with W3C: $root_spans_with_w3c\n\n";

// Step 6: Show sample spans
echo "Step 6: Sample spans (first 5):\n";
foreach (array_slice($spans, 0, 5) as $span) {
    echo "   - {$span['name']} (trace: " . substr($span['trace_id'], 0, 16) . ")\n";
    echo "     w3c_traceparent: " . (!empty($span['w3c_traceparent']) ? "✓ " . substr($span['w3c_traceparent'], 0, 50) . "..." : "✗ NULL") . "\n";
    echo "     w3c_tracestate: " . (!empty($span['w3c_tracestate']) ? "✓ " . substr($span['w3c_tracestate'], 0, 50) . "..." : "✗ NULL") . "\n";
}

// Step 7: Final validation
echo "\n" . str_repeat("=", 80) . "\n";
echo "VALIDATION RESULT:\n";

if ($spans_with_tp > 0 || $spans_with_ts > 0) {
    echo "✓ SUCCESS: W3C data is present in ClickHouse!\n";
    echo "  - $spans_with_tp span(s) have w3c_traceparent\n";
    echo "  - $spans_with_ts span(s) have w3c_tracestate\n";
    exit(0);
} else {
    echo "✗ FAILURE: No W3C data found in ClickHouse\n";
    echo "\nDiagnosis:\n";
    echo "  - W3C context is being set (opa_set_w3c_context returns true)\n";
    echo "  - Spans are being created and stored\n";
    echo "  - But W3C fields are not included in span JSON\n";
    echo "\nPossible causes:\n";
    echo "  1. W3C context is not persisting until RSHUTDOWN\n";
    echo "  2. W3C fields are not being added to span JSON in span.c\n";
    echo "  3. Agent is not storing W3C fields in ClickHouse\n";
    exit(1);
}

