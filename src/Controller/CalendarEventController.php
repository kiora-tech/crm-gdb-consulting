<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CalendarEvent;
use App\Entity\Customer;
use App\Entity\User;
use App\Form\CalendarEventType;
use App\Service\CalendarEventSyncService;
use App\Service\MicrosoftGraphService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/customer/{customerId}/event', name: 'app_calendar_event')]
class CalendarEventController extends AbstractController
{
    public function __construct(
        private readonly CalendarEventSyncService $syncService,
        private readonly MicrosoftGraphService $microsoftGraphService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/create', name: '_create', methods: ['GET', 'POST'])]
    public function createEventForCustomer(Request $request, int $customerId): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        // Check if user has Microsoft token
        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('error', 'Vous devez connecter votre compte Microsoft pour créer des événements.');

            return $this->redirectToRoute('app_customer_show', ['id' => $customerId]);
        }

        // Get customer
        $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
        if (!$customer) {
            throw $this->createNotFoundException('Client non trouvé');
        }

        // Authorization check: user must own the customer or be admin
        if ($customer->getUser()?->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas créer des événements pour ce client');
        }

        // Create new calendar event
        $calendarEvent = new CalendarEvent();
        $calendarEvent->setCustomer($customer);
        $calendarEvent->setCreatedBy($user);

