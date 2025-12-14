<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Redis Profiling Test Controller
 * 
 * Tests Redis operations for OPA extension profiling:
 * - SET/GET operations
 * - DELETE operations
 * - EXISTS operations
 * - INCR/DECR operations
 * - Hash operations (HSET/HGET)
 * - List operations (LPUSH/RPOP)
 * - Set operations (SADD/SMEMBERS)
 * - Expiration (EXPIRE/TTL)
 * - Multiple operations
 */
class RedisProfilingTestController extends AbstractController
{
    private const REDIS_HOST = 'redis-symfony';
    private const REDIS_PORT = 6379;
    private const REDIS_DB = 0;

    /**
     * Get Redis connection
     */
    private function getRedis(): ?\Redis
    {
        if (!extension_loaded('redis')) {
            return null;
        }

        try {
            $redis = new \Redis();
            $connected = $redis->connect(self::REDIS_HOST, self::REDIS_PORT);
            
            if (!$connected) {
                return null;
            }

            $redis->select(self::REDIS_DB);
            return $redis;
        } catch (\Exception $e) {
            return null;
        }
    }

    #[Route('/api/test/redis/simple', name: 'test_redis_simple', methods: ['GET'])]
    public function testSimpleOperations(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_simple_operations',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $testKey = 'opa_test:simple:' . time();

            // Test SET
            $setResult = $redis->set($testKey, 'test_value');
            $results['operations'][] = [
                'operation' => 'SET',
                'key' => $testKey,
                'value' => 'test_value',
                'success' => $setResult
            ];

            // Test GET
            $getValue = $redis->get($testKey);
            $results['operations'][] = [
                'operation' => 'GET',
                'key' => $testKey,
                'value' => $getValue,
                'success' => $getValue !== false
            ];

            // Cleanup
            $redis->del($testKey);

            if (function_exists('opa_dump')) {
                opa_dump('Redis Simple Operations Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/set-get', name: 'test_redis_set_get', methods: ['GET'])]
    public function testSetGet(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_set_get',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $testKey = 'opa_test:set_get:' . time();
            $testValue = 'Test value for Redis profiling ' . uniqid();

            // SET operation
            $setResult = $redis->set($testKey, $testValue);
            $results['operations'][] = [
                'operation' => 'SET',
                'key' => $testKey,
                'value' => $testValue,
                'success' => $setResult
            ];

            // GET operation
            $retrievedValue = $redis->get($testKey);
            $results['operations'][] = [
                'operation' => 'GET',
                'key' => $testKey,
                'retrieved_value' => $retrievedValue,
                'values_match' => $retrievedValue === $testValue,
                'success' => $retrievedValue !== false
            ];

            // Cleanup
            $redis->del($testKey);

            if (function_exists('opa_dump')) {
                opa_dump('Redis Set/Get Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/delete', name: 'test_redis_delete', methods: ['GET'])]
    public function testDelete(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_delete',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $testKey = 'opa_test:delete:' . time();

            // SET first
            $redis->set($testKey, 'value_to_delete');
            $results['operations'][] = [
                'operation' => 'SET',
                'key' => $testKey,
                'success' => true
            ];

            // Check EXISTS before delete
            $existsBefore = $redis->exists($testKey);
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'key' => $testKey,
                'exists' => $existsBefore > 0
            ];

            // DELETE operation
            $deleteResult = $redis->del($testKey);
            $results['operations'][] = [
                'operation' => 'DEL',
                'key' => $testKey,
                'deleted_count' => $deleteResult,
                'success' => $deleteResult > 0
            ];

            // Check EXISTS after delete
            $existsAfter = $redis->exists($testKey);
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'key' => $testKey,
                'exists' => $existsAfter > 0,
                'deleted' => $existsAfter === 0
            ];

            if (function_exists('opa_dump')) {
                opa_dump('Redis Delete Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/exists', name: 'test_redis_exists', methods: ['GET'])]
    public function testExists(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_exists',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $existingKey = 'opa_test:exists:' . time();
            $nonExistingKey = 'opa_test:not_exists:' . time();

            // SET a key
            $redis->set($existingKey, 'value');
            $results['operations'][] = [
                'operation' => 'SET',
                'key' => $existingKey,
                'success' => true
            ];

            // EXISTS on existing key
            $existsResult = $redis->exists($existingKey);
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'key' => $existingKey,
                'exists' => $existsResult > 0,
                'success' => true
            ];

            // EXISTS on non-existing key
            $notExistsResult = $redis->exists($nonExistingKey);
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'key' => $nonExistingKey,
                'exists' => $notExistsResult > 0,
                'success' => $notExistsResult === 0
            ];

