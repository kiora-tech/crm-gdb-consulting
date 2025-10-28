<?php

namespace App\Controller;

use App\Data\CustomerSearchData;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\Document;
use App\Entity\ProspectStatus;
use App\Entity\Template;
use App\Entity\User;
use App\Form\CustomerSearchType;
use App\Form\CustomerType;
use App\Form\DropzoneForm;
use App\Repository\CustomerRepository;
use App\Service\ImportService;
use App\Service\MicrosoftGraphService;
use App\Service\PaginationService;
use App\Service\SearchFilterService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/customer', name: 'app_customer')]
class CustomerController extends AbstractController
{
    /**
     * @param PaginationService<int, Company> $paginationService
     */
    #[Route('/', name: '_index', methods: ['GET'])]
    /** @phpstan-param PaginationService<int, Customer> $paginationService */
    public function index(
        CustomerRepository $customerRepository,
        PaginationService $paginationService,
        Request $request,
        SearchFilterService $searchFilterService,
        LoggerInterface $logger,
    ): Response {
        $session = $request->getSession();
        $resetFilter = $request->query->has('reset');

        $logger->info('CustomerController: Session started', [
            'session_id' => $session->getId(),
            'reset_filter' => $resetFilter,
            'query_count' => $request->query->count(),
            'has_customer_search' => $session->has('customer_search'),
        ]);

        // Initialiser les données de recherche
        if ($resetFilter) {
            // Réinitialiser les filtres si demandé
            $searchFilterService->clearSearchData($session);
            $data = new CustomerSearchData();
        } elseif (!$request->query->count() && $session->has('customer_search')) {
            // Récupérer les données de session si aucun paramètre n'est fourni
            $data = $searchFilterService->loadSearchData($session);
        } else {
            // Nouvelle recherche
            $data = new CustomerSearchData();
        }

        $form = $this->createForm(CustomerSearchType::class, $data);
        $form->handleRequest($request);

        // Si le formulaire est soumis, sauvegarder en session
        if ($form->isSubmitted() && $form->isValid()) {
            $searchFilterService->saveSearchData($session, $data);
        }

        $query = $customerRepository->search($data);
        $customers = $paginationService->paginate($query, $request);

        $importErrorDirectory = $this->getParameter('kernel.project_dir').'/var/import/errors';
        $errorFiles = [];
        if (is_dir($importErrorDirectory)) {
            $errorFiles = array_filter(
                scandir($importErrorDirectory),
                fn ($file) => '.' !== $file && '..' !== $file && 'xlsx' === pathinfo($file, PATHINFO_EXTENSION)
            );
        }

        return $this->render('customer/index.html.twig', [
            'customers' => $customers,
            'form' => $form,
            'importErrorFiles' => $errorFiles,
        ]);
    }

