<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use function Symfony\Component\String\u;

abstract class CustomerInfoController extends AbstractController
{
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

    #[Route('/new/{customer?}', name: '_new', methods: ['GET', 'POST'])]
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

        return $this->render($this->getName().'/new.html.twig', [
            'entity' => $entity,
            'form' => $form,
        ]);
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

        // Personnalisation de la query
        $this->customizeQueryBuilder($qb);

        // Gestion du tri
        $sortField = $request->query->get('sort');
        $sortDirection = $request->query->get('order', 'asc');

        $pagination = $this->paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render($this->getName().'/index.html.twig', [
            'entities' => $pagination,
            'baseRouteName' => $this->getBaseRouteName(),
            'entityName' => $this->getName(),
            'currentSort' => [
                'field' => $sortField,
                'order' => $sortDirection
            ],
        ]);
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
    
        return $this->render($this->getName().'/edit.html.twig', [
            'entity' => $entity,
            'form' => $form,
            'customer' => $customer, 
        ]);
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
