<?php

namespace App\Controller;

trait CrudTrait
{
    protected function getEntityName(): string
    {
        // Extrait le nom de la classe controller (ex: "DocumentTypeController")
        $className = basename(str_replace('\\', '/', static::class));
        // Retire "Controller" et convertit en snake_case
        return $this->toSnakeCase(substr($className, 0, -10));
    }

    protected function getRoutePrefix(): string
    {
        return 'app_' . $this->getEntityName();
    }

    protected function getPagePrefix(): string
    {
        return $this->getEntityName();
    }

    /**
     * Convertit un string CamelCase en snake_case
     */
    private function toSnakeCase(string $input): string
    {
        // DocumentType -> document_type
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    protected function getFormVars($form, ?object $entity = null): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            'form' => $form->createView(),
            'entity' => $entity,
            'back_route' => $routePrefix . '_index',
            'delete_route' => $routePrefix . '_delete',
            'page_prefix' => $this->getPagePrefix(),
            'template_path' => strtolower($this->getEntityName()) . '/_form.html.twig'
        ];
    }

    protected function getIndexVars($pagination, array $columns): array
    {
        return [
            'pagination' => $pagination,
            'columns' => $columns,
            'page_prefix' => $this->getPagePrefix(),
            'page_title' =>  strtolower($this->getEntityName()).'.title',
            'new_route' => $this->getNewRoute(),
            'table_routes' => $this->getRoute()
        ];
    }

    public function getNewRoute(): false|string
    {
        return $this->getRoutePrefix() . '_new';
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