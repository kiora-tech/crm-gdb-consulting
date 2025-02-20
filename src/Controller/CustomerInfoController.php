<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use function Symfony\Component\String\u;

abstract class CustomerInfoController extends AbstractController
{
    use CrudTrait {
        getIndexVars as protected getIndexVarsTrait;
    }

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly PaginatorInterface $paginator
    ) {
    }

    /**
     * @phpstan-return class-string
     * @return string
     */
    protected abstract function getEntityClass(): string;

    /**
     * Replace Entity form namespace by Form
     * @phpstan-return class-string
     * @return string
     */
    protected function getFormTypeClass(): string
    {
        return str_replace('\Entity\\', '\Form\\', $this->getEntityClass()) . 'Type';
    }

    public function getBaseRouteName(): string
    {
        return 'app_' . $this->getName();
    }

    protected function getName(): string
    {
        $shortName = (new \ReflectionClass($this->getEntityClass()))->getShortName();

        return u($shortName)->snake()->toString();
    }


    protected function getRepository(): ObjectRepository
    {
        return $this->entityManager->getRepository($this->getEntityClass());
    }

    #[Route('/new/{customer?}', name: '_new', methods: ['GET', 'POST'], priority: 1)]
    public function new(Request $request, ?Customer $customer = null): Response
    {
        $entity = new ($this->getEntityClass());
        $entity->setCustomer($customer);
        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer)
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            if ($customer) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()],
                    Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute($this->getBaseRouteName().'_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $entity));
    }

    #[Route('/modal/{id}/edit/{customer}', name: '_modal_edit', methods: ['GET', 'POST'])]
    public function modalEdit(Request $request, int $id, ?Customer $customer = null): Response
    {
        $entity = $this->getRepository()->find($id);

        if ($customer) {
            $entity->setCustomer($customer);
        }

        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer)
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            // Retourner une réponse qui fermera la modal et rafraîchira la page
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'redirect' => $this->generateUrl('app_customer_show', ['id' => $customer->getId()])
                ]);
            }

            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }

        $vars = [
            'form' => $form->createView(),
            'customer' => $customer,
            'entity' => $entity,
            'page_prefix' => $this->getPagePrefix()
        ];

        // Permettre aux contrôleurs enfants de personnaliser le template
        if (method_exists($this, 'getModalFormVars')) {
            $vars = array_merge($vars, $this->getModalFormVars($form, $entity));
        }

        return $this->render('crud/_modal_form.html.twig', $vars);
    }

    #[Route('/modal/new/{customer}', name: '_modal_new', methods: ['GET', 'POST'])]
    public function modalNew(Customer $customer): Response
    {
        $entity = new ($this->getEntityClass());
        $entity->setCustomer($customer);
        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer)
        ]);

        // Ajout des variables pour le template spécifique
        $vars = [
            'form' => $form->createView(),
            'customer' => $customer,
            'page_prefix' => $this->getPagePrefix()
        ];


        $vars = array_merge($vars, $this->getModalFormVars($form, $entity));


        return $this->render('crud/_modal_form.html.twig', $vars);
    }

    protected function getModalFormVars($form, ?object $entity = null): array
    {
        return [];
    }

    /**
     * Personnalise la QueryBuilder pour l'index
     */
    protected function customizeQueryBuilder(QueryBuilder $qb): void
    {
        // À surcharger dans les contrôleurs enfants si besoin
    }

    #[Route(name: '_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $qb = $this->entityManager
            ->getRepository($this->getEntityClass())
            ->createQueryBuilder('e');

        $this->customizeQueryBuilder($qb);

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1)
        );

        return $this->render('crud/index.html.twig', $this->getIndexVars(
            $pagination,
            $this->getColumns()
        ));
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

    #[Route('/{id}/edit/{customer?}', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, ?Customer $customer = null): Response
    {
        $entity = $this->getRepository()->find($id);
        
        if ($customer) {
            $entity->setCustomer($customer);
        }

        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer)
        ]);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            
            if ($customer) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
            }
    
            return $this->redirectToRoute($this->getBaseRouteName().'_index', [], Response::HTTP_SEE_OTHER);
        }


        return $this->render('crud/form.html.twig', $this->getFormVars($form, $entity));
    }


    #[Route('/{id}/{customer?}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, ?Customer $customer = null): Response
    {
        $entity = $this->getRepository()->find($id);
        if ($customer) {
            $entity->setCustomer($customer);
        }
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
        if ($customer) {
            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute($this->getBaseRouteName().'_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Generate the form action URL based on the entity and customer
     */
    protected function generateFormAction(object $entity, ?Customer $customer): string
    {
        $route = method_exists($entity, 'getId') && $entity->getId()
            ? $this->getBaseRouteName() . '_edit'
            : $this->getBaseRouteName() . '_new';

        $parameters = [];
        if ($customer) {
            $parameters['customer'] = $customer->getId();
        }

        if (method_exists($entity, 'getId') && $entity->getId()) {
            $parameters['id'] = $entity->getId();
        }

        return $this->generateUrl($route, $parameters);
    }

}
