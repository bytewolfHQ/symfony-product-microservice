<?php

namespace App\Tests\Controller;

use App\Entity\Product;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use http\Client;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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

    public function testCreateGetUpdatePatchDelete(): void
    {
        $client = static::createClient();
        $base = [
            'name' => 'Foo '.uniqid(),
            'description' => 'Lorem ipsum dolor sit amet.',
            'price' => 19.99,
            'category' => 'Books',
            'isActive' => true,
        ];

        // CREATE
        $client->request(
            'POST',
            '/api/products',
            server: ['Content-Type'=>'application/json'],
            content: json_encode($base)
        );
        self::assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $created);
        $id = $created['id'];

        // GET ONE
        $client->request('GET', "/api/products/$id");
        self::assertResponseIsSuccessful();
        $got = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($base['name'], $got['name']);

        // PUT
        $put = $base; $put['price'] = 24.99; $put['category'] = 'Other';
        $client->request('PUT', "/api/products/$id", server: ['Content-Type'=>'application/json'], content: json_encode($put));
        self::assertResponseIsSuccessful();
        $afterPut = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(24.99, $afterPut['price']);

        // PATCH
        $patch = ['price' => 29.99];
        $client->request('PATCH', "/api/products/$id", server: ['Content-Type'=>'application/json'], content: json_encode($patch));
        self::assertResponseIsSuccessful();
        $afterPatch = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(29.99, $afterPatch['price']);

        // DELETE
        $client->request('DELETE', "/api/products/$id");
        self::assertResponseStatusCodeSame(204);

        // GET after delete → 404
        $client->request('GET', "/api/products/$id");
        self::assertResponseStatusCodeSame(404);
    }

    public function testListWithFiltersAndPagination(): void
    {
        $client = static::createClient();

        // Seed 1–2 Produkte, damit Filter was findet
        $this->createProduct($client, ['price' => 120.0, 'category' => 'Electronics']);
        $this->createProduct($client, ['price' => 480.0, 'category' => 'Electronics']);

        // Ab hier wie gehabt – jetzt sollte 200 kommen
        $client->request('GET', '/api/products?page=1&limit=5&sort=createdAt,DESC');
        self::assertResponseIsSuccessful();
        $res = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('data', $res);
        self::assertLessThanOrEqual(5, count($res['data']));

        $client->request('GET', '/api/products?category=Electronics&minPrice=10&maxPrice=500&isActive=1');
        self::assertResponseIsSuccessful(); // 200
        $res = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('data', $res);
        foreach ($res['data'] as $row) {
            self::assertSame('Electronics', $row['category']);
            self::assertGreaterThanOrEqual(10, $row['price']);
            self::assertLessThanOrEqual(500, $row['price']);
        }
    }

    /**
     * @throws \JsonException
     */
    private function createProduct(KernelBrowser $client, array $data = []): array
    {
        $payload = array_merge([
            'name'        => 'Phone X',
            'description' => 'Nice device',
            'price'       => 199.0,
            'category'    => 'Electronics',
            'isActive'    => true,
        ], $data);

        $client->request(
            'POST',
            '/api/products',
            [], // parameters
            [], // files
            ['CONTENT_TYPE' => 'application/json'], // server / headers
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);

        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
