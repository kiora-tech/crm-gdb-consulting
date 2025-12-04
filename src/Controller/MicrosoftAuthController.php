<?php

namespace App\Controller;

use App\Entity\MicrosoftToken;
use App\Entity\User;
use App\Service\MicrosoftGraphService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MicrosoftAuthController extends AbstractController
{
    public function __construct(
        private readonly MicrosoftGraphService $microsoftGraphService,
    ) {
    }

    #[Route('/connect/microsoft', name: 'connect_microsoft_start')]
    public function connectAction(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('microsoft')
            ->redirect([
                'openid',
                'profile',
                'email',
                'https://graph.microsoft.com/Tasks.ReadWrite',
                'https://graph.microsoft.com/Tasks.ReadWrite.Shared',
                'https://graph.microsoft.com/Mail.ReadWrite',
                'https://graph.microsoft.com/Calendars.ReadWrite',
            ], [
                'prompt' => 'select_account',
            ]);
    }

    #[Route('/connect/microsoft/check', name: 'connect_microsoft_check')]
    public function connectCheckAction(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
    ): Response {
        $client = $clientRegistry->getClient('microsoft');

        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \LogicException('User must be logged in to connect Microsoft account');
            }

            $accessToken = $client->getAccessToken();

            // Update existing token or create new one
            $microsoftToken = $user->getMicrosoftToken();
            if (!$microsoftToken) {
                $microsoftToken = new MicrosoftToken();
                $microsoftToken->setUser($user);
                $entityManager->persist($microsoftToken);
            }

            $microsoftToken->setAccessToken($accessToken->getToken());
            $microsoftToken->setRefreshToken($accessToken->getRefreshToken());
            $microsoftToken->setExpiresAt((new \DateTime())->setTimestamp($accessToken->getExpires()));
            $scope = $accessToken->getValues()['scope'] ?? null;
            if (is_array($scope)) {
                $microsoftToken->setScope(implode(' ', $scope));
            } else {
                $microsoftToken->setScope((string) $scope);
            }

            // Récupérer l'email du compte Microsoft depuis l'API Graph
            try {
                $userInfo = $this->microsoftGraphService->getUserProfile($user);
                if (isset($userInfo['userPrincipalName'])) {
                    $microsoftToken->setMicrosoftEmail($userInfo['userPrincipalName']);
                } elseif (isset($userInfo['mail'])) {
                    $microsoftToken->setMicrosoftEmail($userInfo['mail']);
                }
            } catch (\Exception $e) {
                // Si on ne peut pas récupérer l'email, ce n'est pas bloquant
                $this->addFlash('warning', 'Connected but could not retrieve Microsoft email');
            }

            $entityManager->flush();

            $this->addFlash('success', 'Microsoft account connected successfully!');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error connecting Microsoft account: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_user_profile');
    }

    #[Route('/disconnect/microsoft', name: 'disconnect_microsoft')]
    public function disconnectAction(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        $microsoftToken = $user->getMicrosoftToken();
        if ($microsoftToken) {
            $entityManager->remove($microsoftToken);
            $entityManager->flush();
            $this->addFlash('success', 'Microsoft account disconnected successfully!');
        }

        return $this->redirectToRoute('app_user_profile');
    }

    #[Route('/microsoft/create-test-task', name: 'create_test_task')]
    public function createTestTask(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('error', 'You must connect your Microsoft account first.');

            return $this->redirectToRoute('app_user_profile');
        }

        try {
            $task = $this->microsoftGraphService->createTestTask($user);
            $this->addFlash('success', sprintf('Test task "%s" created successfully in %s!', $task['title'], $task['listName']));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Provide more user-friendly error messages
            if (str_contains($errorMessage, 'refresh Microsoft token') || str_contains($errorMessage, 'No refresh token available')) {
                $this->addFlash('error', 'Your Microsoft connection has expired. Please disconnect and reconnect your Microsoft account.');
            } elseif (str_contains($errorMessage, '401')) {
                $this->addFlash('error', 'Authentication failed with Microsoft. Please try disconnecting and reconnecting your account.');
            } elseif (str_contains($errorMessage, 'Microsoft To-Do is not available') || str_contains($errorMessage, '404')) {
                $this->addFlash('error', 'Microsoft To-Do is not available for this account. Please ensure you have Microsoft To-Do enabled in your Microsoft 365 account, or try opening https://to-do.microsoft.com to activate it.');
            } else {
                $this->addFlash('error', 'Error creating test task: '.$errorMessage);
            }
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/microsoft/create-outlook-test-task', name: 'create_outlook_test_task')]
    public function createOutlookTestTask(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('error', 'You must connect your Microsoft account first.');

            return $this->redirectToRoute('app_user_profile');
        }

        try {
            $task = $this->microsoftGraphService->createOutlookTestTask($user);
            $this->addFlash('success', sprintf('Tâche Outlook "%s" créée avec succès!', $task['subject']));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Provide more user-friendly error messages
            if (str_contains($errorMessage, 'refresh Microsoft token') || str_contains($errorMessage, 'No refresh token available')) {
                $this->addFlash('error', 'Your Microsoft connection has expired. Please disconnect and reconnect your Microsoft account.');
            } elseif (str_contains($errorMessage, '401')) {
                $this->addFlash('error', 'Authentication failed with Microsoft. Please try disconnecting and reconnecting your account.');
            } elseif (str_contains($errorMessage, '403') || str_contains($errorMessage, 'Forbidden')) {
                $this->addFlash('error', 'Access denied. Your account may not have the required permissions for Outlook tasks.');
            } else {
                $this->addFlash('error', 'Error creating Outlook test task: '.$errorMessage);
            }
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/microsoft/get-outlook-tasks', name: 'get_outlook_tasks')]
    public function getOutlookTasks(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('error', 'You must connect your Microsoft account first.');

            return $this->redirectToRoute('app_user_profile');
        }

        try {
            $tasks = $this->microsoftGraphService->getOutlookTasks($user);
            $taskCount = count($tasks);

            if ($taskCount > 0) {
                $this->addFlash('success', sprintf('%d tâches Outlook trouvées!', $taskCount));
            } else {
                $this->addFlash('info', 'Aucune tâche Outlook trouvée.');
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Provide more user-friendly error messages
            if (str_contains($errorMessage, 'refresh Microsoft token') || str_contains($errorMessage, 'No refresh token available')) {
                $this->addFlash('error', 'Your Microsoft connection has expired. Please disconnect and reconnect your Microsoft account.');
            } elseif (str_contains($errorMessage, '401')) {
                $this->addFlash('error', 'Authentication failed with Microsoft. Please try disconnecting and reconnecting your account.');
            } elseif (str_contains($errorMessage, '403') || str_contains($errorMessage, 'Forbidden')) {
                $this->addFlash('error', 'Access denied. Your account may not have the required permissions for Outlook tasks.');
            } else {
                $this->addFlash('error', 'Error fetching Outlook tasks: '.$errorMessage);
            }
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/microsoft/create-calendar-event', name: 'create_calendar_event')]
    public function createCalendarEvent(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('error', 'You must connect your Microsoft account first.');

            return $this->redirectToRoute('app_user_profile');
        }

        // Use user's default calendar
        try {
            $calendarId = $user->getDefaultCalendarId();
            $event = $this->microsoftGraphService->createCalendarEvent($user, $calendarId);
            $this->addFlash('success', sprintf('Événement "%s" créé avec succès dans votre calendrier!', $event['subject']));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Provide more user-friendly error messages
            if (str_contains($errorMessage, 'refresh Microsoft token') || str_contains($errorMessage, 'No refresh token available')) {
                $this->addFlash('error', 'Your Microsoft connection has expired. Please disconnect and reconnect your Microsoft account.');
            } elseif (str_contains($errorMessage, '401')) {
                $this->addFlash('error', 'Authentication failed with Microsoft. Please try disconnecting and reconnecting your account.');
            } elseif (str_contains($errorMessage, '403') || str_contains($errorMessage, 'Forbidden')) {
                $this->addFlash('error', 'Access denied. Your account may not have the required permissions for Calendar.');
            } else {
                $this->addFlash('error', 'Error creating calendar event: '.$errorMessage);
            }
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/microsoft/save-default-calendar', name: 'save_default_calendar', methods: ['POST'])]
    public function saveDefaultCalendar(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        $calendarId = $request->request->get('calendar_id');

        // If empty string, set to null
        if ('' === $calendarId) {
            $calendarId = null;
        }

        $user->setDefaultCalendarId($calendarId);
        $entityManager->flush();

        $this->addFlash('success', 'Calendrier par défaut enregistré avec succès!');

        return $this->redirectToRoute('app_user_profile');
    }
}
