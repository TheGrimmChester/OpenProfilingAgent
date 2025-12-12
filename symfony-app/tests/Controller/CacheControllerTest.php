<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CacheControllerTest extends WebTestCase
{
    public function testRedisCacheEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/cache/redis');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('stored_value', $response);
    }

    public function testApcuCacheEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/cache/apcu');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
    }

    public function testMixedCacheEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/cache/mixed');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('results', $response);
    }
}

