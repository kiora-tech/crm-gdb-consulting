<?php

namespace App\Controller;

use App\Data\ContactSearchData;
use App\Entity\Contact;
use App\Form\ContactSearchType;
use App\Repository\ContactRepository;
use App\Service\PaginationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/contact', name: 'app_contact')]
final class ContactController extends CustomerInfoController
{
    /**
     * @param PaginationService<int, Contact> $paginationService
     */
    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Knp\Component\Pager\PaginatorInterface $paginator,
        \Psr\Log\LoggerInterface $logger,
        private readonly ContactRepository $contactRepository,
        private readonly PaginationService $paginationService,
    ) {
        parent::__construct($entityManager, $paginator, $logger);
    }

    public function getEntityClass(): string
    {
        return Contact::class;
    }

    #[Route('', name: '_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $data = new ContactSearchData();
        $form = $this->createForm(ContactSearchType::class, $data);
        $form->handleRequest($request);

        $query = $this->contactRepository->search($data);
        $contacts = $this->paginationService->paginate($query, $request);

        return $this->render('contact/index.html.twig', [
            'contacts' => $contacts,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: '_show', methods: ['GET'])]
    public function show(Contact $contact): Response
    {
        return $this->render('contact/show.html.twig', [
            'entity' => $contact,
        ]);
    }

    /**
     * @param \Knp\Component\Pager\Pagination\PaginationInterface<int, Contact> $pagination
     * @param array<int, array<string, mixed>>                                  $columns
     *
     * @return array<string, mixed>
     */
    protected function getIndexVars($pagination, array $columns = []): array
    {
        return $this->getIndexVarsTrait($pagination, [
            ['field' => 'email', 'label' => 'contact.email', 'sortable' => true],
            ['field' => 'lastName', 'label' => 'contact.last_name', 'sortable' => true],
            ['field' => 'position', 'label' => 'contact.position'],
            ['field' => 'phone', 'label' => 'contact.phone'],
            ['field' => 'mobilePhone', 'label' => 'contact.mobile_phone'],
        ]);
    }

    /**
     * @return array<string, string|bool>
     */
    protected function getRoute(): array
    {
        $route = parent::getRoute();
        $route['show'] = 'app_contact_show';

        return $route;
    }
}
