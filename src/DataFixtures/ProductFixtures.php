<?php

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('de_DE');

        // + more random products
        foreach (range(1, 10) as $i) {
            $product = (new Product())
                ->setName($faker->words(3, true))
                ->setDescription($faker->sentence(12))
                ->setPrice($faker->randomFloat(2, 4, 199))
                ->setCategory($i%2 ? 'books' : 'games')
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable())
                ->setIsActive($faker->boolean(80));
            $manager->persist($product);
        }

        $manager->flush();
    }
}