            // Cleanup
            $redis->del($existingKey);

            if (function_exists('opa_dump')) {
                opa_dump('Redis Exists Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/incr-decr', name: 'test_redis_incr_decr', methods: ['GET'])]
    public function testIncrDecr(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_incr_decr',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $counterKey = 'opa_test:counter:' . time();

            // INCR operation
            $incrResult = $redis->incr($counterKey);
            $results['operations'][] = [
                'operation' => 'INCR',
                'key' => $counterKey,
                'value' => $incrResult,
                'success' => $incrResult === 1
            ];

            // INCR again
            $incrResult2 = $redis->incr($counterKey);
            $results['operations'][] = [
                'operation' => 'INCR',
                'key' => $counterKey,
                'value' => $incrResult2,
                'success' => $incrResult2 === 2
            ];

            // DECR operation
            $decrResult = $redis->decr($counterKey);
            $results['operations'][] = [
                'operation' => 'DECR',
                'key' => $counterKey,
                'value' => $decrResult,
                'success' => $decrResult === 1
            ];

            // Get final value
            $finalValue = $redis->get($counterKey);
            $results['operations'][] = [
                'operation' => 'GET',
                'key' => $counterKey,
                'value' => $finalValue,
                'success' => $finalValue === '1'
            ];

            // Cleanup
            $redis->del($counterKey);

            if (function_exists('opa_dump')) {
                opa_dump('Redis Incr/Decr Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/hash', name: 'test_redis_hash', methods: ['GET'])]
    public function testHash(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_hash',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $hashKey = 'opa_test:hash:' . time();

            // HSET operation
            $hsetResult = $redis->hSet($hashKey, 'field1', 'value1');
            $results['operations'][] = [
                'operation' => 'HSET',
                'key' => $hashKey,
                'field' => 'field1',
                'value' => 'value1',
                'success' => $hsetResult !== false
            ];

            // HSET multiple fields
            $redis->hSet($hashKey, 'field2', 'value2');
            $redis->hSet($hashKey, 'field3', 'value3');
            $results['operations'][] = [
                'operation' => 'HSET',
                'key' => $hashKey,
                'fields_set' => 3,
                'success' => true
            ];

            // HGET operation
            $hgetValue = $redis->hGet($hashKey, 'field1');
            $results['operations'][] = [
                'operation' => 'HGET',
                'key' => $hashKey,
                'field' => 'field1',
                'value' => $hgetValue,
                'success' => $hgetValue === 'value1'
            ];

            // HGETALL operation
            $hgetAll = $redis->hGetAll($hashKey);
            $results['operations'][] = [
                'operation' => 'HGETALL',
                'key' => $hashKey,
                'fields' => $hgetAll,
                'field_count' => count($hgetAll),
                'success' => count($hgetAll) === 3
            ];

            // Cleanup
            $redis->del($hashKey);

            if (function_exists('opa_dump')) {
                opa_dump('Redis Hash Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/list', name: 'test_redis_list', methods: ['GET'])]
    public function testList(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_list',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $listKey = 'opa_test:list:' . time();

            // LPUSH operation
            $lpushResult = $redis->lPush($listKey, 'item1');
            $results['operations'][] = [
                'operation' => 'LPUSH',
                'key' => $listKey,
                'value' => 'item1',
                'list_length' => $lpushResult,
                'success' => $lpushResult > 0
            ];

            // LPUSH more items
            $redis->lPush($listKey, 'item2');
            $redis->lPush($listKey, 'item3');
            $results['operations'][] = [
                'operation' => 'LPUSH',
                'key' => $listKey,
                'items_pushed' => 3,
                'success' => true
            ];

            // RPOP operation
            $rpopValue = $redis->rPop($listKey);
            $results['operations'][] = [
                'operation' => 'RPOP',
                'key' => $listKey,
                'value' => $rpopValue,
                'success' => $rpopValue === 'item1'
            ];

            // LLEN operation
            $llen = $redis->lLen($listKey);
            $results['operations'][] = [
                'operation' => 'LLEN',
                'key' => $listKey,
                'length' => $llen,
                'success' => $llen === 2
            ];

            // Cleanup
            $redis->del($listKey);

            if (function_exists('opa_dump')) {
                opa_dump('Redis List Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/set', name: 'test_redis_set', methods: ['GET'])]
    public function testSet(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_set',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $setKey = 'opa_test:set:' . time();

            // SADD operation
            $saddResult = $redis->sAdd($setKey, 'member1');
            $results['operations'][] = [
                'operation' => 'SADD',
                'key' => $setKey,
                'member' => 'member1',
                'added' => $saddResult,
                'success' => $saddResult > 0
            ];

            // SADD more members
            $redis->sAdd($setKey, 'member2');
            $redis->sAdd($setKey, 'member3');
            $results['operations'][] = [
                'operation' => 'SADD',
                'key' => $setKey,
                'members_added' => 3,
                'success' => true
            ];

            // SMEMBERS operation
            $smembers = $redis->sMembers($setKey);
            $results['operations'][] = [
                'operation' => 'SMEMBERS',
                'key' => $setKey,
                'members' => $smembers,
                'member_count' => count($smembers),
                'success' => count($smembers) === 3
            ];

            // SCARD operation
            $scard = $redis->sCard($setKey);
            $results['operations'][] = [
                'operation' => 'SCARD',
                'key' => $setKey,
                'cardinality' => $scard,
                'success' => $scard === 3
            ];

            // Cleanup
            $redis->del($setKey);

            if (function_exists('opa_dump')) {
                opa_dump('Redis Set Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/expire', name: 'test_redis_expire', methods: ['GET'])]
    public function testExpire(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_expire',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $expireKey = 'opa_test:expire:' . time();

            // SET with expiration
            $redis->set($expireKey, 'value_with_expiration');
            $results['operations'][] = [
                'operation' => 'SET',
                'key' => $expireKey,
                'success' => true
            ];

            // EXPIRE operation
            $expireResult = $redis->expire($expireKey, 10);
            $results['operations'][] = [
                'operation' => 'EXPIRE',
                'key' => $expireKey,
                'seconds' => 10,
                'success' => $expireResult
            ];

            // TTL operation
            $ttl = $redis->ttl($expireKey);
            $results['operations'][] = [
                'operation' => 'TTL',
                'key' => $expireKey,
                'ttl' => $ttl,
                'has_expiration' => $ttl > 0,
                'success' => $ttl > 0 && $ttl <= 10
            ];

            // Cleanup
            $redis->del($expireKey);

            if (function_exists('opa_dump')) {
                opa_dump('Redis Expire Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/multiple', name: 'test_redis_multiple', methods: ['GET'])]
    public function testMultipleOperations(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_multiple_operations',
            'operations' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $baseKey = 'opa_test:multiple:' . time();

            // Multiple SET operations
            $keys = [];
            for ($i = 1; $i <= 5; $i++) {
                $key = $baseKey . ':key' . $i;
                $keys[] = $key;
                $redis->set($key, 'value' . $i);
            }
            $results['operations'][] = [
                'operation' => 'SET',
                'keys_set' => count($keys),
                'success' => true
            ];

            // Multiple GET operations
            $values = [];
            foreach ($keys as $key) {
                $values[] = $redis->get($key);
            }
            $results['operations'][] = [
                'operation' => 'GET',
                'keys_retrieved' => count($values),
                'values' => $values,
                'success' => count($values) === 5
            ];

            // Multiple EXISTS operations
            $existsCount = 0;
            foreach ($keys as $key) {
                if ($redis->exists($key)) {
                    $existsCount++;
                }
            }
            $results['operations'][] = [
                'operation' => 'EXISTS',
                'keys_checked' => count($keys),
                'existing_keys' => $existsCount,
                'success' => $existsCount === 5
            ];

            // Cleanup all keys
            $redis->del($keys);
            $results['operations'][] = [
                'operation' => 'DEL',
                'keys_deleted' => count($keys),
                'success' => true
            ];

            if (function_exists('opa_dump')) {
                opa_dump('Redis Multiple Operations Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/redis/comprehensive', name: 'test_redis_comprehensive', methods: ['GET'])]
    public function testComprehensive(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'redis_comprehensive',
            'sections' => []
        ];

        $redis = $this->getRedis();
        
        if (!$redis) {
            $results['status'] = 'error';
            $results['error'] = 'Redis extension not available or connection failed';
            return new JsonResponse($results, 200);
        }

        try {
            $baseKey = 'opa_test:comprehensive:' . time();

            // Section 1: String operations
            $results['sections']['string_operations'] = $this->testStringOperations($redis, $baseKey);

            // Section 2: Hash operations
            $results['sections']['hash_operations'] = $this->testHashOperations($redis, $baseKey);

            // Section 3: List operations
            $results['sections']['list_operations'] = $this->testListOperations($redis, $baseKey);

            // Section 4: Set operations
            $results['sections']['set_operations'] = $this->testSetOperations($redis, $baseKey);

            // Section 5: Counter operations
            $results['sections']['counter_operations'] = $this->testCounterOperations($redis, $baseKey);

            // Cleanup all test keys
            $pattern = $baseKey . '*';
            $keys = $redis->keys($pattern);
            if (!empty($keys)) {
                $redis->del($keys);
            }

            if (function_exists('opa_dump')) {
                opa_dump('Redis Comprehensive Test', $results);
            }
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        } finally {
            if ($redis) {
                $redis->close();
            }
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Test string operations
     */
    private function testStringOperations(\Redis $redis, string $baseKey): array
    {
        $section = [
            'name' => 'String Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            $key = $baseKey . ':string';
            
            $redis->set($key, 'test_value');
            $section['operations'][] = ['operation' => 'SET', 'success' => true];
            
            $value = $redis->get($key);
            $section['operations'][] = ['operation' => 'GET', 'success' => $value === 'test_value'];
            
            $redis->del($key);
            $section['operations'][] = ['operation' => 'DEL', 'success' => true];
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Test hash operations
     */
    private function testHashOperations(\Redis $redis, string $baseKey): array
    {
        $section = [
            'name' => 'Hash Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            $key = $baseKey . ':hash';
            
            $redis->hSet($key, 'field1', 'value1');
            $redis->hSet($key, 'field2', 'value2');
            $section['operations'][] = ['operation' => 'HSET', 'success' => true];
            
            $value = $redis->hGet($key, 'field1');
            $section['operations'][] = ['operation' => 'HGET', 'success' => $value === 'value1'];
            
            $all = $redis->hGetAll($key);
            $section['operations'][] = ['operation' => 'HGETALL', 'success' => count($all) === 2];
            
            $redis->del($key);
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Test list operations
     */
    private function testListOperations(\Redis $redis, string $baseKey): array
    {
        $section = [
            'name' => 'List Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            $key = $baseKey . ':list';
            
            $redis->lPush($key, 'item1');
            $redis->lPush($key, 'item2');
            $section['operations'][] = ['operation' => 'LPUSH', 'success' => true];
            
            $length = $redis->lLen($key);
            $section['operations'][] = ['operation' => 'LLEN', 'success' => $length === 2];
            
            $value = $redis->rPop($key);
            $section['operations'][] = ['operation' => 'RPOP', 'success' => $value === 'item1'];
            
            $redis->del($key);
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Test set operations
     */
    private function testSetOperations(\Redis $redis, string $baseKey): array
    {
        $section = [
            'name' => 'Set Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            $key = $baseKey . ':set';
            
            $redis->sAdd($key, 'member1');
            $redis->sAdd($key, 'member2');
            $section['operations'][] = ['operation' => 'SADD', 'success' => true];
            
            $members = $redis->sMembers($key);
            $section['operations'][] = ['operation' => 'SMEMBERS', 'success' => count($members) === 2];
            
            $cardinality = $redis->sCard($key);
            $section['operations'][] = ['operation' => 'SCARD', 'success' => $cardinality === 2];
            
            $redis->del($key);
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Test counter operations
     */
    private function testCounterOperations(\Redis $redis, string $baseKey): array
    {
        $section = [
            'name' => 'Counter Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            $key = $baseKey . ':counter';
            
            $redis->set($key, 0);
            $section['operations'][] = ['operation' => 'SET', 'success' => true];
            
            $value1 = $redis->incr($key);
            $section['operations'][] = ['operation' => 'INCR', 'success' => $value1 === 1];
            
            $value2 = $redis->incr($key);
            $section['operations'][] = ['operation' => 'INCR', 'success' => $value2 === 2];
            
            $value3 = $redis->decr($key);
            $section['operations'][] = ['operation' => 'DECR', 'success' => $value3 === 1];
            
            $redis->del($key);
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }
}
