<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $em
    ) {}

    /** @return Product[] */
    public function getAll(bool $onlyActive = true): array
    {
        $criteria = $onlyActive ? ['isActive' => true] : [];
        return $this->productRepository->findBy($criteria, ['createdAt' => 'DESC']);
    }

    public function getById(int $id): ?Product
    {
        return $this->productRepository->find($id);
    }

    public function create(string $name, string $description, float $price, string $category, bool $isActive): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setDescription($description)
            ->setPrice($price)
            ->setCategory($category)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setIsActive($isActive);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function save(Product $product): void
    {
        $this->em->persist($product);
        $this->em->flush();
    }

    public function delete(Product $product): void
    {
        $this->em->remove($product);
        $this->em->flush();
    }
}
