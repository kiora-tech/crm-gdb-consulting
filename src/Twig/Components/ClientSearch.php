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

        /** @var Customer[] $result */
        $result = $this->entityManager->getRepository(Customer::class)->createQueryBuilder('c')
            ->where(
                'c.name LIKE :query OR 
                e.type LIKE :query OR 
                e.code LIKE :query OR 
                e.provider LIKE :query OR
                co.lastName LIKE :query OR
                co.firstName LIKE :query OR
                co.email LIKE :query OR
                co.phone LIKE :query OR
                co.position LIKE :query
                '
            )
            ->leftJoin('c.energies', 'e')
            ->leftJoin('c.contacts', 'co')
            ->setParameter('query', '%'.$this->query.'%')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
