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
            ->andWhere(
                'c.name LIKE :query OR 
                e.type LIKE :query OR 
                e.code LIKE :query OR 
                p.name LIKE :query OR
                co.lastName LIKE :query OR
                co.firstName LIKE :query OR
                co.email LIKE :query OR
                co.phone LIKE :query OR
                co.position LIKE :query OR
                c.siret LIKE :query
                '
            )
            ->leftJoin('e.energyProvider', 'p')
            ->setParameter('query', '%'.$this->query.'%')
            // Ordre de priorité: SIRET exact > SIRET partiel > autres champs
            ->orderBy('CASE WHEN c.siret = :exact_siret THEN 0 WHEN c.siret LIKE :start_siret THEN 1 ELSE 2 END', 'ASC')
            ->setParameter('exact_siret', $this->query)
            ->setParameter('start_siret', $this->query.'%')
            // Tri secondaire par nom pour les résultats de même priorité
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
