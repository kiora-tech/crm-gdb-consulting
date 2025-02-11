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

    /**
     * Get the CSRF token for deletion
     */
    public function getDeleteToken(): string
    {
        if (!$this->entity || !method_exists($this->entity, 'getId')) {
            return '';
        }

        return $this->entity->getId() ? 'delete' . $this->entity->getId() : '';
    }

    /**
     * Vérifie si une action spécifique est disponible
     */
    public function isActionAvailable(string $action): bool
    {
        $route = $action . 'Route';
        return $this->$route !== null && $this->{'can' . ucfirst($action)};
    }
}