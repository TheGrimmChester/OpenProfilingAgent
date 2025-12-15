<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Service Map Test Controller
 * 
 * Makes HTTP calls to various external hosts to generate service map data.
 * This creates service dependencies and external service relationships
 * that will appear in the service map visualization.
 * 
 * Uses composite pattern to organize calls by service type.
 */
class ServiceMapTestController extends AbstractController
{
    /**
     * List of external services to call for service map generation
     */
    private const EXTERNAL_SERVICES = [
        // Public APIs
        'httpbin' => [
            'base_url' => 'https://httpbin.org',
            'endpoints' => ['/get', '/post', '/json', '/status/200', '/status/404', '/delay/1'],
            'service_type' => 'api',
            'description' => 'HTTPBin - HTTP testing service'
        ],
        'jsonplaceholder' => [
            'base_url' => 'https://jsonplaceholder.typicode.com',
            'endpoints' => ['/posts/1', '/users/1', '/comments/1', '/albums/1'],
            'service_type' => 'api',
            'description' => 'JSONPlaceholder - Fake REST API'
        ],
        'reqres' => [
            'base_url' => 'https://reqres.in/api',
            'endpoints' => ['/users/1', '/users/2', '/users?page=1', '/users?page=2'],
            'service_type' => 'api',
            'description' => 'ReqRes - Sample REST API'
        ],
        // Status code testing
        'httpstat' => [
            'base_url' => 'https://httpstat.us',
            'endpoints' => ['/200', '/201', '/400', '/401', '/403', '/404', '/500', '/503'],
            'service_type' => 'api',
            'description' => 'HTTP Status - Status code testing'
        ],
        // Delay testing
        'httpdelay' => [
            'base_url' => 'https://httpbin.org',
            'endpoints' => ['/delay/0.5', '/delay/1', '/delay/2', '/delay/3'],
            'service_type' => 'api',
            'description' => 'HTTPBin Delay - Latency testing'
        ],
    ];

    /**
     * Make calls to all external services with randomness
     */
    #[Route('/api/test/service-map/all', name: 'test_service_map_all', methods: ['GET'])]
    public function testAllServices(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'service_map_all_services',
            'services' => []
        ];

        $iterations = (int)($request->query->get('iterations', 1));
        $randomize = $request->query->get('randomize', 'true') !== 'false';
        
        $services = array_keys(self::EXTERNAL_SERVICES);
        
        // Randomize service order if requested
        if ($randomize) {
            shuffle($services);
        }
        
        // Randomly select subset of services if iterations > 1
        if ($randomize && $iterations > 1) {
            $selectedCount = min(count($services), max(1, (int)($iterations / 2)));
            $services = array_slice($services, 0, $selectedCount);
        }
        
        foreach ($services as $serviceName) {
            $serviceConfig = self::EXTERNAL_SERVICES[$serviceName];
            $serviceIterations = $randomize ? rand(1, $iterations) : $iterations;
            $serviceResults = $this->callService($serviceName, $serviceConfig, $serviceIterations, $randomize);
            $results['services'][$serviceName] = $serviceResults;
        }

