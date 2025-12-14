<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHPUnit tests for ComprehensiveProfilingTestController
 * 
 * Tests comprehensive profiling features including:
 * - HTTP Request/Response profiling
 * - MySQLi query profiling
 * - PDO query profiling
 * - Error tracking
 * - Log tracking
 * - Manual spans
 * - Complex operations
 */
class ComprehensiveProfilingTestControllerTest extends WebTestCase
{
    /**
     * Test comprehensive profiling endpoint with GET method
     */
    public function testComprehensiveGet(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/comprehensive?response_size=500');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('comprehensive_profiling', $response['test']);
        $this->assertArrayHasKey('sections', $response);
        $this->assertArrayHasKey('http_profiling', $response['sections']);
        $this->assertArrayHasKey('mysqli_profiling', $response['sections']);
        $this->assertArrayHasKey('pdo_profiling', $response['sections']);
    }

    /**
     * Test comprehensive profiling endpoint with POST method
     */
    public function testComprehensivePost(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/test/comprehensive?response_size=1000', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => 'test data']));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
    }

    /**
     * Test comprehensive profiling endpoint with PUT method
     */
    public function testComprehensivePut(): void
    {
        $client = static::createClient();

        $client->request('PUT', '/api/test/comprehensive?response_size=2000', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => 'put test data']));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
    }

    /**
     * Test comprehensive profiling endpoint with PATCH method
     */
    public function testComprehensivePatch(): void
    {
        $client = static::createClient();

        $client->request('PATCH', '/api/test/comprehensive?response_size=1500', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => 'patch test data']));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
    }

    /**
     * Test comprehensive profiling with various response sizes
     */
    public function testComprehensiveWithVariousSizes(): void
    {
        $client = static::createClient();
        $sizes = [100, 500, 1000, 5000, 10000];

        foreach ($sizes as $size) {
            $client->request('GET', '/api/test/comprehensive?response_size=' . $size);
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertIsArray($response);
            $this->assertEquals('success', $response['status']);
        }
    }

    /**
     * Test comprehensive profiling validates all sections
     */
    public function testComprehensiveSections(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/comprehensive');
        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('sections', $response);
        $sections = $response['sections'];
        
        // HTTP profiling section
        $this->assertArrayHasKey('http_profiling', $sections);
        $this->assertEquals('success', $sections['http_profiling']['status']);
        
        // MySQLi profiling section
        $this->assertArrayHasKey('mysqli_profiling', $sections);
        $this->assertArrayHasKey('queries', $sections['mysqli_profiling']);
        
        // PDO profiling section
        $this->assertArrayHasKey('pdo_profiling', $sections);
        $this->assertArrayHasKey('queries', $sections['pdo_profiling']);
        
        // Error tracking section
        $this->assertArrayHasKey('error_tracking', $sections);
        
        // Log tracking section
        $this->assertArrayHasKey('log_tracking', $sections);
        
        // Manual spans section
        $this->assertArrayHasKey('manual_spans', $sections);
        
        // Complex operations section
        $this->assertArrayHasKey('complex_operations', $sections);
    }

    /**
     * Test comprehensive profiling execution time tracking
     */
    public function testComprehensiveExecutionTime(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/comprehensive');
        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('execution_time_ms', $response);
        $this->assertIsNumeric($response['execution_time_ms']);
        $this->assertGreaterThan(0, $response['execution_time_ms']);
        
        $this->assertArrayHasKey('total_operations', $response);
        $this->assertIsInt($response['total_operations']);
        $this->assertGreaterThan(0, $response['total_operations']);
    }
}


