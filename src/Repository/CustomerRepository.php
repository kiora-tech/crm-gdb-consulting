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
        $qb = $this->createQueryBuilder('c');
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

    /**
     * @return Query<int, Customer>
     */
    public function search(CustomerSearchData $search): Query
    {
        $query = $this->getQueryBuilder()
            ->leftJoin('c.contacts', 'co')
            ->leftJoin('c.energies', 'e')
            ->distinct();

        if (!empty($search->name)) {
            $query
                ->andWhere('LOWER(c.name) LIKE LOWER(:name)')
                ->setParameter('name', '%'.$search->name.'%');
        }

        if (!empty($search->leadOrigin)) {
            $query
                ->andWhere('LOWER(c.leadOrigin) LIKE LOWER(:leadOrigin)')
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
                ->andWhere('(LOWER(co.firstName) LIKE LOWER(:contactName) OR LOWER(co.lastName) LIKE LOWER(:contactName))')
                ->setParameter('contactName', '%'.$search->contactName.'%');
        }

        if (!empty($search->address)) {
            $query
                ->andWhere('LOWER(c.address) LIKE LOWER(:address)')
                ->setParameter('address', '%'.$search->address.'%');
        }

        if (!empty($search->action)) {
            $query
                ->andWhere('LOWER(c.action) LIKE LOWER(:action)')
                ->setParameter('action', '%'.$search->action.'%');
        }

        if (!empty($search->worth)) {
            $query
                ->andWhere('LOWER(c.worth) LIKE LOWER(:worth)')
                ->setParameter('worth', '%'.$search->worth.'%');
        }

        if (!empty($search->commision)) {
            $query
                ->andWhere('LOWER(c.commision) LIKE LOWER(:commision)')
                ->setParameter('commision', '%'.$search->commision.'%');
        }

        if (!empty($search->margin)) {
            $query
                ->andWhere('LOWER(c.margin) LIKE LOWER(:margin)')
                ->setParameter('margin', '%'.$search->margin.'%');
        }

        if (!empty($search->companyGroup)) {
            $query
                ->andWhere('LOWER(c.companyGroup) LIKE LOWER(:companyGroup)')
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
        //
        // LOGIQUE DE FILTRAGE:
        // Cas 1: Filtre "avant" uniquement (contractEndBefore = 31/12/2025)
        //   - Trouve les clients avec au moins un contrat expirant <= 31/12/2025
        //   - EXCLUT les clients qui ont un contrat plus récent expirant après cette date
        //   - Exemple: Client avec contrat au 30/06/2025 et 15/03/2026 -> EXCLU (a un contrat en 2026)
        //   - Exemple: Client avec contrat au 30/06/2025 uniquement -> INCLUS
        //
        // Cas 2: Filtre "après" uniquement (contractEndAfter = 01/01/2025)
        //   - Trouve les clients avec au moins un contrat expirant >= 01/01/2025
        //   - Utile pour trouver les contrats à renouveler à partir d'une date
        //
        // Cas 3: Les deux filtres (periode entre deux dates)
        //   - Trouve les clients avec au moins un contrat dans cette période
        //   - EXCLUT les clients qui ont un contrat plus récent après la période
        if ($search->contractEndBefore || $search->contractEndAfter) {
            $query->andWhere('e.contractEnd IS NOT NULL');

            // Filtrer par la période demandée
            if ($search->contractEndBefore) {
                $query->andWhere('e.contractEnd <= :expirationDateBefore')
                    ->setParameter('expirationDateBefore', $search->contractEndBefore);
            }

            if ($search->contractEndAfter) {
                $query->andWhere('e.contractEnd >= :expirationDateAfter')
                    ->setParameter('expirationDateAfter', $search->contractEndAfter);
            }

            // Exclusion intelligente: si on filtre avec "contractEndBefore",
            // on exclut les clients qui ont un contrat plus récent que cette date.
            // Cela permet de trouver les clients dont le dernier contrat expire avant la date cible.
            if ($search->contractEndBefore) {
                // Créer un sous-query pour vérifier s'il existe un contrat plus récent
                $subQuery = $this->createQueryBuilder('c2')
                    ->select('1')
                    ->join('c2.energies', 'e2')
                    ->where('c2.id = c.id')
                    ->andWhere('e2.contractEnd IS NOT NULL')
                    ->andWhere('e2.contractEnd > :expirationDateBefore')
                    ->getDQL();

                $query->andWhere($query->expr()->not($query->expr()->exists($subQuery)));
            }

            $query->orderBy('e.contractEnd', 'ASC');
        }

        if (!empty($search->code)) {
            $query
                ->andWhere('LOWER(e.code) LIKE LOWER(:code)')
                ->setParameter('code', '%'.$search->code.'%');
        }

        if (!empty($search->energyProvider)) {
            $query
                ->andWhere('e.energyProvider = :energyProvider')
                ->setParameter('energyProvider', $search->energyProvider);
        }

        /** @var Query<int, Customer> $result */
        $result = $query->getQuery();

        return $result;
    }
}
