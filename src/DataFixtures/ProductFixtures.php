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

        $examples = [
            ['USB-C Kabel 1m', 'Flexibles USB-C Kabel', 7.99,  'kabel',   true],
            ['Bluetooth Lautsprecher', 'Kompakter Speaker', 29.90, 'audio', true],
            ['Gaming Maus', 'Ergonomische Maus', 39.00, 'zubehör', true],
            ['Gaming Stuhl', 'Ergonomischee Gaming Stuhl', 249.00, 'ausstattung', true],
        ];

        foreach ($examples as [$name, $desc, $price, $cat, $active]) {
            $product = (new Product())
                ->setName($name)
                ->setDescription($desc)
                ->setPrice($price)
                ->setCategory($cat)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable())
                ->setIsActive($active);
            $manager->persist($product);
        }

        // + more random products
        for ($i = 0; $i < 12; $i++) {
            $p = (new Product())
                ->setName($faker->words(3, true))
                ->setDescription($faker->sentence(12))
                ->setPrice($faker->randomFloat(2, 4, 199))
                ->setCategory($faker->randomElement(['audio','kabel','zubehör','haushalt']))
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable())
                ->setIsActive($faker->boolean(80)); // ~80% aktiv
            $manager->persist($p);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['dev', 'demo']; // z.B. 'demo' fürs gezielte Laden
    }
}
