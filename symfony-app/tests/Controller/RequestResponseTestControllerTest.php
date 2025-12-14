<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHPUnit tests for RequestResponseTestController
 * 
 * Tests request and response size tracking with various scenarios:
 * - Different HTTP methods
 * - Various request body sizes
 * - Various response sizes
 * - Request size validation
 * - Response size validation
 */
class RequestResponseTestControllerTest extends WebTestCase
{
    private const SIZE_SMALL = 100;
    private const SIZE_MEDIUM = 1024;
    private const SIZE_LARGE = 10240;
    private const SIZE_XLARGE = 102400;

    /**
     * Test request-response endpoint with GET method
     */
    public function testRequestResponseGet(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/request-response?response_size=' . self::SIZE_MEDIUM);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('request_info', $response);
        $this->assertArrayHasKey('response_info', $response);
        $this->assertEquals('GET', $response['request_info']['method']);
    }

    /**
     * Test request-response endpoint with POST method
     */
    public function testRequestResponsePost(): void
    {
        $client = static::createClient();

        $body = str_repeat('x', self::SIZE_MEDIUM);
        $client->request('POST', '/api/test/request-response?response_size=' . self::SIZE_MEDIUM, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('POST', $response['request_info']['method']);
        $this->assertGreaterThan(0, $response['request_info']['body_size']);
    }

    /**
     * Test request-response endpoint with PUT method
     */
    public function testRequestResponsePut(): void
    {
        $client = static::createClient();

        $body = str_repeat('y', self::SIZE_LARGE);
        $client->request('PUT', '/api/test/request-response?response_size=' . self::SIZE_LARGE, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('PUT', $response['request_info']['method']);
    }

    /**
     * Test request-response endpoint with PATCH method
     */
    public function testRequestResponsePatch(): void
    {
        $client = static::createClient();

        $body = str_repeat('z', self::SIZE_SMALL);
        $client->request('PATCH', '/api/test/request-response?response_size=' . self::SIZE_SMALL, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('PATCH', $response['request_info']['method']);
    }

    /**
     * Test request size endpoint
     */
    public function testRequestSize(): void
    {
        $client = static::createClient();

        $body = str_repeat('a', self::SIZE_MEDIUM);
        $client->request('POST', '/api/test/request-size', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('body_size', $response);
        $this->assertGreaterThan(0, $response['body_size']);
    }

    /**
     * Test response size endpoint with small size
     */
    public function testResponseSizeSmall(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/response-size/' . self::SIZE_SMALL);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals(self::SIZE_SMALL, $response['requested_size']);
    }

    /**
     * Test response size endpoint with medium size
     */
    public function testResponseSizeMedium(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/response-size/' . self::SIZE_MEDIUM);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals(self::SIZE_MEDIUM, $response['requested_size']);
    }

    /**
     * Test response size endpoint with large size
     */
    public function testResponseSizeLarge(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/response-size/' . self::SIZE_LARGE);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals(self::SIZE_LARGE, $response['requested_size']);
    }

    /**
     * Test response size endpoint with xlarge size
     */
    public function testResponseSizeXLarge(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/response-size/' . self::SIZE_XLARGE);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals(self::SIZE_XLARGE, $response['requested_size']);
    }

    /**
     * Test full request-response endpoint
     */
    public function testFullRequestResponse(): void
    {
        $client = static::createClient();

        $body = str_repeat('test', 100);
        $client->request('POST', '/api/test/full-request-response', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('request', $response);
        $this->assertArrayHasKey('response', $response);
        $this->assertArrayHasKey('validation_instructions', $response);
    }

    /**
     * Test various response sizes
     */
    public function testVariousResponseSizes(): void
    {
        $client = static::createClient();
        $sizes = [100, 500, 1024, 2048, 5120, 10240, 20480, 51200, 102400];

        foreach ($sizes as $size) {
            $client->request('GET', '/api/test/response-size/' . $size);
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertIsArray($response);
            $this->assertEquals($size, $response['requested_size']);
        }
    }

    /**
     * Test various request body sizes
     */
    public function testVariousRequestBodySizes(): void
    {
        $client = static::createClient();
        $sizes = [100, 500, 1024, 2048, 5120, 10240];

        foreach ($sizes as $size) {
            $body = str_repeat('x', $size);
            $client->request('POST', '/api/test/request-size', [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode(['data' => $body]));
            
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertIsArray($response);
            $this->assertGreaterThan(0, $response['body_size']);
        }
    }
}


