<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
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

        try {
            // Get calendar events for the next 30 days
            $events = $this->getCalendarEvents($user);

            return $this->render('outlook_calendar/index.html.twig', [
                'events' => $events,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la récupération des événements : '.$e->getMessage());

            return $this->render('outlook_calendar/index.html.twig', [
                'events' => [],
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCalendarEvents(User $user): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $token = $this->microsoftGraphService->hasValidToken($user);
        }

        // Get the default calendar ID
        $defaultCalendarId = $user->getDefaultCalendarId();
        if (!$defaultCalendarId) {
            throw new \RuntimeException('Aucun calendrier par défaut sélectionné. Veuillez configurer votre calendrier par défaut dans votre profil.');
        }

        // Get events for the next 30 days
        $startDate = new \DateTime();
        $endDate = (new \DateTime())->modify('+30 days');

        // Use the default calendar instead of "me/calendar"
        $url = sprintf(
            'https://graph.microsoft.com/v1.0/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$orderby=start/dateTime&$top=100',
            $defaultCalendarId,
            $startDate->format('Y-m-d\TH:i:s'),
            $endDate->format('Y-m-d\TH:i:s')
        );

        $httpClient = \Symfony\Component\HttpClient\HttpClient::create();

        try {
            $response = $httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                    'Prefer' => 'outlook.timezone="'.$user->getTimezone().'"',
                ],
            ]);

            $data = json_decode($response->getContent(), true);

            return $data['value'] ?? [];
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to fetch calendar events: '.$e->getMessage());
        }
    }
}
