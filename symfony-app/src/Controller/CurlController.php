<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CurlController extends AbstractController
{
    #[Route('/api/curl/simple', name: 'curl_simple', methods: ['GET'])]
    public function testSimpleCurl(): JsonResponse
    {
        // Test simple cURL request - automatically instrumented by OPA
        $ch = curl_init('https://httpbin.org/get');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new JsonResponse([
            'status' => 'success',
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'message' => 'Simple cURL request completed'
        ]);
    }

    #[Route('/api/curl/multiple', name: 'curl_multiple', methods: ['GET'])]
    public function testMultipleCurl(): JsonResponse
    {
        $results = [];

        // Multiple cURL requests
        $urls = [
            'https://httpbin.org/status/200',
            'https://httpbin.org/status/404',
            'https://httpbin.org/status/500',
        ];

        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $results[] = [
                'url' => $url,
                'http_code' => $httpCode
            ];
        }

        return new JsonResponse([
            'status' => 'success',
            'results' => $results,
            'message' => 'Multiple cURL requests completed'
        ]);
    }

    #[Route('/api/curl/post', name: 'curl_post', methods: ['GET'])]
    public function testPostCurl(): JsonResponse
    {
        $data = json_encode(['name' => 'Test User', 'email' => 'test@example.com']);

        $ch = curl_init('https://httpbin.org/post');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new JsonResponse([
            'status' => 'success',
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'message' => 'POST cURL request completed'
        ]);
    }

    #[Route('/api/curl/delayed', name: 'curl_delayed', methods: ['GET'])]
    public function testDelayedCurl(): JsonResponse
    {
        // Test delayed response
        $ch = curl_init('https://httpbin.org/delay/2');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $start = microtime(true);
        curl_exec($ch);
        $duration = microtime(true) - $start;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new JsonResponse([
            'status' => 'success',
            'http_code' => $httpCode,
            'duration' => round($duration, 2),
            'message' => 'Delayed cURL request completed'
        ]);
    }
}

