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

    public function findBySearchTerm(string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.name LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
