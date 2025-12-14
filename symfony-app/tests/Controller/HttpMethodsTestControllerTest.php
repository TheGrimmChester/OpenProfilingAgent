<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHPUnit tests for HttpMethodsTestController
 * 
 * Tests all HTTP methods (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
 * with various request and response sizes.
 */
class HttpMethodsTestControllerTest extends WebTestCase
{
    private const SIZE_TINY = 100;
    private const SIZE_SMALL = 1024;
    private const SIZE_MEDIUM = 10240;
    private const SIZE_LARGE = 102400;
    private const SIZE_XLARGE = 1048576;

    /**
     * Test GET method with various response sizes
     */
    public function testGetMethod(): void
    {
        $client = static::createClient();

        // Test with default size
        $client->request('GET', '/api/test/http/get');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('GET', $response['method']);

        // Test with small size
        $client->request('GET', '/api/test/http/get?size=' . self::SIZE_SMALL);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_SMALL - 200, strlen($client->getResponse()->getContent()));

        // Test with medium size
        $client->request('GET', '/api/test/http/get?size=' . self::SIZE_MEDIUM);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_MEDIUM - 200, strlen($client->getResponse()->getContent()));

        // Test with large size
        $client->request('GET', '/api/test/http/get?size=' . self::SIZE_LARGE);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_LARGE - 200, strlen($client->getResponse()->getContent()));
    }

    /**
     * Test POST method with various request and response sizes
     */
    public function testPostMethod(): void
    {
        $client = static::createClient();

        // Test with small request body
        $smallBody = str_repeat('x', self::SIZE_SMALL);
        $client->request('POST', '/api/test/http/post', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $smallBody]));
        
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('POST', $response['method']);
        $this->assertGreaterThanOrEqual(self::SIZE_SMALL, $response['request_info']['body_size']);

        // Test with medium request body
        $mediumBody = str_repeat('y', self::SIZE_MEDIUM);
        $client->request('POST', '/api/test/http/post?response_size=' . self::SIZE_MEDIUM, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $mediumBody]));
        
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_MEDIUM - 200, strlen($client->getResponse()->getContent()));

        // Test with large request body
        $largeBody = str_repeat('z', self::SIZE_LARGE);
        $client->request('POST', '/api/test/http/post?response_size=' . self::SIZE_LARGE, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $largeBody]));
        
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_LARGE - 200, strlen($client->getResponse()->getContent()));
    }

    /**
     * Test PUT method with various request and response sizes
     */
    public function testPutMethod(): void
    {
        $client = static::createClient();

        // Test with small request body
        $smallBody = str_repeat('a', self::SIZE_SMALL);
        $client->request('PUT', '/api/test/http/put', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $smallBody]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('PUT', $response['method']);

        // Test with medium request body and custom response size
        $mediumBody = str_repeat('b', self::SIZE_MEDIUM);
        $client->request('PUT', '/api/test/http/put?response_size=' . self::SIZE_MEDIUM, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $mediumBody]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_MEDIUM - 200, strlen($client->getResponse()->getContent()));
    }

    /**
     * Test PATCH method with various request and response sizes
     */
    public function testPatchMethod(): void
    {
        $client = static::createClient();

        // Test with small request body
        $smallBody = str_repeat('c', self::SIZE_SMALL);
        $client->request('PATCH', '/api/test/http/patch', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $smallBody]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('PATCH', $response['method']);

        // Test with large request body
        $largeBody = str_repeat('d', self::SIZE_LARGE);
        $client->request('PATCH', '/api/test/http/patch?response_size=' . self::SIZE_LARGE, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $largeBody]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_LARGE - 200, strlen($client->getResponse()->getContent()));
    }

    /**
     * Test DELETE method with various response sizes
     */
    public function testDeleteMethod(): void
    {
        $client = static::createClient();

        // Test with default size
        $client->request('DELETE', '/api/test/http/delete');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('DELETE', $response['method']);

        // Test with medium size
        $client->request('DELETE', '/api/test/http/delete?size=' . self::SIZE_MEDIUM);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_MEDIUM - 200, strlen($client->getResponse()->getContent()));
    }

    /**
     * Test HEAD method
     */
    public function testHeadMethod(): void
    {
        $client = static::createClient();

        $client->request('HEAD', '/api/test/http/head');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('', $client->getResponse()->getContent()); // HEAD should have no body
        $this->assertEquals('HEAD', $client->getResponse()->headers->get('X-Test-Method'));

        // Test with size parameter
        $client->request('HEAD', '/api/test/http/head?size=' . self::SIZE_MEDIUM);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('', $client->getResponse()->getContent());
        $this->assertEquals((string)self::SIZE_MEDIUM, $client->getResponse()->headers->get('X-Requested-Size'));
    }

    /**
     * Test OPTIONS method
     */
    public function testOptionsMethod(): void
    {
        $client = static::createClient();

        $client->request('OPTIONS', '/api/test/http/options');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('', $client->getResponse()->getContent());
        $this->assertEquals('OPTIONS', $client->getResponse()->headers->get('X-Test-Method'));
        $this->assertStringContainsString('GET', $client->getResponse()->headers->get('Allow'));
        $this->assertStringContainsString('POST', $client->getResponse()->headers->get('Allow'));
    }

    /**
     * Test comprehensive endpoint with GET method
     */
    public function testComprehensiveGet(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/http/comprehensive?response_size=' . self::SIZE_MEDIUM);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('GET', $response['method']);
        $this->assertArrayHasKey('size_tests', $response);
        $this->assertArrayHasKey('tiny', $response['size_tests']);
        $this->assertArrayHasKey('small', $response['size_tests']);
        $this->assertArrayHasKey('medium', $response['size_tests']);
        $this->assertArrayHasKey('large', $response['size_tests']);
    }

    /**
     * Test comprehensive endpoint with POST method
     */
    public function testComprehensivePost(): void
    {
        $client = static::createClient();

        $body = str_repeat('test', 100);
        $client->request('POST', '/api/test/http/comprehensive?response_size=' . self::SIZE_MEDIUM, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('request_info', $response);
        $this->assertGreaterThan(0, $response['request_info']['body_size']);
    }

    /**
     * Test method with size parameter - GET
     */
    public function testMethodWithSizeGet(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/http/get/size/' . self::SIZE_MEDIUM);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('GET', $response['method']);
        $this->assertGreaterThanOrEqual(self::SIZE_MEDIUM - 200, strlen($client->getResponse()->getContent()));
    }

    /**
     * Test method with size parameter - POST
     */
    public function testMethodWithSizePost(): void
    {
        $client = static::createClient();

        $body = str_repeat('x', self::SIZE_SMALL);
        $client->request('POST', '/api/test/http/post/size/' . self::SIZE_MEDIUM, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('POST', $response['method']);
        $this->assertGreaterThanOrEqual(self::SIZE_MEDIUM - 200, strlen($client->getResponse()->getContent()));
    }

    /**
     * Test method with size parameter - PUT
     */
    public function testMethodWithSizePut(): void
    {
        $client = static::createClient();

        $body = str_repeat('y', self::SIZE_SMALL);
        $client->request('PUT', '/api/test/http/put/size/' . self::SIZE_MEDIUM, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('PUT', $response['method']);
    }

    /**
     * Test method with size parameter - PATCH
     */
    public function testMethodWithSizePatch(): void
    {
        $client = static::createClient();

        $body = str_repeat('z', self::SIZE_SMALL);
        $client->request('PATCH', '/api/test/http/patch/size/' . self::SIZE_MEDIUM, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $body]));
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('PATCH', $response['method']);
    }

    /**
     * Test method with size parameter - DELETE
     */
    public function testMethodWithSizeDelete(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/test/http/delete/size/' . self::SIZE_MEDIUM);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('DELETE', $response['method']);
        $this->assertGreaterThanOrEqual(self::SIZE_MEDIUM - 200, strlen($client->getResponse()->getContent()));
    }

    /**
     * Test all size categories
     */
    public function testAllSizeCategories(): void
    {
        $client = static::createClient();
        $sizes = [
            'tiny' => self::SIZE_TINY,
            'small' => self::SIZE_SMALL,
            'medium' => self::SIZE_MEDIUM,
            'large' => self::SIZE_LARGE,
        ];

        foreach ($sizes as $category => $size) {
            $client->request('GET', '/api/test/http/get?size=' . $size);
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
            $response = json_decode($client->getResponse()->getContent(), true);
            $this->assertIsArray($response);
            $this->assertEquals($category, $response['response_info']['size_category']);
        }
    }

    /**
     * Test request body parsing for POST
     */
    public function testRequestBodyParsing(): void
    {
        $client = static::createClient();

        $testData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'data' => str_repeat('x', 100)
        ];

        $client->request('POST', '/api/test/http/post', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($testData));
        
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('request_info', $response);
        $this->assertArrayHasKey('parsed_body', $response['request_info']);
        $this->assertEquals('Test User', $response['request_info']['parsed_body']['name']);
    }

    /**
     * Test query parameters handling
     */
    public function testQueryParameters(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/http/get?param1=value1&param2=value2&size=' . self::SIZE_SMALL);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('request_info', $response);
        $this->assertArrayHasKey('query_params', $response['request_info']);
        $this->assertEquals('value1', $response['request_info']['query_params']['param1']);
        $this->assertEquals('value2', $response['request_info']['query_params']['param2']);
    }

    /**
     * Test very large request body (1MB)
     */
    public function testVeryLargeRequestBody(): void
    {
        $client = static::createClient();

        $largeBody = str_repeat('x', self::SIZE_XLARGE);
        $client->request('POST', '/api/test/http/post?response_size=' . self::SIZE_SMALL, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['data' => $largeBody]));
        
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_XLARGE, $response['request_info']['body_size']);
    }

    /**
     * Test very large response (1MB)
     */
    public function testVeryLargeResponse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/http/get?size=' . self::SIZE_XLARGE);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(self::SIZE_XLARGE - 200, strlen($client->getResponse()->getContent()));
    }
}

