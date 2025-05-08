<?php

namespace App\Controller;

use App\Entity\EnergyProvider;
use App\Form\EnergyProviderType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/energy-provider', name: 'app_energy_provider')]
class EnergyProviderController extends BaseCrudController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator,
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct($entityManager, $paginator);
    }

    #[Route('/api/energy-provider/new', name: '_new_ajax', methods: ['POST'])]
    public function createAjax(Request $request, EntityManagerInterface $entityManager): Response
    {
        $name = $request->request->get('name');

        if (!$name) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        $energyProvider = new EnergyProvider();
        $energyProvider->setName($name);

        $entityManager->persist($energyProvider);
        $entityManager->flush();

        return $this->json([
            'id' => $energyProvider->getId(),
            'name' => $energyProvider->getName(),
        ]);
    }

    #[Route('/merge', name: '_merge_form', methods: ['GET'])]
    public function mergeForm(): Response
    {
        $providers = $this->entityManager->getRepository(EnergyProvider::class)->findAll();

        return $this->render('energy_provider/merge.html.twig', [
            'providers' => $providers,
        ]);
    }

    #[Route('/merge', name: '_merge_process', methods: ['POST'])]
    public function mergeProcess(Request $request): Response
    {
        $sourceId = $request->request->get('source_provider');
        $targetId = $request->request->get('target_provider');

        if (!$sourceId || !$targetId || $sourceId === $targetId) {
            $this->addFlash('error', 'energy_provider.merge.invalid_selection');

            return $this->redirectToRoute('app_energy_provider_merge_form');
        }

        $sourceProvider = $this->entityManager->getRepository(EnergyProvider::class)->find($sourceId);
        $targetProvider = $this->entityManager->getRepository(EnergyProvider::class)->find($targetId);

        if (!$sourceProvider || !$targetProvider) {
            $this->addFlash('error', 'energy_provider.merge.provider_not_found');

            return $this->redirectToRoute('app_energy_provider_merge_form');
        }

        try {
            $this->entityManager->beginTransaction();

            // Déplacer toutes les énergies du fournisseur source vers le fournisseur cible
            foreach ($sourceProvider->getEnergies()->toArray() as $energy) {
                $energy->setEnergyProvider($targetProvider);
            }

            // Supprimer le fournisseur source
            $this->entityManager->remove($sourceProvider);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->addFlash('success', 'energy_provider.merge.success');
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->addFlash('error', 'energy_provider.merge.error');
        }

        return $this->redirectToRoute('app_energy_provider_index');
    }

    protected function getEntityClass(): string
    {
        return EnergyProvider::class;
    }

    #[Route('/', name: '_index', methods: ['GET'])]
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

        return $this->render('energy_provider/index.html.twig', $this->getIndexVars(
            $pagination,
            $this->getColumns()
        ));
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $entity = new EnergyProvider();
        $form = $this->createForm(EnergyProviderType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_energy_provider_index');
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $entity));
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EnergyProvider $energyProvider): Response
    {
        $form = $this->createForm(EnergyProviderType::class, $energyProvider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_energy_provider_index');
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $energyProvider));
    }

    #[Route('/{id}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, EnergyProvider $energyProvider): Response
    {
        if ($this->isCsrfTokenValid('delete'.$energyProvider->getId(), $request->request->get('_token'))) {
            // Vérifier si ce fournisseur est utilisé dans des énergies
            if (!$energyProvider->getEnergies()->isEmpty()) {
                $this->addFlash(
                    'error',
                    'energy_provider.delete.error_in_use'
                );

                return $this->redirectToRoute('app_energy_provider_index');
            }

            $this->entityManager->remove($energyProvider);
            $this->entityManager->flush();

            $this->addFlash('success', 'energy_provider.delete.success');
        }

        return $this->redirectToRoute('app_energy_provider_index');
    }

    #[Route('/{id}', name: '_show', methods: ['GET'])]
    public function show(EnergyProvider $energyProvider): Response
    {
        return $this->render('energy_provider/show.html.twig', [
            'provider' => $energyProvider,
        ]);
    }

    protected function getFormVars($form, ?object $entity = null): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            'form' => $form->createView(),
            'entity' => $entity,
            'back_route' => $routePrefix.'_index',
            'delete_route' => $routePrefix.'_delete',
            'page_prefix' => $this->getPagePrefix(),
            'template_path' => null,
        ];
    }

    protected function customizeQueryBuilder(QueryBuilder $qb): void
    {
        // Joindre la collection d'énergies pour pouvoir compter et trier par ce champ
        $qb->leftJoin('e.energies', 'energies')
            ->addSelect('COUNT(energies.id) as HIDDEN energiesCount')
            ->groupBy('e.id');

        // Appliquer le tri en fonction des paramètres de requête
        $sort = $this->getRequestSort();
        $order = $this->getRequestOrder();

        if ('name' === $sort) {
            $qb->orderBy('e.name', $order);
        } elseif ('energies.count' === $sort) {
            $qb->orderBy('energiesCount', $order);
        } else {
            // Tri par défaut
            $qb->orderBy('e.name', 'ASC');
        }
    }

    protected function getColumns(): array
    {
        return [
            ['field' => 'name', 'label' => 'energy_provider.name', 'sortable' => true, 'sortAlias' => 'e.name'],
            ['field' => 'energies.count', 'label' => 'energy_provider.energy_count', 'sortable' => true, 'sortAlias' => 'energiesCount'],
        ];
    }

    private function getRequestSort(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request ? $request->query->get('sort', 'name') : 'name';
    }

    private function getRequestOrder(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request ? strtoupper($request->query->get('order', 'ASC')) : 'ASC';
    }
}
