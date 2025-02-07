<?php

namespace App\Service;

use Doctrine\ORM\Query;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @template TKey of array-key
 * @template TValue
 */
class PaginationService
{
    private PaginatorInterface $paginator;

    public function __construct(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * @return PaginationInterface<int, mixed>
     */
    public function paginate(Query $query, Request $request, int $defaultLimit = 10, string $pageParamName = 'page'): PaginationInterface
    {
        $page = $request->query->getInt($pageParamName, 1);
        $limit = $request->query->getInt('limit', $defaultLimit);

        return $this->paginator->paginate(
            $query,
            $page,
            $limit
        );
    }
}
