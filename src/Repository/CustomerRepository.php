<?php

namespace App\Repository;

use App\Data\CustomerSearchData;
use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private TokenStorageInterface $tokenStorage,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($registry, Customer::class);
    }

    public function getQueryBuilder(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('co')
            ->addSelect('e')
            ->leftJoin('c.contacts', 'co')
            ->leftJoin('c.energies', 'e');
        $user = $this->tokenStorage->getToken()?->getUser();

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return $qb;
        }

        if ($user instanceof User) {
            return $qb
                ->andWhere('c.user = :login_user OR c.user IS NULL')
                ->setParameter('login_user', $user);
        }

        return $qb;
    }

    public function search(CustomerSearchData $search): Query
    {
        $query = $this->getQueryBuilder();

        if (!empty($search->name)) {
            $query
                ->andWhere('c.name LIKE :name')
                ->setParameter('name', '%'.$search->name.'%');
        }

        if (!empty($search->leadOrigin)) {
            $query
                ->andWhere('c.leadOrigin LIKE :leadOrigin')
                ->setParameter('leadOrigin', '%'.$search->leadOrigin.'%');
        }

        if ($search->status) {
            $query
                ->andWhere('c.status = :status')
                ->setParameter('status', $search->status);
        }

        if ($search->origin) {
            $query
                ->andWhere('c.origin = :origin')
                ->setParameter('origin', $search->origin);
        }

        if (!empty($search->contactName)) {
            $query
                ->andWhere('(co.firstName LIKE :contactName OR co.lastName LIKE :contactName)')
                ->setParameter('contactName', '%'.$search->contactName.'%');
        }

        if (!empty($search->address)) {
            $query
                ->andWhere('c.address LIKE :address')
                ->setParameter('address', '%'.$search->address.'%');
        }

        if (!empty($search->action)) {
            $query
                ->andWhere('c.action LIKE :action')
                ->setParameter('action', '%'.$search->action.'%');
        }

        if (!empty($search->worth)) {
            $query
                ->andWhere('c.worth LIKE :worth')
                ->setParameter('worth', '%'.$search->worth.'%');
        }

        if (!empty($search->commision)) {
            $query
                ->andWhere('c.commision LIKE :commision')
                ->setParameter('commision', '%'.$search->commision.'%');
        }

        if (!empty($search->margin)) {
            $query
                ->andWhere('c.margin LIKE :margin')
                ->setParameter('margin', '%'.$search->margin.'%');
        }

        if (!empty($search->companyGroup)) {
            $query
                ->andWhere('c.companyGroup LIKE :companyGroup')
                ->setParameter('companyGroup', '%'.$search->companyGroup.'%');
        }

        if (!empty($search->siret)) {
            $query
                ->andWhere('c.siret LIKE :siret')
                ->setParameter('siret', '%'.$search->siret.'%');
        }

        if ($search->user) {
            $query
                ->andWhere('c.user = :user')
                ->setParameter('user', $search->user);
        }

        if ($search->unassigned) {
            $query
                ->andWhere('c.user IS NULL');
        }

        // Filtre par contrats expirants
        // Logique intelligente :
        // - Si on cherche dans le futur uniquement : on ne montre que les contrats actifs
        // - Si on cherche dans le passé : on montre tous les contrats
        // - Si on cherche une période mixte : on montre tout
        if ($search->contractEndBefore || $search->contractEndAfter) {
            $query->andWhere('e.contractEnd IS NOT NULL');

            $now = new \DateTime();
            $searchingFutureOnly = false;

            // Déterminer si on cherche uniquement dans le futur
            if ($search->contractEndAfter && $search->contractEndAfter > $now) {
                // Si la date de début est dans le futur
                $searchingFutureOnly = true;
            } elseif ($search->contractEndAfter && $search->contractEndBefore) {
                // Si on a les deux dates et que la période est entièrement dans le futur
                if ($search->contractEndAfter > $now && $search->contractEndBefore > $now) {
                    $searchingFutureOnly = true;
                }
            } elseif (!$search->contractEndAfter && $search->contractEndBefore && $search->contractEndBefore > $now) {
                // Si on a seulement une date de fin dans le futur
                $searchingFutureOnly = true;
            }

            // Appliquer le filtre des contrats actifs uniquement si on cherche dans le futur
            if ($searchingFutureOnly) {
                $query->andWhere('e.contractEnd > :now')
                    ->setParameter('now', $now);
            }

            if ($search->contractEndBefore) {
                $query->andWhere('e.contractEnd <= :expirationDateBefore')
                    ->setParameter('expirationDateBefore', $search->contractEndBefore);
            }

            if ($search->contractEndAfter) {
                $query->andWhere('e.contractEnd >= :expirationDateAfter')
                    ->setParameter('expirationDateAfter', $search->contractEndAfter);
            }

            $query->orderBy('e.contractEnd', 'ASC');
        }

        if (!empty($search->code)) {
            $query
                ->andWhere('e.code LIKE :code')
                ->setParameter('code', '%'.$search->code.'%');
        }

        if (!empty($search->energyProvider)) {
            $query
                ->andWhere('e.energyProvider = :energyProvider')
                ->setParameter('energyProvider', $search->energyProvider);
        }

        return $query
            ->getQuery();
    }
}
