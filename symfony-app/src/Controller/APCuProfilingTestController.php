<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * APCu Profiling Test Controller
 * 
 * Tests APCu operations for OPA extension profiling:
 * - FETCH operations (cache hits and misses)
 * - STORE operations
 * - DELETE operations
 * - EXISTS operations
 * - CLEAR_CACHE operations
 * - Multiple operations
 */
class APCuProfilingTestController extends AbstractController
{
    /**
     * Check if APCu extension is available
     */
    private function isAPCuAvailable(): bool
    {
        return extension_loaded('apcu');
    }

    #[Route('/api/test/apcu/simple', name: 'test_apcu_simple', methods: ['GET'])]
    public function testSimpleOperations(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'apcu_simple_operations',
            'operations' => []
        ];

        if (!$this->isAPCuAvailable()) {
            $results['status'] = 'error';
            $results['error'] = 'APCu extension not available';
            return new JsonResponse($results, 200);
        }

        try {
            $testKey = 'opa_test:simple:' . time();

            // Test STORE
            $storeResult = apcu_store($testKey, 'test_value');
            $results['operations'][] = [
                'operation' => 'STORE',
                'key' => $testKey,
                'value' => 'test_value',
                'success' => $storeResult
            ];

            // Test FETCH (should be a hit)
            $fetchValue = apcu_fetch($testKey);
            $results['operations'][] = [
                'operation' => 'FETCH',
                'key' => $testKey,
                'value' => $fetchValue,
                'hit' => $fetchValue !== false,
                'success' => $fetchValue === 'test_value'
            ];

            // Cleanup
            apcu_delete($testKey);

            if (function_exists('opa_dump')) {
                opa_dump('APCu Simple Operations Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/apcu/fetch-store', name: 'test_apcu_fetch_store', methods: ['GET'])]
    public function testFetchStore(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'apcu_fetch_store',
            'operations' => []
        ];

        if (!$this->isAPCuAvailable()) {
            $results['status'] = 'error';
            $results['error'] = 'APCu extension not available';
            return new JsonResponse($results, 200);
        }

        try {
            $testKey = 'opa_test:fetch_store:' . time();
            $testValue = 'Test value for APCu profiling ' . uniqid();

            // STORE operation
            $storeResult = apcu_store($testKey, $testValue);
            $results['operations'][] = [
                'operation' => 'STORE',
                'key' => $testKey,
                'value' => $testValue,
                'success' => $storeResult
            ];

            // FETCH operation (should be a hit)
            $retrievedValue = apcu_fetch($testKey);
            $results['operations'][] = [
                'operation' => 'FETCH',
                'key' => $testKey,
                'retrieved_value' => $retrievedValue,
                'values_match' => $retrievedValue === $testValue,
                'hit' => $retrievedValue !== false,
                'success' => $retrievedValue === $testValue
            ];

            // FETCH non-existent key (should be a miss)
            $nonExistentKey = 'opa_test:not_exists:' . time();
            $missValue = apcu_fetch($nonExistentKey);
            $results['operations'][] = [
                'operation' => 'FETCH',
                'key' => $nonExistentKey,
                'retrieved_value' => $missValue,
                'hit' => $missValue !== false,
                'success' => $missValue === false
            ];

            // Cleanup
            apcu_delete($testKey);

            if (function_exists('opa_dump')) {
                opa_dump('APCu Fetch/Store Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/apcu/delete', name: 'test_apcu_delete', methods: ['GET'])]
    public function testDelete(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'apcu_delete',
            'operations' => []
        ];

        if (!$this->isAPCuAvailable()) {
            $results['status'] = 'error';
            $results['error'] = 'APCu extension not available';
            return new JsonResponse($results, 200);
        }

        try {
            $testKey = 'opa_test:delete:' . time();

            // STORE first
            $storeResult = apcu_store($testKey, 'value_to_delete');
            $results['operations'][] = [
                'operation' => 'STORE',
                'key' => $testKey,
                'success' => $storeResult
            ];

            // Check EXISTS before delete
            $existsBefore = apcu_exists($testKey);
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'key' => $testKey,
                'exists' => $existsBefore
            ];

            // DELETE operation
            $deleteResult = apcu_delete($testKey);
            $results['operations'][] = [
                'operation' => 'DELETE',
                'key' => $testKey,
                'deleted' => $deleteResult,
                'success' => $deleteResult
            ];

            // Check EXISTS after delete
            $existsAfter = apcu_exists($testKey);
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'key' => $testKey,
                'exists' => $existsAfter,
                'deleted' => !$existsAfter
            ];

            if (function_exists('opa_dump')) {
                opa_dump('APCu Delete Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/apcu/exists', name: 'test_apcu_exists', methods: ['GET'])]
    public function testExists(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'apcu_exists',
            'operations' => []
        ];

        if (!$this->isAPCuAvailable()) {
            $results['status'] = 'error';
            $results['error'] = 'APCu extension not available';
            return new JsonResponse($results, 200);
        }

        try {
            $existingKey = 'opa_test:exists:' . time();
            $nonExistingKey = 'opa_test:not_exists:' . time();

            // STORE a key
            apcu_store($existingKey, 'value');
            $results['operations'][] = [
                'operation' => 'STORE',
                'key' => $existingKey,
                'success' => true
            ];

            // EXISTS on existing key
            $existsResult = apcu_exists($existingKey);
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'key' => $existingKey,
                'exists' => $existsResult,
                'success' => $existsResult
            ];

            // EXISTS on non-existing key
            $notExistsResult = apcu_exists($nonExistingKey);
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'key' => $nonExistingKey,
                'exists' => $notExistsResult,
                'success' => !$notExistsResult
            ];

