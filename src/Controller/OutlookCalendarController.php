<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MicrosoftGraphCacheService;
use App\Service\MicrosoftGraphService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/outlook-calendar', name: 'app_outlook_calendar')]
class OutlookCalendarController extends AbstractController
{
    public function __construct(
        private readonly MicrosoftGraphService $microsoftGraphService,
        private readonly MicrosoftGraphCacheService $cacheService,
    ) {
    }

    #[Route('', name: '_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        // Check if user has Microsoft token
        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('error', 'Vous devez connecter votre compte Microsoft pour voir votre agenda.');

            return $this->redirectToRoute('app_user_profile');
        }

        // Check if user has a default calendar configured
        if (!$user->getDefaultCalendarId()) {
            $this->addFlash('warning', 'Vous devez sélectionner un calendrier par défaut dans votre profil pour voir votre agenda.');

            return $this->redirectToRoute('app_user_profile');
        }

        // Get calendar events for the next 30 days - FROM CACHE
        $startDate = new \DateTime();
        $endDate = (new \DateTime())->modify('+30 days');

        $events = $this->cacheService->getCalendarEvents(
            $user,
            $user->getDefaultCalendarId(),
            $startDate,
            $endDate
        );

        // L'utilisateur voit immédiatement les événements cachés
        // En arrière-plan, si cache vieux, un refresh async est dispatché

        return $this->render('outlook_calendar/index.html.twig', [
            'events' => $events,
        ]);
    }
}