        if (function_exists('opa_dump')) {
            opa_dump('Service Map - All Services Test', [
                'services_called' => count($results['services']),
                'iterations' => $iterations,
                'randomized' => $randomize
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Test specific service
     */
    #[Route('/api/test/service-map/{service}', name: 'test_service_map_service', methods: ['GET'])]
    public function testService(string $service, Request $request): JsonResponse
    {
        if (!isset(self::EXTERNAL_SERVICES[$service])) {
            return new JsonResponse([
                'status' => 'error',
                'error' => "Service '{$service}' not found",
                'available_services' => array_keys(self::EXTERNAL_SERVICES)
            ], 404);
        }

        $serviceConfig = self::EXTERNAL_SERVICES[$service];
        $iterations = (int)($request->query->get('iterations', 1));

        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'service_map_single_service',
            'service' => $service,
            'service_config' => $serviceConfig,
            'calls' => $this->callService($service, $serviceConfig, $iterations)
        ];

        if (function_exists('opa_dump')) {
            opa_dump("Service Map - {$service} Test", $results);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Test with custom hosts
     */
    #[Route('/api/test/service-map/custom', name: 'test_service_map_custom', methods: ['POST'])]
    public function testCustomHosts(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $hosts = $body['hosts'] ?? [];
        $iterations = (int)($body['iterations'] ?? 1);

        if (empty($hosts)) {
            return new JsonResponse([
                'status' => 'error',
                'error' => 'No hosts provided. Provide hosts array in request body.'
            ], 400);
        }

        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'service_map_custom_hosts',
            'hosts' => []
        ];

        foreach ($hosts as $host) {
            $url = is_array($host) ? ($host['url'] ?? '') : $host;
            if (empty($url)) {
                continue;
            }

            $hostResults = $this->callCustomHost($url, $iterations);
            $results['hosts'][] = [
                'url' => $url,
                'results' => $hostResults
            ];
        }

        if (function_exists('opa_dump')) {
            opa_dump('Service Map - Custom Hosts Test', [
                'hosts_called' => count($results['hosts']),
                'iterations' => $iterations
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Test with various HTTP methods to different hosts with randomness
     */
    #[Route('/api/test/service-map/methods', name: 'test_service_map_methods', methods: ['GET'])]
    public function testHttpMethods(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'service_map_http_methods',
            'calls' => []
        ];

        $baseUrls = ['https://httpbin.org', 'https://jsonplaceholder.typicode.com', 'https://reqres.in/api'];
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $iterations = (int)($request->query->get('iterations', 1));
        $randomize = $request->query->get('randomize', 'true') !== 'false';

        // Randomize method order
        if ($randomize) {
            shuffle($methods);
        }

        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select method and base URL
            $method = $randomize ? $methods[array_rand($methods)] : $methods[$i % count($methods)];
            $baseUrl = $randomize ? $baseUrls[array_rand($baseUrls)] : $baseUrls[0];
            
            // Random endpoint selection
            $endpoint = $this->getRandomEndpoint($baseUrl, $method);
            $url = $endpoint;
            
            // Random payload size
            $payload = $randomize ? [
                'test' => 'service_map',
                'method' => $method,
                'iteration' => $i,
                'random_data' => str_repeat('x', rand(10, 1000)),
                'timestamp' => time()
            ] : [
                'test' => 'service_map',
                'method' => $method,
                'iteration' => $i
            ];
            
            $callResult = $this->makeHttpCall($method, $url, $payload);

            $results['calls'][] = [
                'method' => $method,
                'url' => $url,
                'iteration' => $i,
                'status_code' => $callResult['status_code'],
                'success' => $callResult['success'],
                'duration_ms' => $callResult['duration_ms']
            ];
            
            // Random delay between calls (0 to 3 seconds)
            if ($randomize && $i < $iterations - 1) {
                $sleepSeconds = rand(0, 3);
                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }
            }
        }

        if (function_exists('opa_dump')) {
            opa_dump('Service Map - HTTP Methods Test', [
                'methods_tested' => $methods,
                'iterations' => $iterations,
                'randomized' => $randomize
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Test with various status codes with randomness
     */
    #[Route('/api/test/service-map/status-codes', name: 'test_service_map_status_codes', methods: ['GET'])]
    public function testStatusCodes(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'service_map_status_codes',
            'calls' => []
        ];

        $baseUrl = 'https://httpstat.us';
        $statusCodes = [200, 201, 301, 400, 401, 403, 404, 500, 502, 503];
        $iterations = (int)($request->query->get('iterations', 1));
        $randomize = $request->query->get('randomize', 'true') !== 'false';

        // Randomize status code order
        if ($randomize) {
            shuffle($statusCodes);
        }

        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select status code
            $code = $randomize ? $statusCodes[array_rand($statusCodes)] : $statusCodes[$i % count($statusCodes)];
            $url = "{$baseUrl}/{$code}";
            
            $callResult = $this->makeHttpCall('GET', $url);

            $results['calls'][] = [
                'status_code' => $code,
                'url' => $url,
                'iteration' => $i,
                'response_code' => $callResult['status_code'],
                'success' => $callResult['success'],
                'duration_ms' => $callResult['duration_ms']
            ];
            
            // Random delay between calls (0 to 3 seconds)
            if ($randomize && $i < $iterations - 1) {
                $sleepSeconds = rand(0, 3);
                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }
            }
        }

        if (function_exists('opa_dump')) {
            opa_dump('Service Map - Status Codes Test', [
                'status_codes_tested' => $statusCodes,
                'iterations' => $iterations,
                'randomized' => $randomize
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Test with various latencies with randomness
     */
    #[Route('/api/test/service-map/latencies', name: 'test_service_map_latencies', methods: ['GET'])]
    public function testLatencies(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'service_map_latencies',
            'calls' => []
        ];

        $baseUrl = 'https://httpbin.org';
        $delays = [0.1, 0.5, 1, 2, 3, 5];
        $iterations = (int)($request->query->get('iterations', 1));
        $randomize = $request->query->get('randomize', 'true') !== 'false';

        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select delay or use random delay
            if ($randomize) {
                $delay = $delays[array_rand($delays)];
                // Sometimes use random delay between 0.1 and 5 seconds
                if (rand(1, 3) === 1) {
                    $delay = round(rand(1, 50) / 10, 1); // 0.1 to 5.0
                }
            } else {
                $delay = $delays[$i % count($delays)];
            }
            
            $url = "{$baseUrl}/delay/{$delay}";
            
            $callResult = $this->makeHttpCall('GET', $url);

            $results['calls'][] = [
                'delay_seconds' => $delay,
                'url' => $url,
                'iteration' => $i,
                'status_code' => $callResult['status_code'],
                'success' => $callResult['success'],
                'duration_ms' => $callResult['duration_ms']
            ];
            
            // Random delay between calls (0 to 3 seconds)
            if ($randomize && $i < $iterations - 1) {
                $sleepSeconds = rand(0, 3);
                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }
            }
        }

        if (function_exists('opa_dump')) {
            opa_dump('Service Map - Latencies Test', [
                'delays_tested' => $delays,
                'iterations' => $iterations,
                'randomized' => $randomize
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Comprehensive test - all variations
     */
    #[Route('/api/test/service-map/comprehensive', name: 'test_service_map_comprehensive', methods: ['GET'])]
    public function testComprehensive(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'service_map_comprehensive',
            'sections' => []
        ];

        $iterations = (int)($request->query->get('iterations', 1));

        // Test all services
        $allServicesResults = $this->testAllServices($request);
        $results['sections']['all_services'] = json_decode($allServicesResults->getContent(), true);

        // Test HTTP methods
        $methodsResults = $this->testHttpMethods($request);
        $results['sections']['http_methods'] = json_decode($methodsResults->getContent(), true);

        // Test status codes
        $statusResults = $this->testStatusCodes($request);
        $results['sections']['status_codes'] = json_decode($statusResults->getContent(), true);

        // Test latencies
        $latencyResults = $this->testLatencies($request);
        $results['sections']['latencies'] = json_decode($latencyResults->getContent(), true);

        if (function_exists('opa_dump')) {
            opa_dump('Service Map - Comprehensive Test', [
                'iterations' => $iterations,
                'sections' => array_keys($results['sections'])
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Call a service with all its endpoints with randomness
     */
    private function callService(string $serviceName, array $serviceConfig, int $iterations, bool $randomize = true): array
    {
        $results = [
            'service_name' => $serviceName,
            'base_url' => $serviceConfig['base_url'],
            'service_type' => $serviceConfig['service_type'],
            'description' => $serviceConfig['description'],
            'calls' => []
        ];

        $endpoints = $serviceConfig['endpoints'] ?? [];
        
        // Randomize endpoint order
        if ($randomize) {
            shuffle($endpoints);
        }

        // Randomly select subset of endpoints if iterations > 1
        if ($randomize && $iterations > 1 && count($endpoints) > 1) {
            $selectedCount = min(count($endpoints), max(1, (int)($iterations / 2)));
            $endpoints = array_slice($endpoints, 0, $selectedCount);
        }

        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select endpoint
            $endpoint = $randomize ? $endpoints[array_rand($endpoints)] : $endpoints[$i % count($endpoints)];
            $url = $serviceConfig['base_url'] . $endpoint;
            
            $callResult = $this->makeHttpCall('GET', $url);

            $results['calls'][] = [
                'endpoint' => $endpoint,
                'url' => $url,
                'iteration' => $i,
                'status_code' => $callResult['status_code'],
                'success' => $callResult['success'],
                'duration_ms' => $callResult['duration_ms'],
                'error' => $callResult['error'] ?? null
            ];
            
            // Random delay between calls (0 to 3 seconds)
            if ($randomize && $i < $iterations - 1) {
                $sleepSeconds = rand(0, 3);
                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }
            }
        }

        return $results;
    }

    /**
     * Get random endpoint based on base URL and method
     */
    private function getRandomEndpoint(string $baseUrl, string $method): string
    {
        if (strpos($baseUrl, 'httpbin.org') !== false) {
            $endpoints = ['/get', '/post', '/put', '/patch', '/delete', '/json', '/status/200'];
            return $baseUrl . $endpoints[array_rand($endpoints)];
        } elseif (strpos($baseUrl, 'jsonplaceholder.typicode.com') !== false) {
            $endpoints = ['/posts/' . rand(1, 100), '/users/' . rand(1, 10), '/comments/' . rand(1, 500)];
            return $baseUrl . $endpoints[array_rand($endpoints)];
        } elseif (strpos($baseUrl, 'reqres.in') !== false) {
            $endpoints = ['/users/' . rand(1, 12), '/users?page=' . rand(1, 2)];
            return $baseUrl . $endpoints[array_rand($endpoints)];
        }
        
        // Default
        return $baseUrl . '/';
    }

    /**
     * Call a custom host
     */
    private function callCustomHost(string $url, int $iterations): array
    {
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $callResult = $this->makeHttpCall('GET', $url);

            $results[] = [
                'url' => $url,
                'iteration' => $i,
                'status_code' => $callResult['status_code'],
                'success' => $callResult['success'],
                'duration_ms' => $callResult['duration_ms'],
                'error' => $callResult['error'] ?? null
            ];
        }

        return $results;
    }

    /**
     * Make HTTP call using cURL
     */
    private function makeHttpCall(string $method, string $url, array $data = []): array
    {
        $startTime = microtime(true);
        $result = [
            'status_code' => 0,
            'success' => false,
            'duration_ms' => 0,
            'error' => null
        ];

        if (!function_exists('curl_init')) {
            $result['error'] = 'cURL extension not available';
            return $result;
        }

        try {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_CUSTOMREQUEST => $method,
            ]);

            // Add POST data if method is POST/PUT/PATCH
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json'
                ]);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);

            $duration = (microtime(true) - $startTime) * 1000;

            $result['status_code'] = $httpCode ?: 0;
            $result['success'] = $httpCode >= 200 && $httpCode < 300;
            $result['duration_ms'] = round($duration, 2);
            
            if ($error) {
                $result['error'] = $error;
            }
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        }

        return $result;
    }
}

