<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportAnalysisResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportAnalysisResult>
 */
class ImportAnalysisResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportAnalysisResult::class);
    }
}
