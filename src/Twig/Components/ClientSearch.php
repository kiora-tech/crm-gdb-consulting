<?php

namespace App\Twig\Components;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ClientSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return Customer[]
     */
    public function getResults(): array
    {
        if (empty($this->query)) {
            return [];
        }

        $repository = $this->entityManager->getRepository(Customer::class);
        // S'assurer que la méthode existe avant de l'appeler
        if (!method_exists($repository, 'getQueryBuilder')) {
            return [];
        }

        /** @var Customer[] $result */
        $result = $repository->getQueryBuilder()
            ->leftJoin('c.energies', 'e')
            ->leftJoin('c.contacts', 'co')
            ->leftJoin('e.energyProvider', 'p')
            ->distinct()
            ->andWhere(
                'LOWER(c.name) LIKE LOWER(:query) OR
                LOWER(e.type) LIKE LOWER(:query) OR
                LOWER(e.code) LIKE LOWER(:query) OR
                LOWER(p.name) LIKE LOWER(:query) OR
                LOWER(co.lastName) LIKE LOWER(:query) OR
                LOWER(co.firstName) LIKE LOWER(:query) OR
                LOWER(co.email) LIKE LOWER(:query) OR
                co.phone LIKE :query OR
                LOWER(co.position) LIKE LOWER(:query) OR
                c.siret LIKE :query
                '
            )
            ->setParameter('query', '%'.$this->query.'%')
            // Ordre de priorité: SIRET exact > SIRET partiel > autres champs
            ->orderBy('CASE WHEN c.siret = :exact_siret THEN 0 WHEN c.siret LIKE :start_siret THEN 1 ELSE 2 END', 'ASC')
            ->setParameter('exact_siret', $this->query)
            ->setParameter('start_siret', $this->query.'%')
            // Tri secondaire par nom pour les résultats de même priorité
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
