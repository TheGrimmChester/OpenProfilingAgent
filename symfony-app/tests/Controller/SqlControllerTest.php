<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SqlControllerTest extends WebTestCase
{
    public function testPdoEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/sql/pdo');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('users', $response);
    }

    public function testMysqliEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/sql/mysqli');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('products', $response);
    }

    public function testPreparedStatementsEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/sql/prepared');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('orders', $response);
    }
}

