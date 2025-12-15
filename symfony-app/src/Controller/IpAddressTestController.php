<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * IP Address Test Controller
 * 
 * Tests IP address capture with proxy headers (X-Forwarded-For, X-Real-IP, REMOTE_ADDR)
 * This verifies that the OPA extension correctly extracts real client IP addresses
 * behind proxies.
 */
class IpAddressTestController extends AbstractController
{
    #[Route('/api/test/ip-address', name: 'test_ip_address', methods: ['GET', 'POST'])]
    public function testIpAddress(Request $request): JsonResponse
    {
        // Collect all IP-related information from $_SERVER
        $ipInfo = [
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
            'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            'HTTP_X_REAL_IP' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
            'HTTP_X_FORWARDED' => $_SERVER['HTTP_X_FORWARDED'] ?? null,
            'HTTP_CLIENT_IP' => $_SERVER['HTTP_CLIENT_IP'] ?? null,
        ];

        // Simulate the same logic as the extension to show expected IP
        $expectedRealIp = $this->getRealClientIp();

        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'IP Address Test - Check ClickHouse tags.http_request.ip field',
            'ip_info' => $ipInfo,
            'expected_real_ip' => $expectedRealIp,
            'request_info' => [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'path' => $request->getPathInfo(),
            ],
            'instructions' => [
                '1. Query ClickHouse to verify the IP is stored in tags field',
                '2. The IP should be in tags JSON: tags->http_request->ip',
                '3. When behind a proxy, the IP should be from X-Forwarded-For or X-Real-IP',
                '4. When direct connection, the IP should be from REMOTE_ADDR',
                'Query example:',
                '   SELECT JSONExtractString(tags, \'http_request\', \'ip\') as captured_ip, tags',
                '   FROM opa.spans_full',
                '   WHERE service = \'symfony-php\'',
                '   ORDER BY created_at DESC LIMIT 10'
            ]
        ];

        // Use opa_dump if available
        if (function_exists('opa_dump')) {
            opa_dump('IP Address Test', [
                'expected_real_ip' => $expectedRealIp,
                'remote_addr' => $ipInfo['REMOTE_ADDR'],
                'x_forwarded_for' => $ipInfo['HTTP_X_FORWARDED_FOR'],
                'x_real_ip' => $ipInfo['HTTP_X_REAL_IP'],
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Get real client IP (matching the extension logic)
     * Priority: HTTP_X_FORWARDED_FOR (first IP) > HTTP_X_REAL_IP > REMOTE_ADDR
     */
    private function getRealClientIp(): ?string
    {
        // Try HTTP_X_FORWARDED_FOR first (contains original client IP when behind proxy)
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $xff = $_SERVER['HTTP_X_FORWARDED_FOR'];
            // Extract first IP (before first comma)
            $ips = explode(',', $xff);
            $ip = trim($ips[0]);
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // Try HTTP_X_REAL_IP (set by nginx proxy)
        if (isset($_SERVER['HTTP_X_REAL_IP']) && !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // Fallback to REMOTE_ADDR (direct connection or proxy IP if no headers)
        if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = trim($_SERVER['REMOTE_ADDR']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }
}

