<?php

namespace App\Repository;

use App\Entity\EnergyProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnergyProvider>
 */
class EnergyProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnergyProvider::class);
    }

    /**
     * @return EnergyProvider[] Returns an array of EnergyProvider objects
     */
    public function findBySearchTerm(string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.name) LIKE LOWER(:term)')
            ->setParameter('term', '%'.$searchTerm.'%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->enableResultCache(3600, 'energy_provider_search_'.md5($searchTerm))
            ->getResult();
    }

    /**
     * @return EnergyProvider[] Returns all energy providers (cached 1h)
     */
    public function findAllCached(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->enableResultCache(3600, 'energy_provider_all')
            ->getResult();
    }
}
