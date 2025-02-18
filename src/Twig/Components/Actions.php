<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Actions
{
    public ?string $showRoute = null;
    public ?string $editRoute = null;
    public ?string $deleteRoute = null;
    public mixed $entity;
    public bool $canShow = true;
    public bool $canEdit = true;
    public bool $canDelete = true;
    public array $deleteRouteParams = [];

    public array $actionAttributes = [
        'show' => [],
        'edit' => [],
        'delete' => []
    ];

    /**
     * Get delete route parameters
     */
    public function getDeleteRouteParams(): array
    {
        if (!$this->entity || !method_exists($this->entity, 'getId')) {
            return [];
        }

        return array_merge(
            ['id' => $this->entity->getId()],
            $this->deleteRouteParams
        );
    }

    /**
     * Vérifie si une action spécifique est disponible
     */
    public function isActionAvailable(string $action): bool
    {
        $route = $action . 'Route';
        return $this->$route && $this->{'can' . ucfirst($action)};
    }

    public function getActionAttributes(string $action): array
    {
        return $this->actionAttributes[$action] ?? [];
    }
}
