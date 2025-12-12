<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CurlControllerTest extends WebTestCase
{
    public function testSimpleCurlEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/curl/simple');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('http_code', $response);
    }

    public function testMultipleCurlEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/curl/multiple');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('results', $response);
        $this->assertCount(3, $response['results']);
    }

    public function testPostCurlEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/curl/post');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
    }
}

