<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    public const DEFAULT_LIMIT = 500;

    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $em
    ) {}

    /** @return Product[] */
    public function getAll(bool $onlyActive = true): array
    {
        return $this->productRepository->getPaginated($onlyActive, 1, 500);
    }

    public function getPaginated(bool $onlyActive, int $page, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->productRepository->getPaginated($onlyActive, $page, $limit);
    }

    public function getByCriteria(array $filters, array $sorting = []): array
    {
        return $this->productRepository->findByCriteria($filters, $sorting);
    }

    public function countAll(bool $onlyActive = true, array $filters = []): int
    {
        return $this->productRepository->countAll($onlyActive, $filters);
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
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTimeImmutable())
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
