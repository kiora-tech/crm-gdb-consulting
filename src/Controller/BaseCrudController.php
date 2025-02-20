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
        protected readonly PaginatorInterface $paginator
    ) {
    }

    /**
     * @phpstan-return class-string
     */
    protected abstract function getEntityClass(): string;

    /**
     * Replace Entity form namespace by Form
     * @phpstan-return class-string
     */
    protected function getFormTypeClass(): string
    {
        return str_replace('\Entity\\', '\Form\\', $this->getEntityClass()) . 'Type';
    }

    protected function getRepository(): ObjectRepository
    {
        return $this->entityManager->getRepository($this->getEntityClass());
    }

    protected function customizeQueryBuilder(QueryBuilder $qb): void
    {
        // À surcharger dans les contrôleurs enfants si besoin
    }

    protected function getColumns(): array
    {
        return [
            ['field' => 'id', 'label' => 'id', 'sortable' => false]
        ];
    }

    protected function getRoute(): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            'edit' => $routePrefix . '_edit',
            'delete' => $routePrefix . '_delete',
            'show' => false
        ];
    }
}