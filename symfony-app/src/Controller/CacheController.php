<?php

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CacheController extends AbstractController
{
    #[Route('/api/cache/redis', name: 'cache_redis', methods: ['GET'])]
    public function testRedis(CacheItemPoolInterface $cache): JsonResponse
    {
        // Test Redis cache operations - automatically instrumented by OPA
        $item = $cache->getItem('test_key');
        $item->set('test_value_' . time());
        $item->expiresAfter(3600);
        $cache->save($item);

        $retrieved = $cache->getItem('test_key');
        $value = $retrieved->isHit() ? $retrieved->get() : null;

        // Multiple operations
        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $item = $cache->getItem("key_$i");
            $item->set("value_$i");
            $cache->save($item);
            $items["key_$i"] = $cache->getItem("key_$i")->get();
        }

        return new JsonResponse([
            'status' => 'success',
            'stored_value' => $value,
            'multiple_items' => $items,
            'message' => 'Redis cache operations completed'
        ]);
    }

    #[Route('/api/cache/apcu', name: 'cache_apcu', methods: ['GET'])]
    public function testApcu(): JsonResponse
    {
        // Test APCu cache operations - automatically instrumented by OPA
        if (!function_exists('apcu_store')) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'APCu extension not available'
            ], 500);
        }

        apcu_store('apcu_test_key', 'apcu_test_value', 3600);
        $value = apcu_fetch('apcu_test_key');

        // Multiple APCu operations
        $values = [];
        for ($i = 1; $i <= 5; $i++) {
            apcu_store("apcu_key_$i", "apcu_value_$i", 3600);
            $values["apcu_key_$i"] = apcu_fetch("apcu_key_$i");
        }

        return new JsonResponse([
            'status' => 'success',
            'stored_value' => $value,
            'multiple_items' => $values,
            'message' => 'APCu cache operations completed'
        ]);
    }

    #[Route('/api/cache/mixed', name: 'cache_mixed', methods: ['GET'])]
    public function testMixedCache(CacheItemPoolInterface $cache): JsonResponse
    {
        // Test both Redis and APCu
        $results = [];

        // Redis
        $redisItem = $cache->getItem('mixed_redis');
        $redisItem->set('redis_value');
        $cache->save($redisItem);
        $results['redis'] = $cache->getItem('mixed_redis')->get();

        // APCu
        if (function_exists('apcu_store')) {
            apcu_store('mixed_apcu', 'apcu_value', 3600);
            $results['apcu'] = apcu_fetch('mixed_apcu');
        }

        return new JsonResponse([
            'status' => 'success',
            'results' => $results,
            'message' => 'Mixed cache operations completed'
        ]);
    }
}

