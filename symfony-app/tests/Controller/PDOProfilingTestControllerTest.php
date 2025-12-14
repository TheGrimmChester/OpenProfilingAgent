<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHPUnit tests for PDOProfilingTestController
 * 
 * Tests PDO query profiling with various query types:
 * - Simple queries
 * - CREATE TABLE
 * - INSERT
 * - SELECT
 * - UPDATE
 * - DELETE
 * - Prepared statements
 * - Transactions
 * - Multiple queries
 * - Complex queries
 */
class PDOProfilingTestControllerTest extends WebTestCase
{
    /**
     * Test simple PDO query
     */
    public function testSimpleQuery(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/pdo/simple');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('pdo_simple_query', $response['test']);
        $this->assertArrayHasKey('queries', $response);
        $this->assertNotEmpty($response['queries']);
    }

    /**
     * Test CREATE TABLE query
     */
    public function testCreateTable(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/pdo/create-table');
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

        $client->request('GET', '/api/test/pdo/insert');
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
     * Test prepared statement with execute
     */
    public function testPrepareExecute(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/pdo/prepare-execute');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
        $this->assertGreaterThanOrEqual(2, count($response['queries'])); // PREPARE + EXECUTE
    }

    /**
     * Test SELECT query
     */
    public function testSelect(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/pdo/select');
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

        $client->request('GET', '/api/test/pdo/update');
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

        $client->request('GET', '/api/test/pdo/delete');
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

        $client->request('GET', '/api/test/pdo/multiple');
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

        $client->request('GET', '/api/test/pdo/complex');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
    }

    /**
     * Test transaction
     */
    public function testTransaction(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/pdo/transaction');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('queries', $response);
        $this->assertArrayHasKey('transaction_committed', $response);
        $this->assertTrue($response['transaction_committed']);
        $this->assertGreaterThanOrEqual(3, count($response['queries']));
    }

    /**
     * Test all PDO endpoints in sequence
     */
    public function testAllPDOEndpoints(): void
    {
        $client = static::createClient();
        
        $endpoints = [
            '/api/test/pdo/simple',
            '/api/test/pdo/create-table',
            '/api/test/pdo/insert',
            '/api/test/pdo/prepare-execute',
            '/api/test/pdo/select',
            '/api/test/pdo/update',
            '/api/test/pdo/delete',
            '/api/test/pdo/multiple',
            '/api/test/pdo/complex',
            '/api/test/pdo/transaction'
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


