<?php

namespace App\Controller;

use App\Entity\DocumentType;
use App\Form\DocumentTypeType;
use App\Repository\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/document_type')]
class DocumentTypeController extends AbstractController
{
    use CrudTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaginatorInterface $paginator
    ) {
    }

    #[Route('/', name: 'app_document_type_index', methods: ['GET'])]
    public function index(Request $request, DocumentTypeRepository $repository): Response
    {
        $queryBuilder = $repository->createQueryBuilder('e');

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1)
        );

        return $this->render('crud/index.html.twig', $this->getIndexVars(
            $pagination,
            [
                ['field' => 'label', 'label' => 'document_type.label', 'sortable' => true]
            ]
        ));
    }

    #[Route('/new', name: 'app_document_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $documentType = new DocumentType();
        $form = $this->createForm(DocumentTypeType::class, $documentType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($documentType);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_document_type_index');
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $documentType));
    }

    #[Route('/{id}/edit', name: 'app_document_type_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DocumentType $documentType): Response
    {
        $form = $this->createForm(DocumentTypeType::class, $documentType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_document_type_index');
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $documentType));
    }

    #[Route('/{id}', name: 'app_document_type_delete', methods: ['POST'])]
    public function delete(Request $request, DocumentType $documentType): Response
    {
        if ($this->isCsrfTokenValid('delete'.$documentType->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($documentType);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('app_document_type_index');
    }
}