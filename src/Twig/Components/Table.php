<?php

namespace App\Twig\Components;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;
use Knp\Component\Pager\Pagination\PaginationInterface;

#[AsTwigComponent]
class Table
{
    public PaginationInterface $paginator;
    public array $columns = [];
    public array $options = [];
    public ?array $currentSort = null;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }
    #[PreMount]
    public function preMount(array $data): array
    {
        // Initialiser le tri actuel depuis la requête
        $request = $this->requestStack->getCurrentRequest();
        $sort = $request?->query->get('sort');
        $order = strtolower($request?->query->get('order', 'asc'));

        if ($sort) {
            $this->currentSort = [
                'field' => $sort,
                'order' => $order
            ];
        }

        // Configurer les options par défaut et valider
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'routes' => [
                'show' => null,
                'edit' => null,
                'delete' => null,
            ],
            'tableClass' => 'table table-hover',
            'theadClass' => 'table-primary',
            'showActions' => true,
            'actions' => [
                'show' => true,
                'edit' => true,
                'delete' => true,
            ],
            'actionAttributes' => [
                'show' => [],
                'edit' => [],
                'delete' => []
            ],
            'sortable' => true,
        ]);

        $resolver->setRequired(['routes']);

        // S'assurer que 'routes' est un tableau avec des clés optionnelles
        $resolver->setAllowedTypes('routes', 'array');
        $resolver->setDefault('routes', []);

        // Fusionner les routes fournies avec les valeurs par défaut
        if (isset($data['options']['routes'])) {
            $data['options']['routes'] = array_merge(
                ['show' => null, 'edit' => null, 'delete' => null],
                $data['options']['routes']
            );
        }

        // Si des options sont fournies, les fusionner avec les valeurs par défaut
        if (isset($data['options'])) {
            $data['options'] = array_merge(
                $resolver->resolve([]),
                $data['options']
            );
        } else {
            $data['options'] = $resolver->resolve([]);
        }

        // Traiter les colonnes
        if (isset($data['columns'])) {
            foreach ($data['columns'] as $key => $column) {
                // S'assurer que chaque colonne a un alias pour le tri si elle est triable
                if (!isset($column['sortAlias']) && isset($column['sortable']) && $column['sortable']) {
                    $data['columns'][$key]['sortAlias'] = 'e.' . $column['field'];
                }
            }
        }

        return $data;
    }

    public function getOrder(string $field): string
    {
        if ($this->currentSort && $this->currentSort['field'] === $field) {
            return $this->currentSort['order'] === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    public function getSortIcon(string $field): string
    {
        if (!$this->currentSort || $this->currentSort['field'] !== $field) {
            return 'bi-sort';
        }

        return $this->currentSort['order'] === 'asc' ? 'bi-sort-down' : 'bi-sort-up';
    }

    public function getSortableField(array $column): string
    {
        return $column['sortAlias'] ?? 'e.' . $column['field'];
    }
}