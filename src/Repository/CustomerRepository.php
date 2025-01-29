<?php

namespace App\Repository;

use App\Data\CustomerSearchData;
use App\Entity\Customer;
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

    public function search(CustomerSearchData $search): Query
    {
        $query = $this->createQueryBuilder('c');

        if (!empty($search->name)) {
            $query = $query
                ->andWhere('c.name LIKE :name')
                ->setParameter('name', '%'.$search->name.'%');
        }

        if($search->status) {
            $query = $query
                ->andWhere('c.status = :status')
                ->setParameter('status', $search->status);
        }

        // search on first and last name of contact
        if (!empty($search->contactName)) {
            $query = $query
                ->join('c.contacts', 'co')
                ->andWhere('(co.firstName LIKE :contactName OR co.lastName LIKE :contactName)')
                ->setParameter('contactName', '%'.$search->contactName.'%');
        }

        return $query
            ->orderBy('c.'.$search->sort, $search->order)
            ->getQuery();
    }
}