            // Cleanup
            apcu_delete($existingKey);

            if (function_exists('opa_dump')) {
                opa_dump('APCu Exists Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/apcu/clear', name: 'test_apcu_clear', methods: ['GET'])]
    public function testClear(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'apcu_clear',
            'operations' => []
        ];

        if (!$this->isAPCuAvailable()) {
            $results['status'] = 'error';
            $results['error'] = 'APCu extension not available';
            return new JsonResponse($results, 200);
        }

        try {
            $baseKey = 'opa_test:clear:' . time();

            // STORE multiple keys
            $keys = [];
            for ($i = 1; $i <= 5; $i++) {
                $key = $baseKey . ':key' . $i;
                $keys[] = $key;
                apcu_store($key, 'value' . $i);
            }
            $results['operations'][] = [
                'operation' => 'STORE',
                'keys_stored' => count($keys),
                'success' => true
            ];

            // Verify keys exist
            $existingCount = 0;
            foreach ($keys as $key) {
                if (apcu_exists($key)) {
                    $existingCount++;
                }
            }
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'keys_checked' => count($keys),
                'existing_keys' => $existingCount,
                'success' => $existingCount === 5
            ];

            // CLEAR_CACHE operation
            $clearResult = apcu_clear_cache();
            $results['operations'][] = [
                'operation' => 'CLEAR_CACHE',
                'success' => $clearResult
            ];

