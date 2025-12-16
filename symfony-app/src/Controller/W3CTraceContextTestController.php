<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * W3C Trace Context Test Controller
 * 
 * Validates W3C Trace Context support:
 * - Parses incoming traceparent/tracestate headers
 * - Tests outgoing header injection via cURL
 * - Queries ClickHouse to verify data storage
 */
class W3CTraceContextTestController extends AbstractController
{
    private const CLICKHOUSE_URL = 'http://clickhouse:8123';
    private const AGENT_URL = 'http://agent:8081';

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * Test endpoint to verify listener is called
     */
    #[Route('/api/test/w3c/listener-test', name: 'test_w3c_listener', methods: ['GET'])]
    public function testListener(Request $request): JsonResponse
    {
        $traceparent = $request->headers->get('traceparent');
        $tracestate = $request->headers->get('tracestate');
        
        $result = [
            'listener_called' => false,
            'function_exists' => function_exists('opa_set_w3c_context'),
            'traceparent' => $traceparent,
            'tracestate' => $tracestate,
            'manual_call_result' => null,
        ];
        
        if ($traceparent && function_exists('opa_set_w3c_context')) {
            $result['manual_call_result'] = opa_set_w3c_context($traceparent, $tracestate);
            $result['listener_called'] = true;
        }
        
        return new JsonResponse($result);
    }

    /**
     * Main validation endpoint - accepts W3C headers and validates the flow
     */
    #[Route('/api/test/w3c/validate', name: 'test_w3c_validate', methods: ['GET', 'POST'])]
    public function validateW3C(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'w3c_trace_context_validation',
            'incoming_headers' => [],
            'outgoing_test' => [],
            'clickhouse_verification' => [],
            'validation_results' => []
        ];

        // 1. Extract and validate incoming W3C headers
        $traceparent = $request->headers->get('traceparent');
        $tracestate = $request->headers->get('tracestate');

        $results['incoming_headers'] = [
            'traceparent' => $traceparent,
            'tracestate' => $tracestate,
            'traceparent_valid' => $this->validateTraceparent($traceparent),
            'tracestate_valid' => $tracestate !== null
        ];

        if ($traceparent) {
            $parsed = $this->parseTraceparent($traceparent);
            $results['incoming_headers']['parsed'] = $parsed;
        }

        // 2. Test outgoing W3C header injection
        $outgoingTest = $this->testOutgoingHeaders();
        $results['outgoing_test'] = $outgoingTest;

        // 3. Wait a bit for data to be processed
        sleep(2);

        // 4. Query ClickHouse to verify data
        if ($traceparent && isset($parsed['trace_id'])) {
            $clickhouseResults = $this->queryClickHouse($parsed['trace_id']);
            $results['clickhouse_verification'] = $clickhouseResults;
        } else {
            // Query recent spans to see if W3C data is present
            $recentSpans = $this->queryRecentSpans();
            $results['clickhouse_verification'] = [
                'method' => 'recent_spans',
                'results' => $recentSpans
            ];
        }

        // 5. Overall validation
        $results['validation_results'] = [
            'incoming_parsed' => $traceparent !== null && $results['incoming_headers']['traceparent_valid'],
            'outgoing_injected' => $outgoingTest['headers_injected'] ?? false,
            'clickhouse_data_found' => !empty($results['clickhouse_verification']['spans'] ?? []),
            'w3c_fields_present' => $this->checkW3CFieldsInResults($results['clickhouse_verification'])
        ];

        $results['status'] = $results['validation_results']['incoming_parsed'] 
            && $results['validation_results']['outgoing_injected']
            && $results['validation_results']['clickhouse_data_found']
            ? 'success' : 'partial';

