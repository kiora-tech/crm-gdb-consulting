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
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): Response {
        try {
            $logger->info('Début de génération de document', [
                'template_id' => $template->getId(),
                'template_label' => $template->getLabel(),
                'customer_id' => $customer->getId(),
                'customer_name' => $customer->getName(),
                'upload_directory' => $uploadDirectory
            ]);

            // Vérifier l'existence du répertoire d'upload
            if (!is_dir($uploadDirectory)) {
                $logger->critical('Le répertoire d\'upload n\'existe pas', [
                    'directory' => $uploadDirectory
                ]);
                throw new \RuntimeException('Le répertoire d\'upload n\'existe pas: ' . $uploadDirectory);
            }

            // Vérifier les permissions d'écriture
            if (!is_writable($uploadDirectory)) {
                $logger->critical('Le répertoire d\'upload n\'est pas accessible en écriture', [
                    'directory' => $uploadDirectory,
                    'permissions' => substr(sprintf('%o', fileperms($uploadDirectory)), -4)
                ]);
                throw new \RuntimeException('Le répertoire d\'upload n\'est pas accessible en écriture: ' . $uploadDirectory);
            }

            // Génère le document à partir du template
            $logger->info('Traitement du template', [
                'template_path' => $template->getPath()
            ]);
            $tempFile = $templateProcessor->processTemplate($template, $customer);
            $logger->info('Template traité avec succès', [
                'temp_file' => $tempFile
            ]);

            // Crée le nouveau nom de fichier
            $originalFilename = pathinfo($template->getOriginalFilename(), PATHINFO_FILENAME);
            $extension = pathinfo($template->getOriginalFilename(), PATHINFO_EXTENSION);
            $safeFilename = $slugger->slug($originalFilename . '_' . $customer->getName());
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;
            $fullPath = $uploadDirectory . '/' . $newFilename;
            
            $logger->info('Déplacement du fichier temporaire', [
                'from' => $tempFile,
                'to' => $fullPath
            ]);

            // Déplace le fichier dans le répertoire uploads
            if (!rename($tempFile, $fullPath)) {
                $logger->error('Échec du déplacement du fichier', [
                    'source' => $tempFile,
                    'destination' => $fullPath,
                    'error' => error_get_last()
                ]);
                throw new \RuntimeException('Impossible de déplacer le fichier temporaire vers la destination finale');
            }

            $logger->info('Fichier déplacé avec succès');

            // Crée une nouvelle entité Document
            $document = new Document();
            $document->setCustomer($customer);
            $document->setName($originalFilename . ' - ' . $customer->getName());
            $document->setPath('uploads/documents/' . $newFilename);
            $document->setType($template->getDocumentType());

            // Persiste le nouveau document
            $entityManager->persist($document);
            $entityManager->flush();

            $logger->info('Document enregistré en base de données', [
                'document_id' => $document->getId(),
                'document_path' => $document->getPath()
            ]);

            // Vérifie que le fichier peut être lu
            if (!file_exists($fullPath) || !is_readable($fullPath)) {
                $logger->error('Le fichier final n\'existe pas ou n\'est pas lisible', [
                    'path' => $fullPath,
                    'exists' => file_exists($fullPath),
                    'readable' => is_readable($fullPath)
                ]);
                throw new \RuntimeException('Le fichier généré n\'existe pas ou n\'est pas lisible');
            }

            // Retourne le fichier
            $fileContent = file_get_contents($fullPath);
            if ($fileContent === false) {
                $logger->error('Impossible de lire le contenu du fichier', [
                    'path' => $fullPath,
                    'error' => error_get_last()
                ]);
                throw new \RuntimeException('Impossible de lire le contenu du fichier généré');
            }

            $response = new Response($fileContent);
            $response->headers->set('Content-Type', $template->getMimeType());
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="' . $newFilename . '"'
            );

            $logger->info('Document généré avec succès', [
                'file_size' => strlen($fileContent)
            ]);

            return $response;

        } catch (\Exception $e) {
            $logger->error('Erreur lors de la génération du document', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'template_id' => $template->getId(),
                'customer_id' => $customer->getId()
            ]);
            
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