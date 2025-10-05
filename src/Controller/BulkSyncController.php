<?php

namespace App\Controller;

use App\Repository\CustomerRepository;
use App\Repository\EnergyRepository;
use App\Repository\ContactRepository;
use App\Repository\CommentRepository;
use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/sync', name: 'app_bulk_sync_')]
class BulkSyncController extends AbstractController
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private EnergyRepository $energyRepository,
        private ContactRepository $contactRepository,
        private CommentRepository $commentRepository,
        private DocumentRepository $documentRepository
    ) {
    }

    #[Route('/counts', name: 'counts', methods: ['GET'])]
    public function getCounts(): JsonResponse
    {
        try {
            $customers = $this->customerRepository->count([]);
            $energies = $this->energyRepository->count([]);
            $contacts = $this->contactRepository->count([]);
            $comments = $this->commentRepository->count([]);
            $documents = $this->documentRepository->count([]);

            return $this->json([
                'customers' => $customers,
                'energies' => $energies,
                'contacts' => $contacts,
                'comments' => $comments,
                'documents' => $documents,
                'total' => $customers + $energies + $contacts + $comments + $documents,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/customers', name: 'customers', methods: ['GET'])]
    public function syncCustomers(Request $request): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 50);
            $offset = $request->query->getInt('offset', 0);

            $customers = $this->customerRepository->findBy(
                [],
                ['id' => 'ASC'],
                $limit,
                $offset
            );

            $data = [];
            foreach ($customers as $customer) {
                $data[] = [
                    'id' => $customer->getId(),
                    'name' => $customer->getName(),
                    'siret' => $customer->getSiret(),
                    'businessGrouping' => $customer->getBusinessGrouping(),
                    'legalForm' => $customer->getLegalForm(),
                    'leadOrigin' => $customer->getLeadOrigin(),
                    'origin' => $customer->getOrigin(),
                    'status' => $customer->getStatus(),
                    'signatureCanal' => $customer->getSignatureCanal(),
                    'action' => $customer->getAction(),
                    'value' => $customer->getValue(),
                    'commission' => $customer->getCommission(),
                    'margin' => $customer->getMargin(),
                    'number' => $customer->getNumber(),
                    'street' => $customer->getStreet(),
                    'postalCode' => $customer->getPostalCode(),
                    'city' => $customer->getCity(),
                    'assignedTo' => $customer->getAssignedTo() ? [
                        'id' => $customer->getAssignedTo()->getId(),
                        'name' => $customer->getAssignedTo()->getName(),
                        'email' => $customer->getAssignedTo()->getEmail(),
                    ] : null,
                    'createdAt' => $customer->getCreatedAt()?->format('c'),
                    'updatedAt' => $customer->getUpdatedAt()?->format('c'),
                    'version' => method_exists($customer, 'getVersion') ? $customer->getVersion() : 1,
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/energies', name: 'energies', methods: ['GET'])]
    public function syncEnergies(Request $request): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 100);
            $offset = $request->query->getInt('offset', 0);

            $energies = $this->energyRepository->findBy(
                [],
                ['id' => 'ASC'],
                $limit,
                $offset
            );

            $data = [];
            foreach ($energies as $energy) {
                $data[] = [
                    'id' => $energy->getId(),
                    'customerId' => $energy->getCustomer()->getId(),
                    'type' => $energy->getType(),
                    'code' => $energy->getCode(),
                    'contractEnd' => $energy->getContractEnd()?->format('c'),
                    'provider' => $energy->getEnergyProvider() ? [
                        'id' => $energy->getEnergyProvider()->getId(),
                        'name' => $energy->getEnergyProvider()->getName(),
                    ] : null,
                    'createdAt' => $energy->getCreatedAt()?->format('c'),
                    'updatedAt' => $energy->getUpdatedAt()?->format('c'),
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/contacts', name: 'contacts', methods: ['GET'])]
    public function syncContacts(Request $request): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 100);
            $offset = $request->query->getInt('offset', 0);

            $contacts = $this->contactRepository->findBy(
                [],
                ['id' => 'ASC'],
                $limit,
                $offset
            );

            $data = [];
            foreach ($contacts as $contact) {
                $data[] = [
                    'id' => $contact->getId(),
                    'customerId' => $contact->getCustomer() ? $contact->getCustomer()->getId() : null,
                    'firstName' => $contact->getFirstName(),
                    'lastName' => $contact->getLastName(),
                    'email' => $contact->getEmail(),
                    'phone' => $contact->getPhone(),
                    'mobile' => $contact->getMobile(),
                    'position' => $contact->getPosition(),
                    'isPrimary' => $contact->isPrimary(),
                    'createdAt' => $contact->getCreatedAt()?->format('c'),
                    'updatedAt' => $contact->getUpdatedAt()?->format('c'),
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/comments', name: 'comments', methods: ['GET'])]
    public function syncComments(Request $request): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 200);
            $offset = $request->query->getInt('offset', 0);

            $comments = $this->commentRepository->findBy(
                [],
                ['id' => 'ASC'],
                $limit,
                $offset
            );

            $data = [];
            foreach ($comments as $comment) {
                $data[] = [
                    'id' => $comment->getId(),
                    'customerId' => $comment->getCustomer() ? $comment->getCustomer()->getId() : null,
                    'content' => $comment->getContent(),
                    'authorId' => $comment->getAuthor() ? $comment->getAuthor()->getId() : null,
                    'authorName' => $comment->getAuthor() ? $comment->getAuthor()->getName() : null,
                    'createdAt' => $comment->getCreatedAt()?->format('c'),
                    'updatedAt' => $comment->getUpdatedAt()?->format('c'),
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/documents-metadata', name: 'documents_metadata', methods: ['GET'])]
    public function syncDocumentsMetadata(): JsonResponse
    {
        try {
            $documents = $this->documentRepository->findAll();

            $data = [];
            foreach ($documents as $document) {
                $data[] = [
                    'id' => $document->getId(),
                    'customerId' => $document->getCustomer() ? $document->getCustomer()->getId() : null,
                    'name' => $document->getName(),
                    'type' => $document->getType() ? $document->getType()->getName() : null,
                    'fileName' => $document->getFileName(),
                    'fileSize' => $document->getFileSize(),
                    'mimeType' => $document->getMimeType(),
                    'createdAt' => $document->getCreatedAt()?->format('c'),
                    'updatedAt' => $document->getUpdatedAt()?->format('c'),
                    // Don't include actual file content for offline mode
                    'isAvailableOffline' => false,
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/full', name: 'full', methods: ['GET'])]
    public function fullSync(): JsonResponse
    {
        try {
            // This endpoint could be used for smaller databases
            // to sync everything in one request
            $data = [
                'customers' => [],
                'energies' => [],
                'contacts' => [],
                'comments' => [],
                'documents' => [],
            ];

            // Only use this for small datasets
            $customerCount = $this->customerRepository->count([]);
            if ($customerCount > 1000) {
                return $this->json([
                    'error' => 'Database too large for full sync. Use batch endpoints instead.',
                ], 400);
            }

            // Fetch all data
            foreach ($this->customerRepository->findAll() as $customer) {
                $data['customers'][] = [
                    'id' => $customer->getId(),
                    'name' => $customer->getName(),
                    'siret' => $customer->getSiret(),
                    // ... other fields
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}