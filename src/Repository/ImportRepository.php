<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Import;
use App\Entity\ImportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Import>
 */
class ImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Import::class);
    }

    /**
     * Find an import with all related errors and analysis results eagerly loaded.
     */
    public function findOneWithDetails(int $id): ?Import
    {
        /* @var Import|null */
        return $this->createQueryBuilder('i')
            ->leftJoin('i.errors', 'e')
            ->addSelect('e')
            ->leftJoin('i.analysisResults', 'ar')
            ->addSelect('ar')
            ->leftJoin('i.user', 'u')
            ->addSelect('u')
            ->where('i.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all imports that are not in a terminal status (still active/processing).
     *
     * @return array<Import>
     */
    public function findActiveImports(): array
    {
        /* @var array<Import> */
        return $this->createQueryBuilder('i')
            ->leftJoin('i.user', 'u')
            ->addSelect('u')
            ->where('i.status IN (:statuses)')
            ->setParameter('statuses', [
                ImportStatus::PENDING->value,
                ImportStatus::ANALYZING->value,
                ImportStatus::AWAITING_CONFIRMATION->value,
                ImportStatus::PROCESSING->value,
            ])
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find imports that have been processing for longer than the given threshold
     * (potential stuck imports).
     *
     * @return array<Import>
     */
    public function findStuckImports(\DateTimeImmutable $threshold): array
    {
        /* @var array<Import> */
        return $this->createQueryBuilder('i')
            ->leftJoin('i.user', 'u')
            ->addSelect('u')
            ->where('i.status IN (:statuses)')
            ->andWhere('i.startedAt IS NOT NULL')
            ->andWhere('i.startedAt < :threshold')
            ->setParameter('statuses', [
                ImportStatus::ANALYZING->value,
                ImportStatus::PROCESSING->value,
            ])
            ->setParameter('threshold', $threshold)
            ->orderBy('i.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
