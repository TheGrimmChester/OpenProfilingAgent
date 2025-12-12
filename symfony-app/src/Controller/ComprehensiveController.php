<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ComprehensiveController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    #[Route('/api/comprehensive', name: 'comprehensive', methods: ['GET'])]
    public function comprehensiveTest(): JsonResponse
    {
        // This endpoint tests multiple features together
        $results = [];

        // 1. SQL queries
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS comprehensive_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data TEXT
        )');
        $this->connection->executeStatement('INSERT INTO comprehensive_test (data) VALUES (?)', ['test_data']);
        $results['sql'] = $this->connection->fetchAllAssociative('SELECT * FROM comprehensive_test');

        // 2. Cache operations
        $cacheItem = $this->cache->getItem('comprehensive_test');
        $cacheItem->set('cache_value');
        $this->cache->save($cacheItem);
        $results['cache'] = $this->cache->getItem('comprehensive_test')->get();

        // 3. cURL request
        $ch = curl_init('https://httpbin.org/get');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $curlResponse = curl_exec($ch);
        $results['curl'] = ['http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)];
        curl_close($ch);

        // 4. File I/O
        $testFile = sys_get_temp_dir() . '/comprehensive_test_' . uniqid() . '.txt';
        file_put_contents($testFile, 'comprehensive test data');
        $results['file'] = ['content' => file_get_contents($testFile), 'size' => filesize($testFile)];
        unlink($testFile);

        // 5. Custom span
        if (function_exists('opa_create_span')) {
            $span = opa_create_span('comprehensive_operation');
            opa_add_tag($span, 'test_type', 'comprehensive');
            usleep(50000);
            opa_finish_span($span);
            $results['span'] = $span;
        }

        // 6. Error log
        error_log('Comprehensive test error log');

        return new JsonResponse([
            'status' => 'success',
            'results' => $results,
            'message' => 'Comprehensive test completed - all features tested together'
        ]);
    }
}

