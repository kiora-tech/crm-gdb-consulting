<?php

namespace App\Twig\Components;

use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent]
/**
 * Table component for displaying paginated data with sortable columns.
 */
class Table
{
    /**
     * @var PaginationInterface<int, mixed>
     */
    public PaginationInterface $paginator;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $columns = [];

    /**
     * @var array<string, mixed>
     */
    public array $options = [];

    /**
     * @var array<string, string>|null
     */
    public ?array $currentSort = null;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Process and prepare component data before mounting.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        // Initialiser le tri actuel depuis la requête
        $request = $this->requestStack->getCurrentRequest();
        $sort = $request?->query->get('sort');
        $order = $request?->query->get('order', 'asc');

        // S'assurer que order est une chaîne avant de l'utiliser avec strtolower
        $orderStr = is_string($order) ? strtolower($order) : 'asc';

        if ($sort) {
            $this->currentSort = [
                'field' => (string) $sort,
                'order' => $orderStr,
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
                'delete' => [],
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
                    $data['columns'][$key]['sortAlias'] = 'e.'.$column['field'];
                }
            }
        }

        return $data;
    }

    public function getOrder(string $field): string
    {
        if ($this->currentSort && $this->currentSort['field'] === $field) {
            return 'asc' === $this->currentSort['order'] ? 'desc' : 'asc';
        }

        return 'asc';
    }

    public function getSortIcon(string $field): string
    {
        if (!$this->currentSort || $this->currentSort['field'] !== $field) {
            return 'bi-sort';
        }

        return 'asc' === $this->currentSort['order'] ? 'bi-sort-down' : 'bi-sort-up';
    }

    /**
     * Get the sortable field name for a column.
     *
     * @param array<string, mixed> $column
     */
    public function getSortableField(array $column): string
    {
        $field = $column['field'] ?? '';

        return $column['sortAlias'] ?? 'e.'.$field;
    }
}