            // Verify keys no longer exist
            $remainingCount = 0;
            foreach ($keys as $key) {
                if (apcu_exists($key)) {
                    $remainingCount++;
                }
            }
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'keys_checked' => count($keys),
                'remaining_keys' => $remainingCount,
                'cleared' => $remainingCount === 0,
                'success' => $remainingCount === 0
            ];

            if (function_exists('opa_dump')) {
                opa_dump('APCu Clear Cache Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/apcu/multiple', name: 'test_apcu_multiple', methods: ['GET'])]
    public function testMultipleOperations(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'apcu_multiple_operations',
            'operations' => []
        ];

        if (!$this->isAPCuAvailable()) {
            $results['status'] = 'error';
            $results['error'] = 'APCu extension not available';
            return new JsonResponse($results, 200);
        }

        try {
            $baseKey = 'opa_test:multiple:' . time();

            // Multiple STORE operations
            $keys = [];
            for ($i = 1; $i <= 5; $i++) {
                $key = $baseKey . ':key' . $i;
                $keys[] = $key;
                apcu_store($key, 'value' . $i);
            }
            $results['operations'][] = [
                'operation' => 'STORE',
                'keys_stored' => count($keys),
                'success' => true
            ];

            // Multiple FETCH operations
            $values = [];
            foreach ($keys as $key) {
                $values[] = apcu_fetch($key);
            }
            $results['operations'][] = [
                'operation' => 'FETCH',
                'keys_retrieved' => count($values),
                'values' => $values,
                'success' => count($values) === 5
            ];

            // Multiple EXISTS operations
            $existsCount = 0;
            foreach ($keys as $key) {
                if (apcu_exists($key)) {
                    $existsCount++;
                }
            }
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'keys_checked' => count($keys),
                'existing_keys' => $existsCount,
                'success' => $existsCount === 5
            ];

            // Multiple DELETE operations
            $deletedCount = 0;
            foreach ($keys as $key) {
                if (apcu_delete($key)) {
                    $deletedCount++;
                }
            }
            $results['operations'][] = [
                'operation' => 'DELETE',
                'keys_deleted' => $deletedCount,
                'success' => $deletedCount === 5
            ];

            if (function_exists('opa_dump')) {
                opa_dump('APCu Multiple Operations Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/apcu/comprehensive', name: 'test_apcu_comprehensive', methods: ['GET'])]
    public function testComprehensive(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'apcu_comprehensive',
            'sections' => []
        ];

        if (!$this->isAPCuAvailable()) {
            $results['status'] = 'error';
            $results['error'] = 'APCu extension not available';
            return new JsonResponse($results, 200);
        }

        try {
            $baseKey = 'opa_test:comprehensive:' . time();

            // Section 1: String operations
            $results['sections']['string_operations'] = $this->testStringOperations($baseKey);

            // Section 2: Array operations
            $results['sections']['array_operations'] = $this->testArrayOperations($baseKey);

            // Section 3: Mixed operations
            $results['sections']['mixed_operations'] = $this->testMixedOperations($baseKey);

            // Cleanup any remaining keys
            $pattern = $baseKey . '*';
            // Note: APCu doesn't have a keys() function, so we'll just try to delete known keys
            for ($i = 1; $i <= 10; $i++) {
                apcu_delete($baseKey . ':string:' . $i);
                apcu_delete($baseKey . ':array:' . $i);
                apcu_delete($baseKey . ':mixed:' . $i);
            }

            if (function_exists('opa_dump')) {
                opa_dump('APCu Comprehensive Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Test string operations
     */
    private function testStringOperations(string $baseKey): array
    {
        $section = [
            'name' => 'String Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            $key = $baseKey . ':string:1';
            
            apcu_store($key, 'test_string_value');
            $section['operations'][] = ['operation' => 'STORE', 'type' => 'string', 'success' => true];
            
            $value = apcu_fetch($key);
            $section['operations'][] = ['operation' => 'FETCH', 'type' => 'string', 'success' => $value === 'test_string_value'];
            
            apcu_delete($key);
            $section['operations'][] = ['operation' => 'DELETE', 'type' => 'string', 'success' => true];
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Test array operations
     */
    private function testArrayOperations(string $baseKey): array
    {
        $section = [
            'name' => 'Array Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            $key = $baseKey . ':array:1';
            $testArray = ['field1' => 'value1', 'field2' => 'value2', 'field3' => 'value3'];
            
            apcu_store($key, $testArray);
            $section['operations'][] = ['operation' => 'STORE', 'type' => 'array', 'success' => true];
            
            $value = apcu_fetch($key);
            $section['operations'][] = ['operation' => 'FETCH', 'type' => 'array', 'success' => is_array($value) && count($value) === 3];
            
            apcu_delete($key);
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Test mixed operations
     */
    private function testMixedOperations(string $baseKey): array
    {
        $section = [
            'name' => 'Mixed Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            $key1 = $baseKey . ':mixed:1';
            $key2 = $baseKey . ':mixed:2';
            $key3 = $baseKey . ':mixed:3';
            
            // Store different types
            apcu_store($key1, 'string_value');
            apcu_store($key2, 12345);
            apcu_store($key3, ['nested' => 'data']);
            $section['operations'][] = ['operation' => 'STORE', 'mixed_types' => 3, 'success' => true];
            
            // Fetch all
            $val1 = apcu_fetch($key1);
            $val2 = apcu_fetch($key2);
            $val3 = apcu_fetch($key3);
            $section['operations'][] = [
                'operation' => 'FETCH',
                'retrieved' => 3,
                'success' => $val1 === 'string_value' && $val2 === 12345 && is_array($val3)
            ];
            
            // Delete all
            apcu_delete($key1);
            apcu_delete($key2);
            apcu_delete($key3);
            $section['operations'][] = ['operation' => 'DELETE', 'deleted' => 3, 'success' => true];
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }
}
