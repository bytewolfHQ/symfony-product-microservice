<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductControllerTest extends WebTestCase
{
    public function testList(): void
    {
        $client = static::createClient();
        // Seed once if no data set exists (or fixture with test load)
        $client->request('GET', '/api/products');
        $this->assertContains($client->getResponse()->getStatusCode(), [200, 204]);
    }
}
