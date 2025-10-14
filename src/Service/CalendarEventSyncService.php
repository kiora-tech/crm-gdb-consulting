<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Repository\CalendarEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CalendarEventSyncService
{
    public function __construct(
        private readonly CalendarEventRepository $calendarEventRepository,
        private readonly MicrosoftGraphService $microsoftGraphService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Synchronize a single event with Microsoft Graph API.
     */
    public function syncEventWithMicrosoft(CalendarEvent $event): void
    {
        $user = $event->getCreatedBy();
        if (!$user) {
            $this->logger->warning('Cannot sync event: no creator', ['event_id' => $event->getId()]);

            return;
        }

        $microsoftEventId = $event->getMicrosoftEventId();
        if (!$microsoftEventId) {
            $this->logger->warning('Cannot sync event: no Microsoft event ID', ['event_id' => $event->getId()]);

            return;
        }

        try {
            // Fetch event from Microsoft
            $microsoftEvent = $this->microsoftGraphService->getEventById($user, $microsoftEventId);

            if (null === $microsoftEvent) {
                // Event was deleted in Microsoft
                $this->logger->info('Event deleted in Microsoft, marking as cancelled', [
                    'event_id' => $event->getId(),
                    'microsoft_event_id' => $microsoftEventId,
                ]);
                $event->setIsCancelled(true);
                $event->markAsSynced();
                $this->entityManager->flush();

                return;
            }

            // Update local event with Microsoft data
            if (isset($microsoftEvent['subject'])) {
                $event->setTitle($microsoftEvent['subject']);
            }

            if (isset($microsoftEvent['start']['dateTime'])) {
                $startDateTime = new \DateTime($microsoftEvent['start']['dateTime']);
                $event->setStartDateTime($startDateTime);
            }

            if (isset($microsoftEvent['end']['dateTime'])) {
                $endDateTime = new \DateTime($microsoftEvent['end']['dateTime']);
                $event->setEndDateTime($endDateTime);
            }

            if (isset($microsoftEvent['location']['displayName'])) {
                $event->setLocation($microsoftEvent['location']['displayName']);
            }

            if (isset($microsoftEvent['body']['content'])) {
                $event->setDescription($microsoftEvent['body']['content']);
            }

            // Check if event is cancelled
            if (isset($microsoftEvent['isCancelled']) && true === $microsoftEvent['isCancelled']) {
                $event->setIsCancelled(true);
            }

            $event->markAsSynced();
            $this->entityManager->flush();

            $this->logger->info('Event synced successfully', [
                'event_id' => $event->getId(),
                'microsoft_event_id' => $microsoftEventId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error syncing event with Microsoft', [
                'event_id' => $event->getId(),
                'microsoft_event_id' => $microsoftEventId,
                'error' => $e->getMessage(),
            ]);
            // Don't rethrow - we want to continue with other events
        }
    }

    /**
     * Synchronize all events that need syncing.
     *
     * @return int Number of events synced
     */
    public function syncAllPendingEvents(): int
    {
        $events = $this->calendarEventRepository->findEventsNeedingSync();
        $syncedCount = 0;

        foreach ($events as $event) {
            try {
                $this->syncEventWithMicrosoft($event);
                ++$syncedCount;
            } catch (\Exception $e) {
                $this->logger->error('Failed to sync event', [
                    'event_id' => $event->getId(),
                    'error' => $e->getMessage(),
                ]);
                // Continue with next event
            }
        }

        $this->logger->info('Bulk sync completed', [
            'total_events' => count($events),
            'synced_count' => $syncedCount,
        ]);

        return $syncedCount;
    }

    /**
     * Create an event in Microsoft Calendar and return the Microsoft event ID.
     */
    public function createEventInMicrosoft(CalendarEvent $event, User $user): string
    {
        if (!$event->getStartDateTime() || !$event->getEndDateTime()) {
            throw new \RuntimeException('Event must have start and end date times');
        }

        try {
            $microsoftEvent = $this->microsoftGraphService->createEventFromCalendarEvent(
                $user,
                $event->getTitle() ?? 'Sans titre',
                $event->getStartDateTime()->format('Y-m-d\TH:i:s'),
                $event->getEndDateTime()->format('Y-m-d\TH:i:s'),
                $event->getDescription(),
                $event->getLocation()
            );

            if (!isset($microsoftEvent['id'])) {
                throw new \RuntimeException('Microsoft event ID not returned');
            }

            $this->logger->info('Event created in Microsoft Calendar', [
                'event_id' => $event->getId(),
                'microsoft_event_id' => $microsoftEvent['id'],
                'title' => $event->getTitle(),
            ]);

            return $microsoftEvent['id'];
        } catch (\Exception $e) {
            $this->logger->error('Error creating event in Microsoft', [
                'event_id' => $event->getId(),
                'title' => $event->getTitle(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create event in Microsoft Calendar: '.$e->getMessage(), 0, $e);
        }
    }
}