    #[Route('/import-error-file', name: '_import_error_file', methods: ['GET'])]
    public function downloadImportErrorFile(Request $request): Response
    {
        $filename = $request->query->get('filename');
        $importErrorDirectory = $this->getParameter('kernel.project_dir').'/var/import/errors';
        $filePath = $importErrorDirectory.'/'.$filename;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier d\'erreur n\'existe pas.');
        }

        // Supprimer le fichier après le téléchargement
        $response = $this->file($filePath, $filename);

        // Ajoutez un listener pour supprimer le fichier après le téléchargement
        $response->deleteFileAfterSend();

        return $response;
    }

    #[Route('/delete-import-error-file', name: '_delete_import_error_file', methods: ['POST'])]
    public function deleteImportErrorFile(Request $request, LoggerInterface $logger): Response
    {
        $filename = $request->request->get('filename');
        $importErrorDirectory = $this->getParameter('kernel.project_dir').'/var/import/errors';
        $filePath = $importErrorDirectory.'/'.$filename;

        if (file_exists($filePath)) {
            try {
                unlink($filePath);
                $this->addFlash('success', 'Fichier d\'erreur supprimé avec succès.');
            } catch (\Exception $e) {
                $logger->error('Impossible de supprimer le fichier d\'erreur : '.$e->getMessage());
                $this->addFlash('error', 'Impossible de supprimer le fichier d\'erreur.');
            }
        } else {
            $this->addFlash('warning', 'Le fichier d\'erreur n\'existe pas.');
        }

        return $this->redirectToRoute('app_customer_index');
    }

    #[Route('/{id}/status/{status}', name: '_status')]
    public function updateStatus(Request $request, Customer $customer, string $status, EntityManagerInterface $entityManager): Response
    {
        $newStatus = ProspectStatus::from($status);
        $customer->setStatus($newStatus);
        $entityManager->flush();

        if ($request->isXmlHttpRequest() && ProspectStatus::LOST === $newStatus) {
            return $this->json([
                'success' => true,
                'status' => $status,
                'customerId' => $customer->getId(),
            ]);
        }

        return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customer = new Customer();
        /** @var User|null $user */
        $user = $this->getUser();
        $customer->setUser($user);
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($customer);
            $entityManager->flush();

            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/upload', name: '_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request, ImportService $importService): Response
    {
        if ($request->isMethod('POST')) {
            $file = $request->files->get('file');

            if ($file instanceof UploadedFile && $file->isValid()) {
                /** @var User $user */
                $user = $this->getUser();
                $importService->importFromUpload($file, $user->getId());

                $this->addFlash('success', 'File uploaded and import data started.');

                return $this->redirectToRoute('app_customer_index');
            }

            $this->addFlash('error', 'There was an issue with the file upload. Please try again.');
        }

        return $this->render('customer/upload.html.twig');
    }

    #[Route('/{id}', name: '_show', methods: ['GET'])]
    public function show(Customer $customer, EntityManagerInterface $entityManager, MicrosoftGraphService $microsoftGraphService, LoggerInterface $logger): Response
    {
        // Vérifier si l'utilisateur a le droit de voir ce client
        $this->denyAccessUnlessGranted('view', $customer);

        $document = new Document();
        $document->setCustomer($customer);
        $formDocument = $this->createForm(DropzoneForm::class, $document, ['customer' => $customer]);
        $templates = $entityManager->getRepository(Template::class)->findAll();

        // Get category colors from Outlook
        $categoryColors = [];
        $user = $this->getUser();
        if ($user instanceof User && $microsoftGraphService->hasValidToken($user)) {
            try {
                $outlookCategories = $microsoftGraphService->getUserCategories($user);
                foreach ($outlookCategories as $category) {
                    $categoryName = $category['displayName'];
                    $categoryColors[$categoryName] = $this->mapOutlookColorToHex($category['color']);
                }
            } catch (\Exception $e) {
                $logger->warning('Could not fetch Outlook categories for customer view', [
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
            'formDocument' => $formDocument->createView(),
            'templates' => $templates,
            'categoryColors' => $categoryColors,
        ]);
    }

    /**
     * Map Outlook color preset to hex color.
     */
    private function mapOutlookColorToHex(string $preset): string
    {
        $colorMap = [
            'preset0' => '#FF6B6B', // Red
            'preset1' => '#FFA500', // Orange
            'preset2' => '#FFD700', // Yellow
            'preset3' => '#90EE90', // Light Green
            'preset4' => '#40E0D0', // Turquoise
            'preset5' => '#87CEEB', // Sky Blue
            'preset6' => '#4169E1', // Royal Blue
            'preset7' => '#9370DB', // Medium Purple
            'preset8' => '#DA70D6', // Orchid
            'preset9' => '#708090', // Slate Gray
            'preset10' => '#A9A9A9', // Dark Gray
            'preset11' => '#696969', // Dim Gray
            'preset12' => '#8B4513', // Saddle Brown
            'preset13' => '#D2691E', // Chocolate
            'preset14' => '#CD5C5C', // Indian Red
            'preset15' => '#F08080', // Light Coral
            'preset16' => '#FA8072', // Salmon
            'preset17' => '#E9967A', // Dark Salmon
            'preset18' => '#FFA07A', // Light Salmon
            'preset19' => '#FF7F50', // Coral
            'preset20' => '#FF6347', // Tomato
            'preset21' => '#FF4500', // Orange Red
            'preset22' => '#FFD700', // Gold
            'preset23' => '#FFFF00', // Yellow
            'preset24' => '#9ACD32', // Yellow Green
        ];

        return $colorMap[$preset] ?? '#808080'; // Default gray
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur a le droit de modifier ce client
        $this->denyAccessUnlessGranted('edit', $customer);

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: '_delete', methods: ['POST'])]
    public function delete(Customer $customer, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Accès refusé. Seuls les administrateurs peuvent supprimer des clients.');

        $entityManager->remove($customer);
        $entityManager->flush();

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}
