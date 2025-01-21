<?php

namespace App\Repository;

use App\Entity\ClientSigningDocument;
use App\Entity\Company;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSigningDocument>
 */
class ClientSigningDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSigningDocument::class);
    }

    /**
     * @return ClientSigningDocument[]
     */
    public function findByUser(User $user): array
    {
        /** @var ClientSigningDocument[] $result */
        $result = $this->createQueryBuilder('csd')
            ->join('csd.clientDocument', 'cd')
            ->join('cd.client', 'c')
            ->join('c.assignedCollaborators', 'u')
            ->where('u = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return ClientSigningDocument[]
     */
    public function findByCompany(Company $company): array
    {
        /** @var ClientSigningDocument[] $result */
        $result = $this->createQueryBuilder('csd')
            ->join('csd.clientDocument', 'cd')
            ->join('cd.client', 'c')
            ->join('c.company', 'co')
            ->where('co = :company')
            ->setParameter('company', $company)
            ->getQuery()
            ->getResult();

        return $result;
    }

    //    /**
    //     * @return ClientSigningDocument[] Returns an array of ClientSigningDocument objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ClientSigningDocument
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