        // Get user's Outlook categories with colors
        $userCategories = [];
        $categoryColors = [];
        try {
            $outlookCategories = $this->microsoftGraphService->getUserCategories($user);
            foreach ($outlookCategories as $category) {
                $categoryName = $category['displayName'];
                $userCategories[$categoryName] = $categoryName;
                $categoryColors[$categoryName] = $this->mapOutlookColorToHex($category['color']);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not fetch Outlook categories, using defaults', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            // Fallback to default categories
            $userCategories = [
                'Réunion' => 'Réunion',
                'Appel téléphonique' => 'Appel téléphonique',
                'Rendez-vous client' => 'Rendez-vous client',
                'Visite sur site' => 'Visite sur site',
                'Négociation' => 'Négociation',
                'Signature contrat' => 'Signature contrat',
                'Suivi' => 'Suivi',
                'Autre' => 'Autre',
            ];
            $categoryColors = [];
        }

        $form = $this->createForm(CalendarEventType::class, $calendarEvent, [
            'user_categories' => $userCategories,
            'category_colors' => $categoryColors,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Begin transaction for atomic operation
                $this->entityManager->beginTransaction();

                // Persist first to get an ID (needed for potential rollback tracking)
                $this->entityManager->persist($calendarEvent);
                $this->entityManager->flush();

                // Create event in Microsoft Calendar with rollback capability
                try {
                    $microsoftEventId = $this->syncService->createEventInMicrosoft($calendarEvent, $user);
                    $calendarEvent->setMicrosoftEventId($microsoftEventId);
                    $calendarEvent->markAsSynced();
                    $this->entityManager->flush();
                    $this->entityManager->commit();

                    $this->addFlash('success', sprintf(
                        'Événement "%s" créé avec succès dans votre calendrier Microsoft.',
                        $calendarEvent->getTitle()
                    ));

                    return $this->redirectToRoute('app_customer_show', ['id' => $customerId]);
                } catch (\Exception $e) {
                    // Rollback database changes if Microsoft sync fails
                    $this->entityManager->rollback();
                    $this->logger->error('Failed to create Microsoft event, rolling back database changes', [
                        'user_id' => $user->getId(),
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            } catch (\Exception $e) {
                $this->addFlash('error', sprintf(
                    'Erreur lors de la création de l\'événement : %s',
                    $e->getMessage()
                ));
            }
        }

        return $this->render('calendar_event/create.html.twig', [
            'customer' => $customer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $customerId, CalendarEvent $calendarEvent): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        // Check if user has Microsoft token
        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('error', 'Vous devez connecter votre compte Microsoft pour modifier des événements.');

            return $this->redirectToRoute('app_user_profile');
        }

        // Get customer
        $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
        if (!$customer) {
            throw $this->createNotFoundException('Client non trouvé');
        }

        // Authorization check: user must be the creator or admin
        if ($calendarEvent->getCreatedBy()?->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cet événement');
        }

        // Get user's Outlook categories with colors
        $userCategories = [];
        $categoryColors = [];
        try {
            $outlookCategories = $this->microsoftGraphService->getUserCategories($user);
            foreach ($outlookCategories as $category) {
                $categoryName = $category['displayName'];
                $userCategories[$categoryName] = $categoryName;
                $categoryColors[$categoryName] = $this->mapOutlookColorToHex($category['color']);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not fetch Outlook categories, using defaults', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            // Fallback to default categories
            $userCategories = [
                'Réunion' => 'Réunion',
                'Appel téléphonique' => 'Appel téléphonique',
                'Rendez-vous client' => 'Rendez-vous client',
                'Visite sur site' => 'Visite sur site',
                'Négociation' => 'Négociation',
                'Signature contrat' => 'Signature contrat',
                'Suivi' => 'Suivi',
                'Autre' => 'Autre',
            ];
            $categoryColors = [];
        }

        $form = $this->createForm(CalendarEventType::class, $calendarEvent, [
            'user_categories' => $userCategories,
            'category_colors' => $categoryColors,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Update in Microsoft Calendar
                $eventData = [
                    'subject' => $calendarEvent->getTitle(),
                    'start' => [
                        'dateTime' => $calendarEvent->getStartDateTime()->format('Y-m-d\TH:i:s'),
                        'timeZone' => $user->getTimezone(),
                    ],
                    'end' => [
                        'dateTime' => $calendarEvent->getEndDateTime()->format('Y-m-d\TH:i:s'),
                        'timeZone' => $user->getTimezone(),
                    ],
                ];

                if ($calendarEvent->getDescription()) {
                    $eventData['body'] = [
                        'contentType' => 'HTML',
                        'content' => $calendarEvent->getDescription(),
                    ];
                }

                if ($calendarEvent->getLocation()) {
                    $eventData['location'] = [
                        'displayName' => $calendarEvent->getLocation(),
                    ];
                }

                if ($calendarEvent->getCategory()) {
                    $eventData['categories'] = [$calendarEvent->getCategory()];
                }

                $this->microsoftGraphService->updateEvent(
                    $user,
                    $calendarEvent->getMicrosoftEventId(),
                    $eventData
                );

                $calendarEvent->markAsSynced();
                $this->entityManager->flush();

                $this->addFlash('success', 'Événement mis à jour avec succès.');

                return $this->redirectToRoute('app_customer_show', ['id' => $customerId]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to update event', [
                    'event_id' => $calendarEvent->getId(),
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', sprintf('Erreur lors de la mise à jour : %s', $e->getMessage()));
            }
        }

        return $this->render('calendar_event/edit.html.twig', [
            'customer' => $customer,
            'calendarEvent' => $calendarEvent,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, int $customerId, CalendarEvent $calendarEvent): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        // Check if user is the creator or admin
        if ($calendarEvent->getCreatedBy()->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cet événement');
        }

        if ($this->isCsrfTokenValid('delete'.$calendarEvent->getId(), $request->request->get('_token'))) {
            try {
                // Delete from Microsoft Calendar first
                $microsoftEventId = $calendarEvent->getMicrosoftEventId();
                if ($microsoftEventId) {
                    try {
                        $this->microsoftGraphService->deleteEvent($user, $microsoftEventId);
                    } catch (\Exception $e) {
                        // Log but continue - local deletion is more important
                        $this->logger->warning('Failed to delete event from Microsoft Calendar', [
                            'event_id' => $calendarEvent->getId(),
                            'microsoft_event_id' => $microsoftEventId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Delete from local database
                $this->entityManager->remove($calendarEvent);
                $this->entityManager->flush();

                $this->addFlash('success', 'Événement supprimé avec succès');
            } catch (\Exception $e) {
                $this->logger->error('Error deleting calendar event', [
                    'event_id' => $calendarEvent->getId(),
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', 'Erreur lors de la suppression de l\'événement');
            }
        }

        return $this->redirectToRoute('app_customer_show', ['id' => $customerId]);
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
}
