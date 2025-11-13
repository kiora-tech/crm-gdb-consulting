<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Repository\CalendarEventRepository;
use App\Service\CalendarEventSyncService;
use App\Service\MicrosoftGraphService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CalendarEventSyncServiceTest extends TestCase
{
    private CalendarEventRepository&MockObject $calendarEventRepository;
    private MicrosoftGraphService&MockObject $microsoftGraphService;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private CalendarEventSyncService $syncService;

    protected function setUp(): void
    {
        $this->calendarEventRepository = $this->createMock(CalendarEventRepository::class);
        $this->microsoftGraphService = $this->createMock(MicrosoftGraphService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->syncService = new CalendarEventSyncService(
            $this->calendarEventRepository,
            $this->microsoftGraphService,
            $this->entityManager,
            $this->logger
        );
    }

    public function testSyncEventWithMicrosoftWithNoCreatorLogsWarningAndReturns(): void
    {
        $event = new CalendarEvent();
        $event->setTitle('Test Event');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Cannot sync event: no creator', $this->arrayHasKey('event_id'));

        $this->microsoftGraphService->expects($this->never())
            ->method('getEventById');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->syncService->syncEventWithMicrosoft($event);
    }

    public function testSyncEventWithMicrosoftWithNoMicrosoftEventIdLogsWarningAndReturns(): void
    {
        $event = new CalendarEvent();
        $event->setTitle('Test Event');
        $event->setCreatedBy($this->createMock(User::class));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Cannot sync event: no Microsoft event ID', $this->arrayHasKey('event_id'));

        $this->microsoftGraphService->expects($this->never())
            ->method('getEventById');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->syncService->syncEventWithMicrosoft($event);
    }

    public function testSyncEventWithMicrosoftWithSuccessfulSyncUpdatesEvent(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setTitle('Original Title');
        $event->setCreatedBy($user);
        $event->setMicrosoftEventId('microsoft-event-123');
        $event->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $microsoftEvent = [
            'subject' => 'Updated Title',
            'start' => ['dateTime' => '2025-10-20T14:00:00'],
            'end' => ['dateTime' => '2025-10-20T15:00:00'],
            'location' => ['displayName' => 'Updated Location'],
            'body' => ['content' => 'Updated description'],
            'isCancelled' => false,
        ];

        $this->microsoftGraphService->expects($this->once())
            ->method('getEventById')
            ->with($user, 'microsoft-event-123')
            ->willReturn($microsoftEvent);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Event synced successfully', $this->arrayHasKey('event_id'));

        $this->syncService->syncEventWithMicrosoft($event);

        $this->assertSame('Updated Title', $event->getTitle());
        $this->assertSame('Updated Location', $event->getLocation());
        $this->assertSame('Updated description', $event->getDescription());
        $this->assertNotNull($event->getSyncedAt());
    }

    public function testSyncEventWithMicrosoftWithDeletedEventDeletesFromDatabase(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setTitle('Test Event');
        $event->setCreatedBy($user);
        $event->setMicrosoftEventId('microsoft-event-123');
        $event->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $this->microsoftGraphService->expects($this->once())
            ->method('getEventById')
            ->with($user, 'microsoft-event-123')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($event);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Event deleted in Microsoft, deleting from local database', $this->arrayHasKey('microsoft_event_id'));

        $this->syncService->syncEventWithMicrosoft($event);
    }

    public function testSyncEventWithMicrosoftWithCancelledEventUpdatesCancelledStatus(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setTitle('Test Event');
        $event->setCreatedBy($user);
        $event->setMicrosoftEventId('microsoft-event-123');
        $event->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $microsoftEvent = [
            'subject' => 'Test Event',
            'isCancelled' => true,
        ];

        $this->microsoftGraphService->expects($this->once())
            ->method('getEventById')
            ->with($user, 'microsoft-event-123')
            ->willReturn($microsoftEvent);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->syncService->syncEventWithMicrosoft($event);

        $this->assertTrue($event->isCancelled());
    }

    public function testSyncEventWithMicrosoftWithApiExceptionLogsError(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setTitle('Test Event');
        $event->setCreatedBy($user);
        $event->setMicrosoftEventId('microsoft-event-123');
        $event->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $this->microsoftGraphService->expects($this->once())
            ->method('getEventById')
            ->with($user, 'microsoft-event-123')
            ->willThrowException(new \RuntimeException('API Error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error syncing event with Microsoft', $this->arrayHasKey('error'));

        $this->entityManager->expects($this->never())
            ->method('flush');

        // Should not throw exception - errors are logged
        $this->syncService->syncEventWithMicrosoft($event);
    }

    public function testSyncAllPendingEventsWithMultipleEventsSyncsAll(): void
    {
        $user = $this->createMock(User::class);

        $event1 = new CalendarEvent();
        $event1->setTitle('Event 1');
        $event1->setCreatedBy($user);
        $event1->setMicrosoftEventId('microsoft-event-1');
        $event1->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event1->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $event2 = new CalendarEvent();
        $event2->setTitle('Event 2');
        $event2->setCreatedBy($user);
        $event2->setMicrosoftEventId('microsoft-event-2');
        $event2->setStartDateTime(new \DateTime('2025-10-21 10:00:00'));
        $event2->setEndDateTime(new \DateTime('2025-10-21 11:00:00'));

        $events = [$event1, $event2];

        $this->calendarEventRepository->expects($this->once())
            ->method('findEventsNeedingSync')
            ->willReturn($events);

        $this->microsoftGraphService->expects($this->exactly(2))
            ->method('getEventById')
            ->willReturn(['subject' => 'Updated']);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        $syncedCount = $this->syncService->syncAllPendingEvents();

        $this->assertSame(2, $syncedCount);
    }

    public function testSyncAllPendingEventsWithNoEventsReturnsZero(): void
    {
        $this->calendarEventRepository->expects($this->once())
            ->method('findEventsNeedingSync')
            ->willReturn([]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Bulk sync completed', [
                'total_events' => 0,
                'synced_count' => 0,
            ]);

        $syncedCount = $this->syncService->syncAllPendingEvents();

        $this->assertSame(0, $syncedCount);
    }

    public function testSyncAllPendingEventsWithPartialFailuresContinuesSyncing(): void
    {
        $user = $this->createMock(User::class);

        $event1 = new CalendarEvent();
        $event1->setTitle('Event 1');
        $event1->setCreatedBy($user);
        $event1->setMicrosoftEventId('microsoft-event-1');
        $event1->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event1->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $event2 = new CalendarEvent();
        $event2->setTitle('Event 2');
        $event2->setCreatedBy($user);
        $event2->setMicrosoftEventId('microsoft-event-2');
        $event2->setStartDateTime(new \DateTime('2025-10-21 10:00:00'));
        $event2->setEndDateTime(new \DateTime('2025-10-21 11:00:00'));

        $events = [$event1, $event2];

        $this->calendarEventRepository->expects($this->once())
            ->method('findEventsNeedingSync')
            ->willReturn($events);

        // First event will fail (getEventById throws exception, caught in syncEventWithMicrosoft)
        // Second event will succeed
        $this->microsoftGraphService->expects($this->exactly(2))
            ->method('getEventById')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('API Error for event 1')),
                ['subject' => 'Updated']
            );

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        // Both events will be "processed" (syncedCount incremented in foreach),
        // but first one logs an error internally in syncEventWithMicrosoft
        $syncedCount = $this->syncService->syncAllPendingEvents();

        // Both are counted as synced since exceptions are caught in syncEventWithMicrosoft
        $this->assertSame(2, $syncedCount);
    }

    public function testCreateEventInMicrosoftWithValidEventReturnsEventId(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setTitle('New Event');
        $event->setDescription('Event description');
        $event->setLocation('Office');
        $event->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $microsoftEvent = [
            'id' => 'microsoft-new-event-123',
            'subject' => 'New Event',
        ];

        $this->microsoftGraphService->expects($this->once())
            ->method('createEventFromCalendarEvent')
            ->with(
                $user,
                'New Event',
                '2025-10-20T10:00:00',
                '2025-10-20T11:00:00',
                'Event description',
                'Office',
                null
            )
            ->willReturn($microsoftEvent);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Event created in Microsoft Calendar', $this->arrayHasKey('microsoft_event_id'));

        $result = $this->syncService->createEventInMicrosoft($event, $user);

        $this->assertArrayHasKey('id', $result);
        $this->assertSame('microsoft-new-event-123', $result['id']);
    }

    public function testCreateEventInMicrosoftWithNullTitleUsesSansTitre(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $microsoftEvent = [
            'id' => 'microsoft-new-event-123',
        ];

        $this->microsoftGraphService->expects($this->once())
            ->method('createEventFromCalendarEvent')
            ->with(
                $user,
                'Sans titre',
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn($microsoftEvent);

        $result = $this->syncService->createEventInMicrosoft($event, $user);

        $this->assertArrayHasKey('id', $result);
        $this->assertSame('microsoft-new-event-123', $result['id']);
    }

    public function testCreateEventInMicrosoftWithoutStartDateTimeThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setTitle('Test Event');
        $event->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Event must have start and end date times');

        $this->syncService->createEventInMicrosoft($event, $user);
    }

    public function testCreateEventInMicrosoftWithoutEndDateTimeThrowsException(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setTitle('Test Event');
        $event->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Event must have start and end date times');

        $this->syncService->createEventInMicrosoft($event, $user);
    }

    public function testCreateEventInMicrosoftWithApiExceptionThrowsWrappedException(): void
    {
        $user = $this->createMock(User::class);
        $event = new CalendarEvent();
        $event->setTitle('Test Event');
        $event->setStartDateTime(new \DateTime('2025-10-20 10:00:00'));
        $event->setEndDateTime(new \DateTime('2025-10-20 11:00:00'));

        $this->microsoftGraphService->expects($this->once())
            ->method('createEventFromCalendarEvent')
            ->willThrowException(new \RuntimeException('API Error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error creating event in Microsoft', $this->arrayHasKey('error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create event in Microsoft Calendar');

        $this->syncService->createEventInMicrosoft($event, $user);
    }
}
