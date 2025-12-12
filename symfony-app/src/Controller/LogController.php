<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class LogController extends AbstractController
{
    #[Route('/api/logs/error', name: 'logs_error', methods: ['GET'])]
    public function testErrorLog(): JsonResponse
    {
        // Test error_log() calls - automatically instrumented by OPA
        error_log('This is a test error log message');
        error_log('Another error log with context: ' . json_encode(['key' => 'value']));

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Error log messages sent'
        ]);
    }

    #[Route('/api/logs/levels', name: 'logs_levels', methods: ['GET'])]
    public function testLogLevels(): JsonResponse
    {
        // Test different log levels
        error_log('ERROR: Critical error occurred', 0);
        error_log('WARNING: This is a warning message', 0);
        error_log('INFO: Informational message', 0);
        error_log('DEBUG: Debug information', 0);

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Multiple log levels tested'
        ]);
    }

    #[Route('/api/logs/context', name: 'logs_context', methods: ['GET'])]
    public function testLogContext(): JsonResponse
    {
        $context = [
            'user_id' => 12345,
            'action' => 'test_action',
            'timestamp' => time(),
            'data' => ['key1' => 'value1', 'key2' => 'value2']
        ];

        error_log('Log with context: ' . json_encode($context));

        return new JsonResponse([
            'status' => 'success',
            'context' => $context,
            'message' => 'Contextual log message sent'
        ]);
    }
}

