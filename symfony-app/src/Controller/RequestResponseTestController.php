<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RequestResponseTestController extends AbstractController
{
    #[Route('/api/test/request-response', name: 'test_request_response', methods: ['GET', 'POST', 'PUT', 'PATCH'])]
    public function testRequestResponse(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'request_info' => [],
            'response_info' => [],
            'validation' => []
        ];

        // Capture request information
        $results['request_info'] = [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'path' => $request->getPathInfo(),
            'query_string' => $request->getQueryString(),
            'content_type' => $request->headers->get('Content-Type'),
            'content_length' => $request->headers->get('Content-Length'),
            'has_body' => $request->getContent() !== '',
            'body_size' => strlen($request->getContent()),
            'query_params' => $request->query->all(),
            'headers' => []
        ];

        // Capture request headers (limited to avoid too much data)
        foreach ($request->headers->all() as $name => $values) {
            if (strlen($name) < 50) { // Only reasonable header names
                $results['request_info']['headers'][$name] = $values[0] ?? '';
            }
        }

        // Capture request body preview (first 200 chars)
        $bodyContent = $request->getContent();
        if ($bodyContent) {
            $results['request_info']['body_preview'] = substr($bodyContent, 0, 200);
            $results['request_info']['body_full_size'] = strlen($bodyContent);
        }

        // Test different response sizes based on query parameter
        $responseSize = (int)($request->query->get('response_size', 100));
        $includeContent = $request->query->get('include_content', 'false') === 'true';
        
        // Generate response data
        $responseData = [
            'message' => 'Request/Response size test',
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getUri(),
            'request_body_size' => strlen($bodyContent),
            'response_size_requested' => $responseSize,
            'test_data' => str_repeat('x', max(0, $responseSize - 200)) // Generate data to reach target size
        ];

        // Add validation info
        $results['validation'] = [
            'request_size_expected' => [
                'body' => strlen($bodyContent),
                'query' => strlen($request->getQueryString() ?? ''),
                'headers' => 'estimated',
                'files' => 0
            ],
            'response_size_expected' => [
                'body' => strlen(json_encode($responseData)),
                'headers' => 'estimated'
            ],
            'notes' => [
                'Check OPA agent logs for actual request_size and response_size values',
                'Request size breakdown should show body/query/files/headers',
                'Response size breakdown should show body/headers',
                'Response content should be captured if < 10KB and output buffering is active'
            ]
        ];

        $results['response_info'] = [
            'status_code' => 200,
            'content_type' => 'application/json',
            'body_size' => strlen(json_encode($responseData)),
            'include_content_in_response' => $includeContent
        ];

        // If requested, include the actual response content in the JSON
        if ($includeContent) {
            $results['response_info']['body_content'] = $responseData;
        }

        // Use opa_dump if available to send debug info
        if (function_exists('opa_dump')) {
            opa_dump('Request/Response Test', [
                'request_method' => $request->getMethod(),
                'request_body_size' => strlen($bodyContent),
                'response_size' => strlen(json_encode($responseData)),
                'response_size_requested' => $responseSize
            ]);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/request-size', name: 'test_request_size', methods: ['POST', 'PUT', 'PATCH'])]
    public function testRequestSize(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $bodySize = strlen($body);
        
        $results = [
            'status' => 'success',
            'request_method' => $request->getMethod(),
            'body_size' => $bodySize,
            'content_type' => $request->headers->get('Content-Type'),
            'content_length_header' => $request->headers->get('Content-Length'),
            'query_string' => $request->getQueryString(),
            'query_string_size' => strlen($request->getQueryString() ?? ''),
            'headers_count' => count($request->headers->all()),
            'body_preview' => substr($body, 0, 100),
            'validation' => [
                'expected_request_size_components' => [
                    'body' => $bodySize,
                    'query' => strlen($request->getQueryString() ?? ''),
                    'headers' => 'estimated from HTTP_* headers',
                    'files' => 0
                ],
                'note' => 'Check OPA agent for actual request_size and request_size_breakdown in http_request JSON'
            ]
        ];

        if (function_exists('opa_dump')) {
            opa_dump('Request Size Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/response-size/{size}', name: 'test_response_size', methods: ['GET'], requirements: ['size' => '\d+'])]
    public function testResponseSize(int $size = 100): JsonResponse
    {
        // Generate response of specified size
        $baseData = [
            'message' => 'Response size test',
            'requested_size' => $size,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $baseJson = json_encode($baseData);
        $baseSize = strlen($baseJson);
        $paddingNeeded = max(0, $size - $baseSize - 50); // -50 for JSON overhead
        
        $data = $baseData;
        if ($paddingNeeded > 0) {
            $data['padding'] = str_repeat('x', $paddingNeeded);
        }
        
        $actualSize = strlen(json_encode($data));
        
        $results = [
            'status' => 'success',
            'requested_size' => $size,
            'actual_body_size' => $actualSize,
            'validation' => [
                'expected_response_size_components' => [
                    'body' => $actualSize,
                    'headers' => 'estimated from response headers'
                ],
                'note' => 'Check OPA agent for actual response_size and response_size_breakdown in http_response JSON. Response content should be captured if < 10KB and output buffering is active.'
            ]
        ];

        if (function_exists('opa_dump')) {
            opa_dump('Response Size Test', [
                'requested_size' => $size,
                'actual_body_size' => $actualSize
            ]);
        }

        return new JsonResponse($data, 200);
    }

    #[Route('/api/test/full-request-response', name: 'test_full_request_response', methods: ['POST'])]
    public function testFullRequestResponse(Request $request): JsonResponse
    {
        $body = $request->getContent();
        
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'request' => [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'body_size' => strlen($body),
                'query_string' => $request->getQueryString(),
                'content_type' => $request->headers->get('Content-Type'),
                'headers' => array_slice($request->headers->all(), 0, 10) // Limit headers
            ],
            'response' => [
                'status_code' => 200,
                'body_size' => 0, // Will be calculated after JSON encoding
                'content_type' => 'application/json'
            ],
            'validation_instructions' => [
                '1. Check OPA agent logs for http_request JSON - should contain:',
                '   - request_size (always present, even if 0)',
                '   - request_size_breakdown with body/query/files/headers',
                '2. Check OPA agent logs for http_response JSON - should contain:',
                '   - response_size (always present, even if 0)',
                '   - response_size_breakdown with body/headers',
                '   - body (if response < 10KB and output buffering active)',
                '3. Verify sizes match expected values'
            ]
        ];

        // Calculate actual response size
        $responseJson = json_encode($results);
        $results['response']['body_size'] = strlen($responseJson);

        if (function_exists('opa_dump')) {
            opa_dump('Full Request/Response Test', [
                'request_body_size' => strlen($body),
                'response_body_size' => strlen($responseJson)
            ]);
        }

        return new JsonResponse($results, 200);
    }
}

