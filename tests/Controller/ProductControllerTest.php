<?php

namespace App\Tests\Controller;

use App\Entity\Product;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductControllerTest extends WebTestCase
{
    public function testList(): void
    {
        $client = static::createClient();

        // Ensure data exists
        $em = static::getContainer()->get(EntityManagerInterface::class);
        if ((int)$em->getRepository(Product::class)->count([]) === 0) {
            $p = (new Product())
                ->setName('Foo')
                ->setDescription('Bar')
                ->setPrice(9.99)
                ->setCategory('Test')
                ->setIsActive(true)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());
            $em->persist($p);
            $em->flush();
        }

        $client->request('GET', '/api/products');

        $this->assertResponseIsSuccessful(); // 2xx ok

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(ProductService::DEFAULT_LIMIT, $payload['meta']['limit']);
        $this->assertIsArray($payload['data']);
        $this->assertGreaterThanOrEqual(1, count($payload['data']));
    }
}
