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
        $query = $this->createQueryBuilder('p');
        if ($onlyActive) {
            $query->andWhere('p.isActive = :active')
                ->setParameter('active', true);
        }
        return $query->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(max(0,$page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByCriteria(array $filters, array $sort = ['field'=>'createdAt', 'direction'=>'DESC']): array
    {
        $query = $this->createQueryBuilder('p');
        if (isset($filters['isActive'])) {
            $query->andWhere('p.isActive = :active')
                ->setParameter('active', (bool) $filters['isActive']);
        }
        $this->applyFilters($query, $filters);
        return $query->orderBy('p.' . $sort['field'], $sort['direction'])
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
        if (isset($filters['minPrice'])) $query->andWhere('p.price => :min')->setParameter('min', $filters['minPrice']);
        if (isset($filters['maxPrice'])) $query->andWhere('p.price <= :max')->setParameter('max', $filters['minPrice']);
        if (isset($filters['isActive'])) $query->andWhere('p.isActive > :a')->setParameter('a', (bool)$filters['isActive']);
    }
}
