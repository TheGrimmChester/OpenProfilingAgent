<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ErrorController extends AbstractController
{
    #[Route('/api/errors/exception', name: 'errors_exception', methods: ['GET'])]
    public function testException(): JsonResponse
    {
        try {
            throw new \RuntimeException('This is a test exception for OPA error tracking');
        } catch (\Throwable $e) {
            // OPA automatically tracks exceptions
            if (function_exists('opa_track_error')) {
                opa_track_error($e);
            }
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Exception thrown and tracked',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/errors/fatal', name: 'errors_fatal', methods: ['GET'])]
    public function testFatalError(): JsonResponse
    {
        // Trigger a fatal error (division by zero)
        $result = 1 / 0;
        return new JsonResponse(['result' => $result]);
    }

    #[Route('/api/errors/warning', name: 'errors_warning', methods: ['GET'])]
    public function testWarning(): JsonResponse
    {
        // Trigger a warning
        $file = @file_get_contents('/nonexistent/file.txt');
        return new JsonResponse([
            'status' => 'success',
            'message' => 'Warning triggered (file not found)'
        ]);
    }

    #[Route('/api/errors/notice', name: 'errors_notice', methods: ['GET'])]
    public function testNotice(): JsonResponse
    {
        // Trigger a notice
        $undefined = $undefinedVariable;
        return new JsonResponse([
            'status' => 'success',
            'message' => 'Notice triggered'
        ]);
    }

    #[Route('/api/errors/custom', name: 'errors_custom', methods: ['GET'])]
    public function testCustomError(): JsonResponse
    {
        try {
            // Use a standard error class instead of nested class declaration
            throw new \Error('Custom error for testing');
        } catch (\Throwable $e) {
            if (function_exists('opa_track_error')) {
                opa_track_error($e);
            }
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Custom error thrown and tracked',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

