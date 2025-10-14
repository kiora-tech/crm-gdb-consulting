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

        // Create new calendar event
        $calendarEvent = new CalendarEvent();
        $calendarEvent->setCustomer($customer);
        $calendarEvent->setCreatedBy($user);

        $form = $this->createForm(CalendarEventType::class, $calendarEvent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Create event in Microsoft Calendar
                $microsoftEventId = $this->syncService->createEventInMicrosoft($calendarEvent, $user);

                // Save to database with Microsoft event ID
                $calendarEvent->setMicrosoftEventId($microsoftEventId);
                $calendarEvent->markAsSynced();

                $this->entityManager->persist($calendarEvent);
                $this->entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Événement "%s" créé avec succès dans votre calendrier Microsoft.',
                    $calendarEvent->getTitle()
                ));

                return $this->redirectToRoute('app_customer_show', ['id' => $customerId]);
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
            $this->entityManager->remove($calendarEvent);
            $this->entityManager->flush();

            $this->addFlash('success', 'Événement supprimé avec succès');
        }

        return $this->redirectToRoute('app_customer_show', ['id' => $customerId]);
    }
}
