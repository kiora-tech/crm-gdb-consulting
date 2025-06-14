<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Document;
use App\Entity\Template;
use App\Form\DropzoneForm;
use App\Service\Template\TemplateProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/document', name: 'app_document')]
class DocumentController extends CustomerInfoController
{
    public function __construct(
        #[Autowire(service: 'documents.storage')]
        private readonly FilesystemOperator $documentsStorage,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator,
    ) {
        parent::__construct($entityManager, $paginator);
    }

    #[Route('/new/{customer?}', name: '_new', methods: ['GET', 'POST'], priority: 999)]
    public function uploadDocument(
        Request $request,
        SluggerInterface $slugger,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        ?Customer $customer = null,
    ): Response {
        $document = new Document();

        $form = $this->createForm(DropzoneForm::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                /** @var UploadedFile|null $documentFile */
                $documentFile = $form->get('file')->getData();

                if ($documentFile) {
                    $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$documentFile->guessExtension();

                    try {
                        $stream = fopen($documentFile->getRealPath(), 'r');
                        $this->documentsStorage->writeStream($newFilename, $stream);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        $document->setName($originalFilename);
                        $document->setPath($newFilename);
                        if ($customer) {
                            $document->setCustomer($customer);
                        }
                        $entityManager->persist($document);
                        $entityManager->flush();

                        if ($request->isXmlHttpRequest()) {
                            $documents = (null !== $customer) ? $customer->getDocuments() : [];

                            return $this->json([
                                'success' => true,
                                'html' => $this->renderView('document/_document_list.html.twig', [
                                    'documents' => $documents,
                                ]),
                            ]);
                        }

                        return $this->redirectToRoute('app_document_index');
                    } catch (FileException $e) {
                        $logger->error('Upload error: '.$e->getMessage());
                        if ($request->isXmlHttpRequest()) {
                            return $this->json([
                                'success' => false,
                                'error' => 'Could not upload file',
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
                            'customer' => $customer,
                        ]),
                    ]);
                }
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'html' => $this->renderView('document/_form_modal_content.html.twig', [
                    'form' => $form->createView(),
                    'customer' => $customer,
                ]),
            ]);
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $document));
    }

    #[Route('/new/{customer?}', name: '_new_override', methods: ['GET', 'POST'], priority: 1)]
    public function new(Request $request, ?Customer $customer = null): Response
    {
        return parent::new($request, $customer);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getColumns(): array
    {
        return [
            ['field' => 'name', 'label' => 'document.name', 'sortable' => true],
            ['field' => 'type', 'label' => 'document.type', 'sortable' => true],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getRoute(): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            'edit' => false,
            'delete' => $routePrefix.'_delete',
            'show' => $routePrefix.'_download',
            'actionAttributes' => [
                'show' => ['data-turbo' => 'false'],
            ],
        ];
    }

    #[Route('/{id}/download', name: '_download', methods: ['GET'])]
    public function downloadDocument(
        Document $document,
    ): Response {
        $response = new Response();

        $filePath = $document->getPath();
        if (!$filePath || !$this->documentsStorage->fileExists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas');
        }

        // Lire les métadonnées du fichier pour déterminer le type MIME
        $mimeType = $this->documentsStorage->mimeType($filePath);
        $baseName = basename($filePath);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$baseName.'"');

        $fileContent = $this->documentsStorage->read($filePath);
        if (empty($fileContent)) {
            throw $this->createNotFoundException('Impossible de lire le fichier');
        }

        $response->setContent($fileContent);

        return $response;
    }

    #[Route('/{id}/{customer?}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, ?Customer $customer = null): Response
    {
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            /** @var Document|null $document */
            $document = $this->getRepository()->find($id);
            if (!$document) {
                throw $this->createNotFoundException('Document non trouvé');
            }

            $path = $document->getPath();
            if ($path && $this->documentsStorage->fileExists($path)) {
                $this->documentsStorage->delete($path);
            }

            /** @var Customer|null $customer */
            $customer = $document->getCustomer();
            $customerId = null;
            if ($customer) {
                $customerId = $customer->getId();
            }

            $this->entityManager->remove($document);
            $this->entityManager->flush();

            if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat() && isset($customerId)) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customerId], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->redirectToRoute('app_document_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/generate/{template}/{customer}', name: '_generate_from_template', methods: ['GET'])]
    public function generateFromTemplate(
        Template $template,
        Customer $customer,
        TemplateProcessor $templateProcessor,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
    ): Response {
        try {
            if (!$template->getId()) {
                throw new \InvalidArgumentException('Template ID ne peut pas être null');
            }

            if (!$template->getLabel()) {
                throw new \InvalidArgumentException('Template label ne peut pas être null');
            }

            if (!$customer->getId()) {
                throw new \InvalidArgumentException('Customer ID ne peut pas être null');
            }

            if (!$customer->getName()) {
                throw new \InvalidArgumentException('Customer name ne peut pas être null');
            }

            $logger->info('Début de génération de document', [
                'template_id' => $template->getId(),
                'template_label' => $template->getLabel(),
                'customer_id' => $customer->getId(),
                'customer_name' => $customer->getName(),
            ]);

            // Génère le document à partir du template
            $logger->info('Traitement du template', [
                'template_path' => $template->getPath(),
            ]);
            $tempFile = $templateProcessor->processTemplate($template, $customer);
            $logger->info('Template traité avec succès', [
                'temp_file' => $tempFile,
            ]);

            // Vérifier que originalFilename n'est pas null
            $originalFilenameRaw = $template->getOriginalFilename();
            if (!$originalFilenameRaw) {
                throw new \RuntimeException('Le nom de fichier original du template est manquant');
            }

            // Crée le nouveau nom de fichier
            $originalFilename = pathinfo($originalFilenameRaw, PATHINFO_FILENAME);
            $extension = pathinfo($originalFilenameRaw, PATHINFO_EXTENSION);
            $safeFilename = $slugger->slug($originalFilename.'_'.$customer->getName());
            $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;

            $logger->info('Déplacement du fichier temporaire', [
                'from' => $tempFile,
                'to' => $newFilename,
            ]);

            // Copie le fichier temporaire vers le stockage Flysystem
            $stream = fopen($tempFile, 'r');
            if (!$stream) {
                $logger->error('Impossible d\'ouvrir le fichier temporaire', [
                    'file' => $tempFile,
                    'error' => error_get_last(),
                ]);
                throw new \RuntimeException('Impossible d\'ouvrir le fichier temporaire');
            }

            $this->documentsStorage->writeStream($newFilename, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // Supprime le fichier temporaire
            unlink($tempFile);

            $logger->info('Fichier déplacé avec succès');

            // Crée une nouvelle entité Document
            $document = new Document();
            $document->setCustomer($customer);
            $document->setName($originalFilename.' - '.$customer->getName());
            $document->setPath($newFilename);
            $document->setType($template->getDocumentType());

            // Persiste le nouveau document
            $entityManager->persist($document);
            $entityManager->flush();

            $logger->info('Document enregistré en base de données', [
                'document_id' => $document->getId(),
                'document_path' => $document->getPath(),
            ]);

            // Vérifie que le fichier peut être lu
            if (!$this->documentsStorage->fileExists($newFilename)) {
                $logger->error('Le fichier final n\'existe pas', [
                    'path' => $newFilename,
                ]);
                throw new \RuntimeException('Le fichier généré n\'existe pas');
            }

            // Retourne le fichier
            $fileContent = $this->documentsStorage->read($newFilename);
            if (empty($fileContent)) {
                $logger->error('Impossible de lire le contenu du fichier', [
                    'path' => $newFilename,
                    'error' => error_get_last(),
                ]);
                throw new \RuntimeException('Impossible de lire le contenu du fichier généré');
            }

            $response = new Response($fileContent);
            $response->headers->set('Content-Type', $template->getMimeType());
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="'.$newFilename.'"'
            );

            $logger->info('Document généré avec succès', [
                'file_size' => strlen($fileContent),
            ]);

            return $response;
        } catch (\Exception $e) {
            // Dans le bloc catch, il est possible que $template->getId() ou $customer->getId()
            // déclenche une autre exception si les objets sont dans un état incohérent
            $templateId = null;
            $customerId = null;

            try {
                $templateId = $template->getId();
            } catch (\Throwable $t) {
                // Ne rien faire, garder null
            }

            try {
                $customerId = $customer->getId();
            } catch (\Throwable $t) {
                // Ne rien faire, garder null
            }

            $logger->error('Erreur lors de la génération du document', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'template_id' => $templateId,
                'customer_id' => $customerId,
                'server_environment' => $_SERVER['APP_ENV'] ?? 'unknown',
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_limit' => ini_get('memory_limit'),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du document : '.$e->getMessage());

            // Utiliser l'ID récupéré plus tôt pour la redirection, avec fallback
            try {
                $id = $customer->getId();

                return $this->redirectToRoute('app_customer_show', ['id' => $id]);
            } catch (\Throwable $t) {
                // En cas d'échec complet, rediriger vers la page d'accueil
                $logger->error('Erreur secondaire lors de la redirection', [
                    'exception' => get_class($t),
                    'message' => $t->getMessage(),
                ]);

                return $this->redirectToRoute('homepage');
            }
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
