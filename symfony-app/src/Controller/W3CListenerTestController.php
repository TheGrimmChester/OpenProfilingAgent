<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Test controller to verify W3C listener is called
 */
class W3CListenerTestController extends AbstractController
{
    #[Route('/api/test/w3c/listener-verify', name: 'test_w3c_listener_verify', methods: ['GET'])]
    public function verifyListener(Request $request): JsonResponse
    {
        $traceparent = $request->headers->get('traceparent');
        $tracestate = $request->headers->get('tracestate');
        
        // Manually call the function to set W3C context
        $manualResult = null;
        $error = null;
        if ($traceparent && function_exists('opa_set_w3c_context')) {
            try {
                $manualResult = opa_set_w3c_context($traceparent, $tracestate);
                // Force a small delay to ensure context is set
                usleep(100000); // 100ms
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        return new JsonResponse([
            'headers_received' => [
                'traceparent' => $traceparent,
                'tracestate' => $tracestate,
            ],
            'function_exists' => function_exists('opa_set_w3c_context'),
            'manual_call_result' => $manualResult,
            'error' => $error,
            'message' => 'This endpoint manually calls opa_set_w3c_context to verify it works',
        ]);
    }
}

