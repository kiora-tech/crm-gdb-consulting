<?php

namespace App\Repository;

use App\Data\CustomerSearchData;
use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function search(CustomerSearchData $search, ?User $user = null): Query
    {
        $query = $this->createQueryBuilder('c')
            ->addSelect('co')
            ->addSelect('e')
            ->leftJoin('c.contacts', 'co')
            ->leftJoin('c.energies', 'e');;


        if (!empty($search->name)) {
            $query = $query
                ->andWhere('c.name LIKE :name')
                ->setParameter('name', '%' . $search->name . '%');
        }


        if (!empty($search->leadOrigin)) {
            $query = $query
                ->andWhere('c.leadOrigin LIKE :leadOrigin')
                ->setParameter('leadOrigin', '%' . $search->leadOrigin . '%');
        }


        if ($search->status) {
            $query = $query
                ->andWhere('c.status = :status')
                ->setParameter('status', $search->status);
        }


        if (!empty($search->contactName)) {
            $query = $query
                ->andWhere('(co.firstName LIKE :contactName OR co.lastName LIKE :contactName)')
                ->setParameter('contactName', '%' . $search->contactName . '%');
        }


        if (!empty($search->address)) {
            $query = $query
                ->andWhere('c.address LIKE :address')
                ->setParameter('address', '%' . $search->address . '%');
        }


        if (!empty($search->action)) {
            $query = $query
                ->andWhere('c.action LIKE :action')
                ->setParameter('action', '%' . $search->action . '%');
        }


        if (!empty($search->worth)) {
            $query = $query
                ->andWhere('c.worth LIKE :worth')
                ->setParameter('worth', '%' . $search->worth . '%');
        }

        if (!empty($search->commision)) {
            $query = $query
                ->andWhere('c.commision LIKE :commision')
                ->setParameter('commision', '%' . $search->commision . '%');
        }

        if (!empty($search->margin)) {
            $query = $query
                ->andWhere('c.margin LIKE :margin')
                ->setParameter('margin', '%' . $search->margin . '%');
        }

        if (!empty($search->companyGroup)) {
            $query = $query
                ->andWhere('c.companyGroup LIKE :companyGroup')
                ->setParameter('companyGroup', '%' . $search->companyGroup . '%');
        }

        if (!empty($search->siret)) {
            $query = $query
                ->andWhere('c.siret LIKE :siret')
                ->setParameter('siret', '%' . $search->siret . '%');
        }

        if($user) {
            $query = $query
                ->andWhere('c.user = :user OR c.user IS NULL')
                ->setParameter('user', $user);
        }

        if($search->user) {
            $query = $query
                ->andWhere('c.user = :user')
                ->setParameter('user', $search->user);
        }

        // Filtre par contrats expirants
        if ($search->expiringContracts) {
            $now = new \DateTime();
            $expirationDate = (new \DateTime())->modify('+' . $search->expirationPeriod . ' months');

            $query->andWhere('e.contractEnd IS NOT NULL')
                ->andWhere('e.contractEnd >= :now')
                ->andWhere('e.contractEnd <= :expirationDate')
                ->setParameter('now', $now)
                ->setParameter('expirationDate', $expirationDate)
                ->orderBy('e.contractEnd', 'ASC');
        }


        return $query
            ->getQuery();
    }

}
