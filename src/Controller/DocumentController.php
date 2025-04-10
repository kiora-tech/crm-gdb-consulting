<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Document;
use App\Entity\Template;
use App\Form\DocumentType;
use App\Form\DropzoneForm;
use App\Repository\DocumentRepository;
use App\Service\Template\TemplateProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/document', name: 'app_document')]
class DocumentController extends CustomerInfoController
{
    #[Route('/new/{customer?}', name: '_new', methods: ['GET', 'POST'], priority: 999)]
    public function uploadDocument(
        Request $request,
        SluggerInterface $slugger,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/public/uploads/documents')]
        string $uploadDirectory,
        ?Customer $customer = null,
    ): Response
    {
        $document = new Document();

        $form = $this->createForm(DropzoneForm::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                /** @var UploadedFile $documentFile */
                $documentFile = $form->get('file')->getData();

                if ($documentFile) {
                    $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $documentFile->guessExtension();

                    try {
                        $documentFile->move($uploadDirectory, $newFilename);
                        $document->setName($originalFilename);
                        $document->setPath($uploadDirectory . '/' . $newFilename);
                        if($customer) {
                            $document->setCustomer($customer);
                        }
                        $entityManager->persist($document);
                        $entityManager->flush();

                        if ($request->isXmlHttpRequest()) {
                            return $this->json([
                                'success' => true,
                                'html' => $this->renderView('document/_document_list.html.twig', [
                                    'documents' => $customer->getDocuments()
                                ])
                            ]);
                        }

                        return $this->redirectToRoute('app_document_index');
                    } catch (FileException $e) {
                        $logger->error('Upload error: ' . $e->getMessage());
                        if ($request->isXmlHttpRequest()) {
                            return $this->json([
                                'success' => false,
                                'error' => 'Could not upload file'
                            ], Response::HTTP_BAD_REQUEST);
                        }
                    }
                }
            } else {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => false,
                        'html' => $this->renderView('document/_form_modal_content.html.twig', [
                            'form' => $form->createView(),
                            'customer' => $customer
                        ])
                    ]);
                }
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'html' => $this->renderView('document/_form_modal_content.html.twig', [
                    'form' => $form->createView(),
                    'customer' => $customer
                ])
            ]);
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $document));
    }

    #[Route('/new/{customer?}', name: '_new_override', methods: ['GET', 'POST'], priority: 1)]
    public function new(Request $request, ?Customer $customer = null): Response
    {
        return parent::new($request, $customer);
    }

    protected function getColumns(): array
    {
        return [
            ['field' => 'name', 'label' => 'document.name', 'sortable' => true],
            ['field' => 'type', 'label' => 'document.type', 'sortable' => true],
        ];
    }

    protected function getRoute(): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            'edit' => false,
            'delete' => $routePrefix . '_delete',
            'show' => $routePrefix . '_download',
            'actionAttributes' => [
                'show' => ['data-turbo' => 'false']
            ]
        ];
    }

    #[Route('/{id}/download', name: '_download', methods: ['GET'])]
    public function downloadDocument(
        Document $document,
        MimeTypesInterface $mimeTypes
    ): Response
    {
        $response = new Response();

        if (!file_exists($filePath = $document->getPath())) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas');
        }

        //gettype
        $typeMime = $mimeTypes->guessMimeType($filePath);
        $baseName = basename($filePath);
        $response->headers->set('Content-Type', $typeMime);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $baseName . '"');
        $response->setContent(file_get_contents($filePath));

        return $response;
    }

    #[Route('/{id}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, ?Customer $customer = null): Response
    {
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            $document = $this->getRepository()->find($id);
            $path = $document->getPath();
            if (file_exists($path)) {
                unlink($path);
            }
            $customer = $document->getCustomer();
            $this->entityManager->remove($document);
            $this->entityManager->flush();

            if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->redirectToRoute('app_document_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/generate/{template}/{customer}', name: '_generate_from_template', methods: ['GET'])]
    public function generateFromTemplate(
        Template $template,
        Customer $customer,
        TemplateProcessor $templateProcessor,
        #[Autowire('%kernel.project_dir%/public/uploads/documents')]
        string $uploadDirectory,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager
    ): Response {
        try {
            // Génère le document à partir du template
            $tempFile = $templateProcessor->processTemplate($template, $customer);

            // Crée le nouveau nom de fichier
            $originalFilename = pathinfo($template->getOriginalFilename(), PATHINFO_FILENAME);
            $extension = pathinfo($template->getOriginalFilename(), PATHINFO_EXTENSION);
            $safeFilename = $slugger->slug($originalFilename . '_' . $customer->getName());
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

            // Déplace le fichier dans le répertoire uploads
            rename($tempFile, $uploadDirectory . '/' . $newFilename);

            // Crée une nouvelle entité Document
            $document = new Document();
            $document->setCustomer($customer);
            $document->setName($originalFilename . ' - ' . $customer->getName());
            $document->setPath('uploads/documents/' . $newFilename);
            $document->setType($template->getDocumentType());

            // Persiste le nouveau document
            $entityManager->persist($document);
            $entityManager->flush();

            // Retourne le fichier
            $response = new Response(file_get_contents($uploadDirectory . '/' . $newFilename));
            $response->headers->set('Content-Type', $template->getMimeType());
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="' . $newFilename . '"'
            );

            return $response;

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la génération du document : ' . $e->getMessage());
            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }
    }

    protected function getEntityClass(): string
    {
        return Document::class;
    }

    protected function getFormTypeClass(): string
    {
        return DropzoneForm::class;
    }

    protected function customizeQueryBuilder(QueryBuilder $qb): void
    {
        $qb->leftJoin('e.customer', 'c')
            ->leftJoin('e.type', 't');
    }
}