        return new JsonResponse($results, 200);
    }

    /**
     * Test endpoint that accepts W3C headers and makes an outgoing request
     */
    #[Route('/api/test/w3c/outgoing', name: 'test_w3c_outgoing', methods: ['GET'])]
    public function testOutgoing(Request $request): JsonResponse
    {
        // Make an outgoing HTTP request to test header injection
        $targetUrl = $request->query->get('target', 'http://httpbin.org/headers');
        
        try {
            $response = $this->httpClient->request('GET', $targetUrl, [
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            $headers = $response->getHeaders();

            // Try to extract traceparent from response (if httpbin echoes it)
            $traceparentInResponse = null;
            if (strpos($content, 'traceparent') !== false) {
                // httpbin returns headers in JSON, try to extract
                $data = json_decode($content, true);
                if (isset($data['headers']['Traceparent'])) {
                    $traceparentInResponse = $data['headers']['Traceparent'];
                } elseif (isset($data['headers']['traceparent'])) {
                    $traceparentInResponse = $data['headers']['traceparent'];
                }
            }

            return new JsonResponse([
                'status' => 'success',
                'target_url' => $targetUrl,
                'response_status' => $statusCode,
                'traceparent_in_response' => $traceparentInResponse,
                'headers_received' => $headers,
                'response_preview' => substr($content, 0, 500)
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'target_url' => $targetUrl
            ], 500);
        }
    }

    /**
     * Query ClickHouse for spans with specific trace ID
     */
    #[Route('/api/test/w3c/clickhouse/{traceId}', name: 'test_w3c_clickhouse', methods: ['GET'])]
    public function queryClickHouseByTraceId(string $traceId): JsonResponse
    {
        $results = $this->queryClickHouse($traceId);
        return new JsonResponse($results, 200);
    }

    /**
     * Validate traceparent header format
     */
    private function validateTraceparent(?string $traceparent): bool
    {
        if (!$traceparent) {
            return false;
        }

        // Format: 00-<32-hex>-<16-hex>-<2-hex>
        // Total length: 55
        if (strlen($traceparent) !== 55) {
            return false;
        }

        // Check separators
        if ($traceparent[2] !== '-' || $traceparent[35] !== '-' || $traceparent[52] !== '-') {
            return false;
        }

        // Check version (must be 00)
        if (substr($traceparent, 0, 2) !== '00') {
            return false;
        }

        // Check hex format
        $traceId = substr($traceparent, 3, 32);
        $parentId = substr($traceparent, 36, 16);
        $flags = substr($traceparent, 53, 2);

        return ctype_xdigit($traceId) && ctype_xdigit($parentId) && ctype_xdigit($flags);
    }

    /**
     * Parse traceparent header
     */
    private function parseTraceparent(string $traceparent): array
    {
        return [
            'version' => substr($traceparent, 0, 2),
            'trace_id' => substr($traceparent, 3, 32),
            'parent_id' => substr($traceparent, 36, 16),
            'flags' => substr($traceparent, 53, 2),
            'sampled' => (hexdec(substr($traceparent, 53, 2)) & 0x01) === 0x01
        ];
    }

    /**
     * Test outgoing header injection
     */
    private function testOutgoingHeaders(): array
    {
        $result = [
            'method' => 'curl',
            'headers_injected' => false,
            'error' => null
        ];

        try {
            // Use cURL to make a request (extension will inject headers)
            $ch = curl_init('http://httpbin.org/headers');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HEADER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $result['error'] = $error;
            } else {
                $result['http_code'] = $httpCode;
                $result['response_received'] = true;
                
                // Check if traceparent is in response (httpbin echoes headers)
                if (strpos($response, 'traceparent') !== false || strpos($response, 'Traceparent') !== false) {
                    $result['headers_injected'] = true;
                    $data = json_decode($response, true);
                    if (isset($data['headers']['Traceparent'])) {
                        $result['traceparent_found'] = $data['headers']['Traceparent'];
                    } elseif (isset($data['headers']['traceparent'])) {
                        $result['traceparent_found'] = $data['headers']['traceparent'];
                    }
                }
            }
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Query ClickHouse for spans with W3C data
     */
    private function queryClickHouse(string $traceId): array
    {
        $results = [
            'trace_id' => $traceId,
            'spans' => [],
            'w3c_fields_found' => false,
            'error' => null
        ];

        try {
            // Convert 32-hex trace_id to 16-hex (take first 16 chars)
            $shortTraceId = substr($traceId, 0, 16);

            // Query ClickHouse for spans with this trace_id
            $query = sprintf(
                "SELECT trace_id, span_id, parent_id, service, name, start_ts, w3c_traceparent, w3c_tracestate " .
                "FROM opa.spans_full " .
                "WHERE trace_id = '%s' " .
                "ORDER BY start_ts DESC " .
                "LIMIT 10 " .
                "FORMAT JSONEachRow",
                addslashes($shortTraceId)
            );

            $url = self::CLICKHOUSE_URL . '/?query=' . urlencode($query);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $results['error'] = $error;
            } elseif ($httpCode !== 200) {
                $results['error'] = "HTTP $httpCode: " . substr($response, 0, 200);
            } else {
                // Parse JSONEachRow format
                $lines = explode("\n", trim($response));
                foreach ($lines as $line) {
                    if (empty($line)) continue;
                    $span = json_decode($line, true);
                    if ($span) {
                        $results['spans'][] = $span;
                        if (!empty($span['w3c_traceparent']) || !empty($span['w3c_tracestate'])) {
                            $results['w3c_fields_found'] = true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Query recent spans to check for W3C fields
     */
    private function queryRecentSpans(): array
    {
        $results = [
            'spans' => [],
            'w3c_fields_found' => false,
            'error' => null
        ];

        try {
            $query = sprintf(
                "SELECT trace_id, span_id, parent_id, service, name, start_ts, w3c_traceparent, w3c_tracestate " .
                "FROM opa.spans_full " .
                "WHERE start_ts >= now() - INTERVAL 5 MINUTE " .
                "ORDER BY start_ts DESC " .
                "LIMIT 20 " .
                "FORMAT JSONEachRow"
            );

            $url = self::CLICKHOUSE_URL . '/?query=' . urlencode($query);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $results['error'] = $error;
            } elseif ($httpCode !== 200) {
                $results['error'] = "HTTP $httpCode: " . substr($response, 0, 200);
            } else {
                $lines = explode("\n", trim($response));
                foreach ($lines as $line) {
                    if (empty($line)) continue;
                    $span = json_decode($line, true);
                    if ($span) {
                        $results['spans'][] = $span;
                        if (!empty($span['w3c_traceparent']) || !empty($span['w3c_tracestate'])) {
                            $results['w3c_fields_found'] = true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Check if W3C fields are present in ClickHouse results
     */
    private function checkW3CFieldsInResults(array $verification): bool
    {
        if (isset($verification['w3c_fields_found'])) {
            return $verification['w3c_fields_found'];
        }

        if (isset($verification['results']['w3c_fields_found'])) {
            return $verification['results']['w3c_fields_found'];
        }

        if (isset($verification['spans'])) {
            foreach ($verification['spans'] as $span) {
                if (!empty($span['w3c_traceparent']) || !empty($span['w3c_tracestate'])) {
                    return true;
                }
            }
        }

        return false;
    }
}

