<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DumpTestController extends AbstractController
{
    #[Route('/api/dump-test', name: 'dump_test', methods: ['GET'])]
    public function dumpTest(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];

        // Test 1: opa_dump() function availability
        $results['tests']['opa_dump_available'] = function_exists('opa_dump');
        
        if (function_exists('opa_dump')) {
            // Test 2: Dump various data types
            $testData = [
                'string' => 'Hello from OPA dump!',
                'number' => 42,
                'array' => ['key1' => 'value1', 'key2' => 'value2'],
                'nested' => [
                    'level1' => [
                        'level2' => 'deep value'
                    ]
                ],
                'boolean' => true,
                'null' => null
            ];
            
            // Dump the test data
            opa_dump('Dump Test Data', $testData);
            opa_dump('Request Info', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'headers' => $request->headers->all()
            ]);
            
            $results['tests']['opa_dump'] = 'Data dumped successfully';
        } else {
            $results['tests']['opa_dump'] = 'opa_dump() function not available';
        }

        // Test 3: cURL call (commented out due to extension segfault issue)
        // TODO: Re-enable once cURL hook segfault is fixed
        try {
            $curlResults = $this->testCurl();
            $results['tests']['curl'] = $curlResults;
        } catch (\Exception $e) {
            $results['tests']['curl'] = [
                'status' => 'error',
                'error' => 'cURL test failed: ' . $e->getMessage()
            ];
        }

        // Test 4: Multiple cURL calls (commented out due to extension segfault issue)
        // TODO: Re-enable once cURL hook segfault is fixed
        try {
            $multiCurlResults = $this->testMultipleCurl();
            $results['tests']['multi_curl'] = $multiCurlResults;
        } catch (\Exception $e) {
            $results['tests']['multi_curl'] = [
                'status' => 'error',
                'error' => 'Multi-cURL test failed: ' . $e->getMessage()
            ];
        }

        // Dump final results
        if (function_exists('opa_dump')) {
            opa_dump('Final Test Results', $results);
        }

        return new JsonResponse($results, 200);
    }

    private function testCurl(): array
    {
        $result = [
            'status' => 'pending',
            'url' => 'https://httpbin.org/get',
            'response_code' => null,
            'error' => null
        ];

        if (!function_exists('curl_init')) {
            $result['status'] = 'error';
            $result['error'] = 'cURL extension not available';
            return $result;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://httpbin.org/get?test=opa');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            $result['status'] = 'error';
            $result['error'] = $error;
        } else {
            $result['status'] = 'success';
            $result['response_code'] = $httpCode;
            $result['response_size'] = strlen($response);
            $result['response_preview'] = substr($response, 0, 200);
        }

        // Dump cURL result
        if (function_exists('opa_dump')) {
            opa_dump('cURL Test Result', $result);
        }

        return $result;
    }

    private function testMultipleCurl(): array
    {
        $results = [
            'status' => 'pending',
            'requests' => []
        ];

        if (!function_exists('curl_init')) {
            $results['status'] = 'error';
            $results['error'] = 'cURL extension not available';
            return $results;
        }

        $urls = [
            'https://httpbin.org/get?test=1',
            'https://httpbin.org/get?test=2',
            'https://httpbin.org/get?test=3'
        ];

        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($urls as $index => $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[$index] = $ch;
        }

        // Execute all requests
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        // Get results
        foreach ($handles as $index => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            $results['requests'][] = [
                'url' => $urls[$index],
                'status_code' => $httpCode,
                'response_size' => strlen($response),
                'error' => $error ?: null
            ];

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        $results['status'] = 'success';
        $results['total_requests'] = count($results['requests']);

        // Dump multi-curl results
        if (function_exists('opa_dump')) {
            opa_dump('Multiple cURL Test Results', $results);
        }

        return $results;
    }
}

