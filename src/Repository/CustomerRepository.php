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
        $query = $this->getQueryBuilder();

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
            $contactSubQuery = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from('App\Entity\Contact', 'co')
                ->where('co.customer = c.id')
                ->andWhere('(LOWER(co.firstName) LIKE LOWER(:contactName) OR LOWER(co.lastName) LIKE LOWER(:contactName))')
                ->getDQL();
            $query->andWhere($query->expr()->exists($contactSubQuery))
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

        // Filtre par contrats expirants (via subquery EXISTS pour éviter les problèmes de DISTINCT/GROUP BY)
        //
        // LOGIQUE DE FILTRAGE:
        // Cas 1: Filtre "avant" uniquement (contractEndBefore = 31/12/2025)
        //   - Trouve les clients avec au moins un contrat expirant <= 31/12/2025
        //   - EXCLUT les clients qui ont un contrat plus récent expirant après cette date
        //
        // Cas 2: Filtre "après" uniquement (contractEndAfter = 01/01/2025)
        //   - Trouve les clients avec au moins un contrat expirant >= 01/01/2025
        //
        // Cas 3: Les deux filtres (periode entre deux dates)
        //   - Trouve les clients avec au moins un contrat dans cette période
        //   - EXCLUT les clients qui ont un contrat plus récent après la période
        if ($search->contractEndBefore || $search->contractEndAfter) {
            $energySubQb = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from('App\Entity\Energy', 'e_sub')
                ->where('e_sub.customer = c.id')
                ->andWhere('e_sub.contractEnd IS NOT NULL');

            if ($search->contractEndBefore) {
                $energySubQb->andWhere('e_sub.contractEnd <= :expirationDateBefore');
                $query->setParameter('expirationDateBefore', $search->contractEndBefore);
            }

            if ($search->contractEndAfter) {
                $energySubQb->andWhere('e_sub.contractEnd >= :expirationDateAfter');
                $query->setParameter('expirationDateAfter', $search->contractEndAfter);
            }

            $query->andWhere($query->expr()->exists($energySubQb->getDQL()));

            // Exclusion intelligente: exclure les clients qui ont un contrat plus récent
            if ($search->contractEndBefore) {
                $excludeSubQuery = $this->getEntityManager()->createQueryBuilder()
                    ->select('1')
                    ->from('App\Entity\Energy', 'e_excl')
                    ->where('e_excl.customer = c.id')
                    ->andWhere('e_excl.contractEnd IS NOT NULL')
                    ->andWhere('e_excl.contractEnd > :expirationDateBefore')
                    ->getDQL();

                $query->andWhere($query->expr()->not($query->expr()->exists($excludeSubQuery)));
            }

            $query->orderBy('c.name', 'ASC');
        }

        if (!empty($search->code)) {
            $codeSubQuery = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from('App\Entity\Energy', 'e_code')
                ->where('e_code.customer = c.id')
                ->andWhere('LOWER(e_code.code) LIKE LOWER(:code)')
                ->getDQL();
            $query->andWhere($query->expr()->exists($codeSubQuery))
                ->setParameter('code', '%'.$search->code.'%');
        }

        if (!empty($search->energyProvider)) {
            $providerSubQuery = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from('App\Entity\Energy', 'e_prov')
                ->where('e_prov.customer = c.id')
                ->andWhere('e_prov.energyProvider = :energyProvider')
                ->getDQL();
            $query->andWhere($query->expr()->exists($providerSubQuery))
                ->setParameter('energyProvider', $search->energyProvider);
        }

        /** @var Query<int, Customer> $result */
        $result = $query->getQuery();

        return $result;
    }
}
