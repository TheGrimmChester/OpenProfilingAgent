<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TestListenerController extends AbstractController
{
    #[Route('/api/test/listener', name: 'test_listener', methods: ['GET'])]
    public function test(Request $request): JsonResponse
    {
        // Manually trigger the listener logic
        $traceparent = $request->headers->get('traceparent');
        $tracestate = $request->headers->get('tracestate');
        
        $result = null;
        if ($traceparent && function_exists('opa_set_w3c_context')) {
            $result = opa_set_w3c_context($traceparent, $tracestate);
        }
        
        return new JsonResponse([
            'traceparent' => $traceparent,
            'tracestate' => $tracestate,
            'function_exists' => function_exists('opa_set_w3c_context'),
            'result' => $result,
        ]);
    }
}

