<?php

namespace App\Repository;

use App\Data\ContactSearchData;
use App\Entity\Contact;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
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
     * find a contact by email or number link to a customer.
     *
     * @param string $contactName (firstName and lastName contatenated)
     */
    public function findContactByCustomerAndEmailOrNumber(Customer $customer, string $contactName, ?string $email, ?string $number): ?Contact
    {
        if (null === $customer->getId()) {
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

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('cus')
            ->join('c.customer', 'cus');
    }

    public function search(ContactSearchData $search): Query
    {
        $query = $this->getQueryBuilder();

        // Filter by contact first name
        if (!empty($search->firstName)) {
            $query->andWhere('c.firstName LIKE :firstName')
                ->setParameter('firstName', '%'.$search->firstName.'%');
        }

        // Filter by contact last name
        if (!empty($search->lastName)) {
            $query->andWhere('c.lastName LIKE :lastName')
                ->setParameter('lastName', '%'.$search->lastName.'%');
        }

        // Filter by contact email
        if (!empty($search->email)) {
            $query->andWhere('c.email LIKE :email')
                ->setParameter('email', '%'.$search->email.'%');
        }

        // Filter by lead origin (from customer)
        if (!empty($search->leadOrigin)) {
            $query->andWhere('cus.leadOrigin LIKE :leadOrigin')
                ->setParameter('leadOrigin', '%'.$search->leadOrigin.'%');
        }

        // Filter by customer's contract expiration dates
        //
        // LOGIQUE DE FILTRAGE:
        // Cas 1: Filtre "avant" uniquement (contractEndBefore = 31/12/2025)
        //   - Trouve les contacts dont le client a au moins un contrat expirant <= 31/12/2025
        //   - EXCLUT les contacts dont le client a un contrat plus récent expirant après cette date
        //   - Exemple: Contact dont le client a contrat au 30/06/2025 et 15/03/2026 -> EXCLU (client a un contrat en 2026)
        //   - Exemple: Contact dont le client a contrat au 30/06/2025 uniquement -> INCLUS
        //
        // Cas 2: Filtre "après" uniquement (contractEndAfter = 01/01/2025)
        //   - Trouve les contacts dont le client a au moins un contrat expirant >= 01/01/2025
        //   - Utile pour trouver les contrats à renouveler à partir d'une date
        //
        // Cas 3: Les deux filtres (periode entre deux dates)
        //   - Trouve les contacts dont le client a au moins un contrat dans cette période
        //   - EXCLUT les contacts dont le client a un contrat plus récent après la période
        if ($search->contractEndBefore || $search->contractEndAfter) {
            // Use EXISTS subquery to check for contracts in the date range
            // This avoids GROUP BY issues with multiple energies per customer

            $subQueryBuilder = $this->getEntityManager()->createQueryBuilder();
            $subQueryBuilder->select('1')
                ->from('App\Entity\Energy', 'e_sub')
                ->where('e_sub.customer = cus')
                ->andWhere('e_sub.contractEnd IS NOT NULL');

            if ($search->contractEndBefore) {
                $subQueryBuilder->andWhere('e_sub.contractEnd <= :expirationDateBefore');
                $query->setParameter('expirationDateBefore', $search->contractEndBefore);
            }

            if ($search->contractEndAfter) {
                $subQueryBuilder->andWhere('e_sub.contractEnd >= :expirationDateAfter');
                $query->setParameter('expirationDateAfter', $search->contractEndAfter);
            }

            $query->andWhere($query->expr()->exists($subQueryBuilder->getDQL()));

            // Exclusion intelligente: si on filtre avec "contractEndBefore",
            // on exclut les contacts dont le client a un contrat plus récent que cette date.
            // Cela permet de trouver les contacts dont le client a son dernier contrat expirant avant la date cible.
            if ($search->contractEndBefore) {
                // Créer un sous-query pour vérifier s'il existe un contrat plus récent
                $subQuery2 = $this->getEntityManager()->createQueryBuilder();
                $subQuery2->select('1')
                    ->from('App\Entity\Energy', 'e_newer')
                    ->where('e_newer.customer = cus')
                    ->andWhere('e_newer.contractEnd IS NOT NULL')
                    ->andWhere('e_newer.contractEnd > :expirationDateBefore');

                $query->andWhere($query->expr()->not($query->expr()->exists($subQuery2->getDQL())));
            }
        }

        // Apply sorting if specified
        if (!empty($search->sort)) {
            $query->orderBy($search->sort, $search->order ?? 'ASC');
        }

        return $query->getQuery();
    }
}
