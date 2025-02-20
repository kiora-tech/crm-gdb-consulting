<?php

namespace App\Controller;

use App\Entity\Energy;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/energy', name: 'app_energy')]
final class EnergyController extends CustomerInfoController
{
   public function getEntityClass(): string
   {
       return Energy::class;
   }

    protected function getFormVars($form, ?object $entity = null): array
    {
        $vars = parent::getFormVars($form, $entity);

        $vars['template_path'] = 'energy/_form.html.twig';

        return $vars;
    }

    protected function getModalFormVars($form, ?object $entity = null): array
    {
        return [
            'template_path' => 'energy/_form.html.twig',
            'back_route' => 'app_customer_index'
        ];
    }
}
