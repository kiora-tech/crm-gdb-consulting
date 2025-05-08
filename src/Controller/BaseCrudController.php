<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class BaseCrudController extends AbstractController
{
    use CrudTrait;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly PaginatorInterface $paginator,
    ) {
    }

    /**
     * @phpstan-return class-string
     */
    abstract protected function getEntityClass(): string;

    /**
     * Replace Entity form namespace by Form.
     *
     * @return class-string<object>
     */
    protected function getFormTypeClass(): string
    {
        $className = str_replace('\Entity\\', '\Form\\', $this->getEntityClass()).'Type';

        // Cette conversion est sûre car nous construisons le nom de classe à partir d'un nom de classe existant
        /* @var class-string<object> */
        return $className;
    }

    /**
     * @return ObjectRepository<object>
     */
    protected function getRepository(): ObjectRepository
    {
        return $this->entityManager->getRepository($this->getEntityClass());
    }

    protected function customizeQueryBuilder(QueryBuilder $qb): void
    {
        // À surcharger dans les contrôleurs enfants si besoin
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getColumns(): array
    {
        return [
            ['field' => 'id', 'label' => 'id', 'sortable' => false],
        ];
    }

    /**
     * @return array<string, string|bool|array<string, mixed>>
     */
    protected function getRoute(): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            'edit' => $routePrefix.'_edit',
            'delete' => $routePrefix.'_delete',
            'show' => false,
        ];
    }
}
