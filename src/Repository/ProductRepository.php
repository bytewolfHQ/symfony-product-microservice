<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function getPaginated(bool $onlyActive, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($onlyActive) {
            $qb->andWhere('p.isActive = :active')
                ->setParameter('active', true);
        }

        return $qb
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(max(0, $page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{
     *   category?: string,
     *   minPrice?: float|int,
     *   maxPrice?: float|int,
     *   isActive?: bool|int|string
     * } $filters
     * @param array{field?: string, direction?: 'ASC'|'DESC'} $sort
     */
    public function findByCriteria(array $filters = [], array $sort = []): array
    {
        $qb = $this->createQueryBuilder('p');

        if (array_key_exists('isActive', $filters)) {
            $qb->andWhere('p.isActive = :active')
                ->setParameter('active', (bool) $filters['isActive']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $filters['category']);
        }

        if (isset($filters['minPrice'])) {
            $qb->andWhere('p.price >= :minPrice')
                ->setParameter('minPrice', (float) $filters['minPrice']);
        }

        if (isset($filters['maxPrice'])) {
            $qb->andWhere('p.price <= :maxPrice')
                ->setParameter('maxPrice', (float) $filters['maxPrice']);
        }

        $allowed = ['createdAt', 'updatedAt', 'price', 'name', 'category'];
        $field   = in_array($sort['field'] ?? '', $allowed, true) ? ('p.' . $sort['field']) : 'p.createdAt';
        $dir     = strtoupper($sort['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        return $qb->orderBy($field, $dir)
            ->getQuery()
            ->getResult();
    }

    public function countAll(bool $onlyActive = true, array $filters = []): int
    {
        $query = $this->createQueryBuilder('p')->select('COUNT(p.id)');
        if ($onlyActive) {
            $query->andWhere('p.isActive = :active')
                ->setParameter('active', true);
        }
        $this->applyFilters($query, $filters);
        return (int) $query->getQuery()->getSingleScalarResult();
    }

    private function applyFilters(QueryBuilder $query, array $filters): void
    {
        if (isset($filters['category'])) $query->andWhere('p.category = :c')->setParameter('c', $filters['category']);
        if (isset($filters['minPrice'])) $query->andWhere('p.price >= :min')->setParameter('min', $filters['minPrice']);
        if (isset($filters['maxPrice'])) $query->andWhere('p.price <= :max')->setParameter('max', $filters['maxPrice']);
        if (isset($filters['isActive'])) $query->andWhere('p.isActive > :a')->setParameter('a', (bool)$filters['isActive']);
    }
}
