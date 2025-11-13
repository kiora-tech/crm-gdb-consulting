<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Import\Service\FileStorageService;
use App\Domain\Import\Service\ImportFileValidator;
use App\Domain\Import\Service\ImportOrchestrator;
use App\Entity\Import;
use App\Entity\ImportType;
use App\Entity\User;
use App\Repository\ImportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Import module controller for managing file imports.
 *
 * Note: This controller currently uses placeholder services that need to be implemented:
 * - ImportOrchestrator: Coordinates import workflow (analysis, confirmation, processing)
 * - FileStorageService: Handles secure file storage and retrieval
 * - ImportFileValidator: Validates uploaded import files
 */
#[Route('/import', name: 'app_import')]
#[IsGranted('ROLE_USER')]
class ImportController extends AbstractController
{
    public function __construct(
        private readonly ImportRepository $importRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportOrchestrator $orchestrator,
        private readonly FileStorageService $fileStorage,
        private readonly ImportFileValidator $validator,
    ) {
    }

    /**
     * List all imports for the current user.
     */
    #[Route('/', name: '_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $imports = $this->importRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('import/index.html.twig', [
            'imports' => $imports,
        ]);
    }

    /**
     * Show upload form and handle file upload.
     */
    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleUpload($request);
        }

        return $this->render('import/new.html.twig');
    }

    /**
     * Handle file upload and initialize import.
     */
    private function handleUpload(Request $request): Response
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('import_file');
        $typeValue = $request->request->get('import_type');

        // Validate file presence
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier.');

            return $this->redirectToRoute('app_import_new');
        }

        // Check for upload errors
        if (!$file->isValid()) {
            $error = $file->getError();
            $errorMessage = match ($error) {
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la limite de taille configurée sur le serveur',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la limite de taille du formulaire',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
                UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté le téléchargement du fichier',
                default => 'Erreur lors du téléchargement du fichier (code: '.$error.')',
            };
            $this->addFlash('error', $errorMessage);

            return $this->redirectToRoute('app_import_new');
        }

        // Validate import type
        try {
            $type = ImportType::from((string) $typeValue);
        } catch (\ValueError) {
            $this->addFlash('error', 'Type d\'import invalide.');

            return $this->redirectToRoute('app_import_new');
        }

        // Validate file extension
        $allowedExtensions = ['xls', 'xlsx', 'ods'];
        $extension = strtolower($file->getClientOriginalExtension() ?? '');
        if (!in_array($extension, $allowedExtensions, true)) {
            $this->addFlash('error', 'Format de fichier non supporté. Formats acceptés : '.implode(', ', $allowedExtensions));

            return $this->redirectToRoute('app_import_new');
        }

        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file->getSize() > $maxSize) {
            $this->addFlash('error', 'Le fichier est trop volumineux (max 10 MB).');

            return $this->redirectToRoute('app_import_new');
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            // Store file and get file info
            // Note: Basic validation already done above (extension, size)
            $fileInfo = $this->fileStorage->storeUploadedFile($file);

            // Initialize import
            $import = $this->orchestrator->initializeImport($fileInfo, $type, $user);

            // Start analysis (file integrity will be validated during analysis)
            $this->orchestrator->startAnalysis($import);

            $this->addFlash('success', 'Fichier importé avec succès. L\'analyse est en cours...');

            return $this->redirectToRoute('app_import_show', ['id' => $import->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'import : '.$e->getMessage());

            return $this->redirectToRoute('app_import_new');
        }
    }

    /**
     * Show import details with analysis results.
     */
    #[Route('/{id}', name: '_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $import = $this->importRepository->findOneWithDetails($id);

        if (!$import) {
            throw $this->createNotFoundException('Import non trouvé.');
        }

        // Verify user ownership
        $this->denyAccessUnlessGranted('view', $import);

        return $this->render('import/show.html.twig', [
            'import' => $import,
        ]);
    }

    /**
     * Confirm and start processing the import.
     */
    #[Route('/{id}/confirm', name: '_confirm', methods: ['POST'])]
    public function confirm(int $id): Response
    {
        $import = $this->importRepository->findOneWithDetails($id);

        if (!$import) {
            throw $this->createNotFoundException('Import non trouvé.');
        }

        // Verify user ownership
        $this->denyAccessUnlessGranted('edit', $import);

        // Verify status allows confirmation
        if (!$import->getStatus()->canBeProcessed()) {
            $this->addFlash('error', 'Cet import ne peut pas être confirmé dans son état actuel.');

            return $this->redirectToRoute('app_import_show', ['id' => $id]);
        }

        try {
            // Confirm and start processing
            $this->orchestrator->confirmAndProcess($import);

            $this->addFlash('success', 'Import confirmé. Le traitement est en cours...');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la confirmation : '.$e->getMessage());
        }

        return $this->redirectToRoute('app_import_show', ['id' => $id]);
    }

    /**
     * Cancel an import.
     */
    #[Route('/{id}/cancel', name: '_cancel', methods: ['POST'])]
    public function cancel(int $id): Response
    {
        $import = $this->importRepository->findOneWithDetails($id);

        if (!$import) {
            throw $this->createNotFoundException('Import non trouvé.');
        }

        // Verify user ownership
        $this->denyAccessUnlessGranted('edit', $import);

        // Verify status allows cancellation
        if (!$import->getStatus()->canBeCancelled()) {
            $this->addFlash('error', 'Cet import ne peut pas être annulé dans son état actuel.');

            return $this->redirectToRoute('app_import_show', ['id' => $id]);
        }

        try {
            // TODO: Replace with actual service call when implemented
            // $this->orchestrator->cancelImport($import);

            // For now, just update status
            $import->markAsCancelled();
            $this->entityManager->flush();

            $this->addFlash('success', 'Import annulé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'annulation : '.$e->getMessage());

            return $this->redirectToRoute('app_import_show', ['id' => $id]);
        }

        return $this->redirectToRoute('app_import_index');
    }
}
