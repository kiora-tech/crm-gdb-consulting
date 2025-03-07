<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * find a contact by email or number link to a customer
     * @param Customer $customer
     * @param string $contactName (firstName and lastName contatenated)
     * @param ?string $email
     * @param ?string $number
     * @return Contact
     */
    public function findContactByCustomerAndEmailOrNumber(Customer $customer,string $contactName, ?string $email, ?string $number): ?Contact
    {
        if ($customer->getId() === null) {
            return null; // Ou gérez cette situation différemment
        }

        $qb = $this->createQueryBuilder('c')
            ->where('c.customer = :customer')
            ->andWhere('(c.email = :email OR c.mobilePhone = :number OR c.phone = :number)')
            ->andWhere('CONCAT(c.firstName, \' \', c.lastName) = :contactName')
            ->setParameter('customer', $customer->getId()) // Utilisez l'ID au lieu de l'objet entier
            ->setParameter('contactName', $contactName)
            ->setParameter('email', $email)
            ->setParameter('number', $number);

        return $qb->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }
}
