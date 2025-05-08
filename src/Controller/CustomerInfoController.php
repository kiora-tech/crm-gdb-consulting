<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

use function Symfony\Component\String\u;

abstract class CustomerInfoController extends AbstractController
{
    use CrudTrait {
        getIndexVars as protected getIndexVarsTrait;
    }

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly PaginatorInterface $paginator,
    ) {
    }

    /**
     * @phpstan-return class-string
     */
    abstract protected function getEntityClass(): string;

    /**
     * Replace Entity form namespace by Form.
     *
     * @phpstan-return class-string
     */
    protected function getFormTypeClass(): string
    {
        return str_replace('\Entity\\', '\Form\\', $this->getEntityClass()).'Type';
    }

    public function getBaseRouteName(): string
    {
        return 'app_'.$this->getName();
    }

    protected function getName(): string
    {
        $shortName = (new \ReflectionClass($this->getEntityClass()))->getShortName();

        return u($shortName)->snake()->toString();
    }

    /**
     * @phpstan-return ObjectRepository<object>
     */
    protected function getRepository(): ObjectRepository
    {
        return $this->entityManager->getRepository($this->getEntityClass());
    }

    #[Route('/new/{customer?}', name: '_new', methods: ['GET', 'POST'], priority: 1)]
    public function new(Request $request, ?Customer $customer = null): Response
    {
        $entity = new ($this->getEntityClass());
        $entity->setCustomer($customer);

        // Permet aux contrôleurs enfants de personnaliser l'entité avant la création du formulaire
        $this->prepareEntityIfExists($entity, $request);

        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer),
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

        // Gestion des erreurs de formulaire avec Turbo Stream
        if ($form->isSubmitted() && !$form->isValid()) {
            if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                $vars = $this->getFormVars($form, $entity);
                $vars['customer'] = $customer;

                return $this->render('crud/_form_stream.html.twig', $vars, new Response(null, 422, [
                    'Content-Type' => 'text/vnd.turbo-stream.html',
                ]));
            }
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $entity));
    }

    #[Route('/{id}/edit/{customer?}', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, ?Customer $customer = null): Response
    {
        $entity = $this->getRepository()->find($id);

        if ($customer) {
            $entity->setCustomer($customer);
        }

        // Permet aux contrôleurs enfants de personnaliser l'entité avant la création du formulaire
        $this->prepareEntityIfExists($entity, $request);

        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            if ($customer) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute($this->getBaseRouteName().'_index', [], Response::HTTP_SEE_OTHER);
        }

        // Gestion des erreurs de formulaire avec Turbo Stream
        if ($form->isSubmitted() && !$form->isValid()) {
            if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                $vars = $this->getFormVars($form, $entity);
                $vars['customer'] = $customer;

                return $this->render('crud/_form_stream.html.twig', $vars, new Response(null, 422, [
                    'Content-Type' => 'text/vnd.turbo-stream.html',
                ]));
            }
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $entity));
    }

    #[Route('/modal/new/{customer}', name: '_modal_new', methods: ['GET', 'POST'])]
    public function modalNew(Request $request, Customer $customer): Response
    {
        $entity = new ($this->getEntityClass());
        $entity->setCustomer($customer);

        // Permet aux contrôleurs enfants de personnaliser l'entité
        $this->prepareEntityIfExists($entity, $request);

        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer),
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
                        'redirect' => $this->generateUrl('app_customer_show', ['id' => $customer->getId()]),
                    ]);
                }

                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
            }

            // Gestion des erreurs de formulaire avec Turbo Stream
            if ($form->isSubmitted() && !$form->isValid() && TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                $vars = [
                    'form' => $form,
                    'entity' => $entity,
                    'customer' => $customer,
                    'page_prefix' => $this->getPagePrefix(),
                ];

                $vars = array_merge($vars, $this->getModalFormVars($form, $entity));

                return $this->render('crud/_form_stream.html.twig', $vars, new Response(null, 422, [
                    'Content-Type' => 'text/vnd.turbo-stream.html',
                ]));
            }
        }

        // Ajout des variables pour le template spécifique
        $vars = [
            'form' => $form->createView(),
            'customer' => $customer,
            'page_prefix' => $this->getPagePrefix(),
        ];

        $vars = array_merge($vars, $this->getModalFormVars($form, $entity));

        return $this->render('crud/_modal_form.html.twig', $vars);
    }

    #[Route('/modal/{id}/edit/{customer}', name: '_modal_edit', methods: ['GET', 'POST'])]
    public function modalEdit(Request $request, int $id, ?Customer $customer = null): Response
    {
        $entity = $this->getRepository()->find($id);

        if ($customer) {
            $entity->setCustomer($customer);
        }

        // Permet aux contrôleurs enfants de personnaliser l'entité
        $this->prepareEntityIfExists($entity, $request);

        $form = $this->createForm($this->getFormTypeClass(), $entity, [
            'customer' => $customer,
            'action' => $this->generateFormAction($entity, $customer),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            // Retourner une réponse qui fermera la modal et rafraîchira la page
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'redirect' => $this->generateUrl('app_customer_show', ['id' => $customer->getId()]),
                ]);
            }

            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }

        // Gestion des erreurs de formulaire avec Turbo Stream
        if ($form->isSubmitted() && !$form->isValid() && TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $vars = [
                'form' => $form,
                'entity' => $entity,
                'customer' => $customer,
                'page_prefix' => $this->getPagePrefix(),
            ];

            $modalFormVars = $this->getModalFormVars($form, $entity);

            $vars = array_merge($vars, $modalFormVars);

            return $this->render('crud/_form_stream.html.twig', $vars, new Response(null, 422, [
                'Content-Type' => 'text/vnd.turbo-stream.html',
            ]));
        }

        $vars = [
            'form' => $form->createView(),
            'customer' => $customer,
            'entity' => $entity,
            'page_prefix' => $this->getPagePrefix(),
        ];

        // Permettre aux contrôleurs enfants de personnaliser le template
        $vars = array_merge($vars, $this->getModalFormVars($form, $entity));

        return $this->render('crud/_modal_form.html.twig', $vars);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<mixed> $form
     *
     * @return array<string, mixed>
     */
    protected function getModalFormVars($form, ?object $entity = null): array
    {
        return [];
    }

    /**
     * Personnalise la QueryBuilder pour l'index.
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

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getColumns(): array
    {
        return [
            ['field' => 'id', 'label' => 'id', 'sortable' => false],
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    protected function getRoute(): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            'edit' => $routePrefix.'_edit',
            'delete' => $routePrefix.'_delete',
            'show' => false,
        ];
    }

    #[Route('/{id}/{customer?}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, ?Customer $customer = null): Response
    {
        $entity = $this->getRepository()->find($id);
        if ($customer) {
            $entity->setCustomer($customer);
        }
        // if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        // }

        if ($customer) {
            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute($this->getBaseRouteName().'_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Calls prepareEntity if the method exists in child class.
     */
    protected function prepareEntityIfExists(object $entity, Request $request): void
    {
        // Cette méthode est vide par défaut, les contrôleurs enfants peuvent la surcharger
    }

    /**
     * Generate the form action URL based on the entity and customer.
     */
    protected function generateFormAction(object $entity, ?Customer $customer): string
    {
        $route = method_exists($entity, 'getId') && $entity->getId()
            ? $this->getBaseRouteName().'_edit'
            : $this->getBaseRouteName().'_new';

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
