<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Energy;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/new/{customer?}', name: '_new', methods: ['GET', 'POST'], priority: 1)]
    public function new(Request $request, ?Customer $customer = null): Response
    {
        $entity = new ($this->getEntityClass());
        $entity->setCustomer($customer);

        // Si un type d'énergie est spécifié dans la requête, on l'applique à l'entité
        if ($this->getEntityClass() === Energy::class && $request->query->has('energyType')) {
            $energyType = $request->query->get('energyType');
            if (in_array($energyType, ['ELEC', 'GAZ'])) {
                $entity->setType(\App\Entity\EnergyType::from($energyType));
            }
        }

        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer)
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            if ($customer) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
            }

            return $this->redirectToRoute($this->getBaseRouteName().'_index');
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $entity));
    }

    #[Route('/modal/new/{customer}', name: '_modal_new', methods: ['GET', 'POST'])]
    public function modalNew(Request $request, Customer $customer): Response
    {
        $entity = new ($this->getEntityClass());
        $entity->setCustomer($customer);

        // Si un type d'énergie est spécifié dans la requête, on l'applique à l'entité
        if ($this->getEntityClass() === Energy::class && $request->query->has('energyType')) {
            $energyType = $request->query->get('energyType');
            if (in_array($energyType, ['ELEC', 'GAZ'])) {
                $entity->setType(\App\Entity\EnergyType::from($energyType));
            }
        }

        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer)
        ]);

        // Traiter la soumission du formulaire
        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->entityManager->persist($entity);
                $this->entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'redirect' => $this->generateUrl('app_customer_show', ['id' => $customer->getId()])
                    ]);
                }

                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
            }
        }

        // Ajout des variables pour le template spécifique
        $vars = [
            'form' => $form->createView(),
            'customer' => $customer,
            'page_prefix' => $this->getPagePrefix()
        ];

        if (method_exists($this, 'getModalFormVars')) {
            $vars = array_merge($vars, $this->getModalFormVars($form, $entity));
        }

        return $this->render('crud/_modal_form.html.twig', $vars);
    }
}
