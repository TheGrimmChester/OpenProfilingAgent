<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * HTTP Methods Test Controller
 * 
 * Tests all HTTP methods (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
 * with various request and response sizes to validate APM tracking.
 * 
 * Uses composite pattern for request/response size generation.
 */
class HttpMethodsTestController extends AbstractController
{
    // Size categories for testing
    private const SIZE_TINY = 100;      // ~100 bytes
    private const SIZE_SMALL = 1024;    // 1 KB
    private const SIZE_MEDIUM = 10240;  // 10 KB
    private const SIZE_LARGE = 102400;  // 100 KB
    private const SIZE_XLARGE = 1048576; // 1 MB
    private const SIZE_XXLARGE = 5242880; // 5 MB

    /**
     * GET endpoint - tests various response sizes
     */
    #[Route('/api/test/http/get', name: 'test_http_get', methods: ['GET'])]
    public function testGet(Request $request): JsonResponse
    {
        $responseSize = (int)($request->query->get('size', self::SIZE_SMALL));
        $sizeCategory = $this->getSizeCategory($responseSize);

        $data = $this->generateResponseData('GET', $request, $responseSize, $sizeCategory);
        
        if (function_exists('opa_dump')) {
            opa_dump('HTTP GET Test', [
                'method' => 'GET',
                'response_size' => strlen(json_encode($data)),
                'size_category' => $sizeCategory
            ]);
        }

        return new JsonResponse($data, 200);
    }

    /**
     * POST endpoint - tests various request and response sizes
     */
    #[Route('/api/test/http/post', name: 'test_http_post', methods: ['POST'])]
    public function testPost(Request $request): JsonResponse
    {
        $requestBody = $request->getContent();
        $requestSize = strlen($requestBody);
        $responseSize = (int)($request->query->get('response_size', $requestSize));
        
        $requestSizeCategory = $this->getSizeCategory($requestSize);
        $responseSizeCategory = $this->getSizeCategory($responseSize);

        $data = $this->generateRequestResponseData(
            'POST',
            $request,
            $requestBody,
            $requestSize,
            $requestSizeCategory,
            $responseSize,
            $responseSizeCategory
        );

        if (function_exists('opa_dump')) {
            opa_dump('HTTP POST Test', [
                'method' => 'POST',
                'request_size' => $requestSize,
                'response_size' => strlen(json_encode($data)),
                'request_size_category' => $requestSizeCategory,
                'response_size_category' => $responseSizeCategory
            ]);
        }

        return new JsonResponse($data, 201);
    }

    /**
     * PUT endpoint - tests various request and response sizes
     */
    #[Route('/api/test/http/put', name: 'test_http_put', methods: ['PUT'])]
    public function testPut(Request $request): JsonResponse
    {
        $requestBody = $request->getContent();
        $requestSize = strlen($requestBody);
        $responseSize = (int)($request->query->get('response_size', $requestSize));
        
        $requestSizeCategory = $this->getSizeCategory($requestSize);
        $responseSizeCategory = $this->getSizeCategory($responseSize);

        $data = $this->generateRequestResponseData(
            'PUT',
            $request,
            $requestBody,
            $requestSize,
            $requestSizeCategory,
            $responseSize,
            $responseSizeCategory
        );

        if (function_exists('opa_dump')) {
            opa_dump('HTTP PUT Test', [
                'method' => 'PUT',
                'request_size' => $requestSize,
                'response_size' => strlen(json_encode($data)),
                'request_size_category' => $requestSizeCategory,
                'response_size_category' => $responseSizeCategory
            ]);
        }

        return new JsonResponse($data, 200);
    }

    /**
     * PATCH endpoint - tests various request and response sizes
     */
    #[Route('/api/test/http/patch', name: 'test_http_patch', methods: ['PATCH'])]
    public function testPatch(Request $request): JsonResponse
    {
        $requestBody = $request->getContent();
        $requestSize = strlen($requestBody);
        $responseSize = (int)($request->query->get('response_size', $requestSize));
        
        $requestSizeCategory = $this->getSizeCategory($requestSize);
        $responseSizeCategory = $this->getSizeCategory($responseSize);

        $data = $this->generateRequestResponseData(
            'PATCH',
            $request,
            $requestBody,
            $requestSize,
            $requestSizeCategory,
            $responseSize,
            $responseSizeCategory
        );

        if (function_exists('opa_dump')) {
            opa_dump('HTTP PATCH Test', [
                'method' => 'PATCH',
                'request_size' => $requestSize,
                'response_size' => strlen(json_encode($data)),
                'request_size_category' => $requestSizeCategory,
                'response_size_category' => $responseSizeCategory
            ]);
        }

        return new JsonResponse($data, 200);
    }

    /**
     * DELETE endpoint - tests various response sizes
     */
    #[Route('/api/test/http/delete', name: 'test_http_delete', methods: ['DELETE'])]
    public function testDelete(Request $request): JsonResponse
    {
        $responseSize = (int)($request->query->get('size', self::SIZE_SMALL));
        $sizeCategory = $this->getSizeCategory($responseSize);

        $data = $this->generateResponseData('DELETE', $request, $responseSize, $sizeCategory);
        
        if (function_exists('opa_dump')) {
            opa_dump('HTTP DELETE Test', [
                'method' => 'DELETE',
                'response_size' => strlen(json_encode($data)),
                'size_category' => $sizeCategory
            ]);
        }

        return new JsonResponse($data, 200);
    }

    /**
     * HEAD endpoint - returns headers only, no body
     */
    #[Route('/api/test/http/head', name: 'test_http_head', methods: ['HEAD'])]
    public function testHead(Request $request): Response
    {
        $responseSize = (int)($request->query->get('size', self::SIZE_SMALL));
        $sizeCategory = $this->getSizeCategory($responseSize);

        $response = new Response('', 200);
        $response->headers->set('X-Test-Method', 'HEAD');
        $response->headers->set('X-Requested-Size', (string)$responseSize);
        $response->headers->set('X-Size-Category', $sizeCategory);
        $response->headers->set('X-Timestamp', date('Y-m-d H:i:s'));

        if (function_exists('opa_dump')) {
            opa_dump('HTTP HEAD Test', [
                'method' => 'HEAD',
                'response_size' => 0, // HEAD has no body
                'size_category' => $sizeCategory
            ]);
        }

        return $response;
    }

    /**
     * OPTIONS endpoint - returns allowed methods
     */
    #[Route('/api/test/http/options', name: 'test_http_options', methods: ['OPTIONS'])]
    public function testOptions(Request $request): Response
    {
        $response = new Response('', 200);
        $response->headers->set('Allow', 'GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->headers->set('X-Test-Method', 'OPTIONS');
        $response->headers->set('X-Timestamp', date('Y-m-d H:i:s'));

        if (function_exists('opa_dump')) {
            opa_dump('HTTP OPTIONS Test', [
                'method' => 'OPTIONS',
                'response_size' => 0
            ]);
        }

        return $response;
    }

    /**
     * Comprehensive test endpoint - tests all methods with various sizes
     */
    #[Route('/api/test/http/comprehensive', name: 'test_http_comprehensive', methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])]
    public function testComprehensive(Request $request): JsonResponse
    {
        $method = $request->getMethod();
        $requestBody = $request->getContent();
        $requestSize = strlen($requestBody);
        
        // Test with multiple size categories
        $sizes = [
            'tiny' => self::SIZE_TINY,
            'small' => self::SIZE_SMALL,
            'medium' => self::SIZE_MEDIUM,
            'large' => self::SIZE_LARGE,
        ];

        $results = [
            'status' => 'success',
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_info' => [
                'method' => $method,
                'uri' => $request->getUri(),
                'body_size' => $requestSize,
                'query_params' => $request->query->all(),
                'headers_count' => count($request->headers->all())
            ],
            'size_tests' => []
        ];

        foreach ($sizes as $category => $size) {
            $testData = $this->generateResponseData($method, $request, $size, $category);
            $results['size_tests'][$category] = [
                'requested_size' => $size,
                'actual_size' => strlen(json_encode($testData)),
                'category' => $category
            ];
        }

        // Add current request/response data
        $responseSize = (int)($request->query->get('response_size', self::SIZE_MEDIUM));
        $data = $this->generateRequestResponseData(
            $method,
            $request,
            $requestBody,
            $requestSize,
            $this->getSizeCategory($requestSize),
            $responseSize,
            $this->getSizeCategory($responseSize)
        );

        $results['current_test'] = $data;

        if (function_exists('opa_dump')) {
            opa_dump('HTTP Comprehensive Test', [
                'method' => $method,
                'request_size' => $requestSize,
                'response_size' => strlen(json_encode($results)),
                'size_tests_count' => count($sizes)
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Size-specific test endpoints for each HTTP method
     */
    #[Route('/api/test/http/{method}/size/{size}', 
        name: 'test_http_method_size', 
        methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        requirements: ['method' => 'get|post|put|patch|delete', 'size' => '\d+'])]
    public function testMethodWithSize(string $method, int $size, Request $request): JsonResponse
    {
        $method = strtoupper($method);
        $sizeCategory = $this->getSizeCategory($size);
        $requestBody = $request->getContent();
        $requestSize = strlen($requestBody);

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $data = $this->generateRequestResponseData(
                $method,
                $request,
                $requestBody,
                $requestSize,
                $this->getSizeCategory($requestSize),
                $size,
                $sizeCategory
            );
        } else {
            $data = $this->generateResponseData($method, $request, $size, $sizeCategory);
        }

        if (function_exists('opa_dump')) {
            opa_dump("HTTP {$method} Size Test", [
                'method' => $method,
                'requested_size' => $size,
                'request_size' => $requestSize,
                'response_size' => strlen(json_encode($data)),
                'size_category' => $sizeCategory
            ]);
        }

        $statusCode = $method === 'POST' ? 201 : 200;
        return new JsonResponse($data, $statusCode);
    }

    /**
     * Generate response data for methods without request body (GET, DELETE)
     */
    private function generateResponseData(string $method, Request $request, int $targetSize, string $sizeCategory): array
    {
        $baseData = [
            'status' => 'success',
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_info' => [
                'uri' => $request->getUri(),
                'query_params' => $request->query->all(),
                'headers_count' => count($request->headers->all())
            ],
            'response_info' => [
                'requested_size' => $targetSize,
                'size_category' => $sizeCategory
            ]
        ];

        $baseJson = json_encode($baseData);
        $baseSize = strlen($baseJson);
        $paddingNeeded = max(0, $targetSize - $baseSize - 50); // -50 for JSON overhead

        if ($paddingNeeded > 0) {
            $baseData['data'] = str_repeat('x', $paddingNeeded);
        }

        return $baseData;
    }

    /**
     * Generate request/response data for methods with request body (POST, PUT, PATCH)
     */
    private function generateRequestResponseData(
        string $method,
        Request $request,
        string $requestBody,
        int $requestSize,
        string $requestSizeCategory,
        int $targetResponseSize,
        string $responseSizeCategory
    ): array {
        $baseData = [
            'status' => 'success',
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_info' => [
                'uri' => $request->getUri(),
                'body_size' => $requestSize,
                'body_size_category' => $requestSizeCategory,
                'content_type' => $request->headers->get('Content-Type'),
                'query_params' => $request->query->all(),
                'body_preview' => substr($requestBody, 0, 200)
            ],
            'response_info' => [
                'requested_size' => $targetResponseSize,
                'size_category' => $responseSizeCategory
            ]
        ];

        // Try to parse request body as JSON if possible
        if ($requestBody) {
            $decoded = json_decode($requestBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $baseData['request_info']['parsed_body'] = $decoded;
            }
        }

        $baseJson = json_encode($baseData);
        $baseSize = strlen($baseJson);
        $paddingNeeded = max(0, $targetResponseSize - $baseSize - 50);

        if ($paddingNeeded > 0) {
            $baseData['data'] = str_repeat('x', $paddingNeeded);
        }

        return $baseData;
    }

    /**
     * Get size category name based on size
     */
    private function getSizeCategory(int $size): string
    {
        if ($size <= self::SIZE_TINY) {
            return 'tiny';
        } elseif ($size <= self::SIZE_SMALL) {
            return 'small';
        } elseif ($size <= self::SIZE_MEDIUM) {
            return 'medium';
        } elseif ($size <= self::SIZE_LARGE) {
            return 'large';
        } elseif ($size <= self::SIZE_XLARGE) {
            return 'xlarge';
        } else {
            return 'xxlarge';
        }
    }
}

