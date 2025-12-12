<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SpanController extends AbstractController
{
    #[Route('/api/spans/custom', name: 'spans_custom', methods: ['GET'])]
    public function testCustomSpans(): JsonResponse
    {
        if (!function_exists('opa_create_span')) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'OPA extension not available'
            ], 500);
        }

        // Create custom spans - manual instrumentation
        $parentSpan = opa_create_span('custom_parent_operation');
        opa_add_tag($parentSpan, 'operation_type', 'parent');
        opa_add_tag($parentSpan, 'user_id', '12345');

        // Simulate some work
        usleep(100000); // 100ms

        // Create child span
        $childSpan = opa_create_span('custom_child_operation');
        opa_set_parent($childSpan, $parentSpan);
        opa_add_tag($childSpan, 'operation_type', 'child');
        opa_add_tag($childSpan, 'step', 'processing');

        usleep(50000); // 50ms

        // Dump variables
        $testData = ['key' => 'value', 'number' => 42];
        opa_dump($testData, $parentSpan, $childSpan);

        opa_finish_span($childSpan);
        opa_finish_span($parentSpan);

        return new JsonResponse([
            'status' => 'success',
            'parent_span' => $parentSpan,
            'child_span' => $childSpan,
            'message' => 'Custom spans created successfully'
        ]);
    }

    #[Route('/api/spans/nested', name: 'spans_nested', methods: ['GET'])]
    public function testNestedSpans(): JsonResponse
    {
        if (!function_exists('opa_create_span')) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'OPA extension not available'
            ], 500);
        }

        // Create nested spans (3 levels)
        $level1 = opa_create_span('level1_operation');
        opa_add_tag($level1, 'level', '1');

        usleep(50000);

        $level2 = opa_create_span('level2_operation');
        opa_set_parent($level2, $level1);
        opa_add_tag($level2, 'level', '2');

        usleep(30000);

        $level3 = opa_create_span('level3_operation');
        opa_set_parent($level3, $level2);
        opa_add_tag($level3, 'level', '3');

        usleep(20000);

        opa_finish_span($level3);
        opa_finish_span($level2);
        opa_finish_span($level1);

        return new JsonResponse([
            'status' => 'success',
            'spans' => [
                'level1' => $level1,
                'level2' => $level2,
                'level3' => $level3
            ],
            'message' => 'Nested spans created successfully'
        ]);
    }

    #[Route('/api/spans/tags', name: 'spans_tags', methods: ['GET'])]
    public function testSpanTags(): JsonResponse
    {
        if (!function_exists('opa_create_span')) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'OPA extension not available'
            ], 500);
        }

        $span = opa_create_span('tagged_operation');
        
        // Add multiple tags
        opa_add_tag($span, 'environment', 'test');
        opa_add_tag($span, 'version', '1.0.0');
        opa_add_tag($span, 'feature', 'span_testing');
        opa_add_tag($span, 'user_id', '999');
        opa_add_tag($span, 'request_id', uniqid());

        usleep(100000);

        opa_finish_span($span);

        return new JsonResponse([
            'status' => 'success',
            'span_id' => $span,
            'tags_added' => 5,
            'message' => 'Span tags added successfully'
        ]);
    }
}

