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

        $qb = $repository->getQueryBuilder();

        $energySubQuery = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\Energy', 'e')
            ->leftJoin('e.energyProvider', 'p')
            ->where('e.customer = c.id')
            ->andWhere(
                'LOWER(e.type) LIKE LOWER(:query) OR
                LOWER(e.code) LIKE LOWER(:query) OR
                LOWER(p.name) LIKE LOWER(:query)'
            )
            ->getDQL();

        $contactSubQuery = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\Contact', 'co')
            ->where('co.customer = c.id')
            ->andWhere(
                'LOWER(co.lastName) LIKE LOWER(:query) OR
                LOWER(co.firstName) LIKE LOWER(:query) OR
                LOWER(co.email) LIKE LOWER(:query) OR
                co.phone LIKE :query OR
                LOWER(co.position) LIKE LOWER(:query)'
            )
            ->getDQL();

        /** @var Customer[] $result */
        $result = $qb
            ->andWhere(
                'LOWER(c.name) LIKE LOWER(:query) OR
                c.siret LIKE :query OR
                EXISTS ('.$energySubQuery.') OR
                EXISTS ('.$contactSubQuery.')'
            )
            ->setParameter('query', '%'.$this->query.'%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
