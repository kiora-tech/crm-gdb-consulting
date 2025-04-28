<?php

namespace App\Repository;

use App\Data\ProjectSearchData;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function search(ProjectSearchData $search): Query
    {
        $query = $this->createQueryBuilder('p');

        if (!empty($search->name)) {
            $query->andWhere('p.name LIKE :name')
                ->setParameter('name', '%' . $search->name . '%');
        }

        if (!empty($search->status)) {
            $query->andWhere('p.status = :status')
                ->setParameter('status', $search->status);
        }

        if (!empty($search->startDate)) {
            $query->andWhere('p.startDate >= :startDate')
                ->setParameter('startDate', $search->startDate);
        }

        if (!empty($search->deadline)) {
            $query->andWhere('p.deadline <= :deadline')
                ->setParameter('deadline', $search->deadline);
        }

        if (!empty($search->budget)) {
            $query->andWhere('p.budget = :budget')
                ->setParameter('budget', $search->budget);
        }

        return $query->getQuery();
    }

    public function findUpcomingDeadlines(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.deadline >= :today')
            ->andWhere('p.deadline <= :soon')
            ->setParameter('today', new \DateTime())
            ->setParameter('soon', (new \DateTime())->modify('+7 days'))
            ->orderBy('p.deadline', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findExpiredProjects(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.deadline < :today')
            ->setParameter('today', new \DateTime())
            ->orderBy('p.deadline', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
