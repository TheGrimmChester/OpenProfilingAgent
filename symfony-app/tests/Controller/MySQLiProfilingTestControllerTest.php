<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHPUnit tests for MySQLiProfilingTestController
 * 
 * Tests MySQLi query profiling with various query types:
 * - Simple queries
 * - CREATE TABLE
 * - INSERT
 * - SELECT
 * - UPDATE
 * - DELETE
 * - Multiple queries
 * - Complex queries
 */
class MySQLiProfilingTestControllerTest extends WebTestCase
{
    /**
     * Test simple MySQLi query
     */
    public function testSimpleQuery(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/mysqli/simple');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('mysqli_simple_query', $response['test']);
        $this->assertArrayHasKey('queries', $response);
        $this->assertNotEmpty($response['queries']);
    }

    /**
     * Test CREATE TABLE query
     */
    public function testCreateTable(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/mysqli/create-table');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
        $this->assertGreaterThanOrEqual(2, count($response['queries'])); // DROP + CREATE
    }

    /**
     * Test INSERT query
     */
    public function testInsert(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/mysqli/insert');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
        
        $insertQuery = $response['queries'][0] ?? null;
        if ($insertQuery) {
            $this->assertEquals('INSERT', $insertQuery['type']);
            $this->assertArrayHasKey('affected_rows', $insertQuery);
        }
    }

    /**
     * Test SELECT query
     */
    public function testSelect(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/mysqli/select');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
        
        $selectQuery = $response['queries'][0] ?? null;
        if ($selectQuery) {
            $this->assertEquals('SELECT', $selectQuery['type']);
            $this->assertArrayHasKey('rows_returned', $selectQuery);
        }
    }

    /**
     * Test UPDATE query
     */
    public function testUpdate(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/mysqli/update');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
        
        $updateQuery = $response['queries'][0] ?? null;
        if ($updateQuery) {
            $this->assertEquals('UPDATE', $updateQuery['type']);
            $this->assertArrayHasKey('affected_rows', $updateQuery);
        }
    }

    /**
     * Test DELETE query
     */
    public function testDelete(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/mysqli/delete');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
        
        $deleteQuery = $response['queries'][0] ?? null;
        if ($deleteQuery) {
            $this->assertEquals('DELETE', $deleteQuery['type']);
            $this->assertArrayHasKey('affected_rows', $deleteQuery);
        }
    }

    /**
     * Test multiple queries
     */
    public function testMultipleQueries(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/mysqli/multiple');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
        $this->assertGreaterThanOrEqual(3, count($response['queries']));
    }

    /**
     * Test complex query
     */
    public function testComplexQuery(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/mysqli/complex');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
    }

    /**
     * Test all MySQLi endpoints in sequence
     */
    public function testAllMySQLiEndpoints(): void
    {
        $client = static::createClient();
        
        $endpoints = [
            '/api/test/mysqli/simple',
            '/api/test/mysqli/create-table',
            '/api/test/mysqli/insert',
            '/api/test/mysqli/select',
            '/api/test/mysqli/update',
            '/api/test/mysqli/delete',
            '/api/test/mysqli/multiple',
            '/api/test/mysqli/complex'
        ];

        foreach ($endpoints as $endpoint) {
            $client->request('GET', $endpoint);
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertIsArray($response);
            $this->assertEquals('success', $response['status']);
        }
    }
}


