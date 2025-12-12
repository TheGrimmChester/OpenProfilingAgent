<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ComprehensiveControllerTest extends WebTestCase
{
    public function testComprehensiveEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/comprehensive');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('results', $response);
        $this->assertArrayHasKey('sql', $response['results']);
        $this->assertArrayHasKey('cache', $response['results']);
        $this->assertArrayHasKey('curl', $response['results']);
        $this->assertArrayHasKey('file', $response['results']);
    }
}

