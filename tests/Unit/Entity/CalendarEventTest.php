<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CalendarEvent;
use App\Entity\Customer;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CalendarEventTest extends TestCase
{
    public function testConstructorInitializesTimestamps(): void
    {
        $event = new CalendarEvent();

        $this->assertInstanceOf(\DateTimeInterface::class, $event->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getUpdatedAt());
        $this->assertNull($event->getSyncedAt());
        $this->assertFalse($event->isCancelled());
    }

    public function testGetIdReturnsNullByDefault(): void
    {
        $event = new CalendarEvent();

        $this->assertNull($event->getId());
    }

    public function testMicrosoftEventIdGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $microsoftEventId = 'microsoft-event-123';

        $result = $event->setMicrosoftEventId($microsoftEventId);

        $this->assertSame($event, $result, 'Setter should return self for fluent interface');
        $this->assertSame($microsoftEventId, $event->getMicrosoftEventId());
    }

    public function testTitleGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $title = 'Meeting with client';

        $result = $event->setTitle($title);

        $this->assertSame($event, $result);
        $this->assertSame($title, $event->getTitle());
    }

    public function testDescriptionGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $description = 'Discuss project requirements and timeline';

        $result = $event->setDescription($description);

        $this->assertSame($event, $result);
        $this->assertSame($description, $event->getDescription());
    }

    public function testDescriptionCanBeNull(): void
    {
        $event = new CalendarEvent();
        $event->setDescription('Some description');

        $event->setDescription(null);

        $this->assertNull($event->getDescription());
    }

    public function testStartDateTimeGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $startDateTime = new \DateTime('2025-10-20 10:00:00');

        $result = $event->setStartDateTime($startDateTime);

        $this->assertSame($event, $result);
        $this->assertSame($startDateTime, $event->getStartDateTime());
    }

    public function testEndDateTimeGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $endDateTime = new \DateTime('2025-10-20 11:00:00');

        $result = $event->setEndDateTime($endDateTime);

        $this->assertSame($event, $result);
        $this->assertSame($endDateTime, $event->getEndDateTime());
    }

    public function testLocationGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $location = 'Conference Room A';

        $result = $event->setLocation($location);

        $this->assertSame($event, $result);
        $this->assertSame($location, $event->getLocation());
    }

    public function testLocationCanBeNull(): void
    {
        $event = new CalendarEvent();
        $event->setLocation('Office');

        $event->setLocation(null);

        $this->assertNull($event->getLocation());
    }

    public function testCreatedByGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $user = $this->createMock(User::class);

        $result = $event->setCreatedBy($user);

        $this->assertSame($event, $result);
        $this->assertSame($user, $event->getCreatedBy());
    }

    public function testCustomerGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $customer = $this->createMock(Customer::class);

        $result = $event->setCustomer($customer);

        $this->assertSame($event, $result);
        $this->assertSame($customer, $event->getCustomer());
    }

    public function testCreatedAtGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $createdAt = new \DateTime('2025-10-14 08:00:00');

        $result = $event->setCreatedAt($createdAt);

        $this->assertSame($event, $result);
        $this->assertSame($createdAt, $event->getCreatedAt());
    }

    public function testUpdatedAtGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $updatedAt = new \DateTime('2025-10-14 09:00:00');

        $result = $event->setUpdatedAt($updatedAt);

        $this->assertSame($event, $result);
        $this->assertSame($updatedAt, $event->getUpdatedAt());
    }

    public function testSyncedAtGetterAndSetter(): void
    {
        $event = new CalendarEvent();
        $syncedAt = new \DateTime('2025-10-14 10:00:00');

        $result = $event->setSyncedAt($syncedAt);

        $this->assertSame($event, $result);
        $this->assertSame($syncedAt, $event->getSyncedAt());
    }

    public function testSyncedAtCanBeNull(): void
    {
        $event = new CalendarEvent();
        $event->setSyncedAt(new \DateTime());

        $event->setSyncedAt(null);

        $this->assertNull($event->getSyncedAt());
    }

    public function testIsCancelledGetterAndSetter(): void
    {
        $event = new CalendarEvent();

        $this->assertFalse($event->isCancelled());

        $result = $event->setIsCancelled(true);

        $this->assertSame($event, $result);
        $this->assertTrue($event->isCancelled());
    }

    public function testIsFutureWithFutureDateReturnsTrue(): void
    {
        $event = new CalendarEvent();
        $futureDate = new \DateTime('+1 day');
        $event->setStartDateTime($futureDate);

        $this->assertTrue($event->isFuture());
    }

    public function testIsFutureWithPastDateReturnsFalse(): void
    {
        $event = new CalendarEvent();
        $pastDate = new \DateTime('-1 day');
        $event->setStartDateTime($pastDate);

        $this->assertFalse($event->isFuture());
    }

    public function testIsFutureWithNullStartDateTimeReturnsFalse(): void
    {
        $event = new CalendarEvent();

        $this->assertFalse($event->isFuture());
    }

    public function testIsOngoingWithCurrentEventReturnsTrue(): void
    {
        $event = new CalendarEvent();
        $startDateTime = new \DateTime('-30 minutes');
        $endDateTime = new \DateTime('+30 minutes');
        $event->setStartDateTime($startDateTime);
        $event->setEndDateTime($endDateTime);

        $this->assertTrue($event->isOngoing());
    }

    public function testIsOngoingWithFutureEventReturnsFalse(): void
    {
        $event = new CalendarEvent();
        $startDateTime = new \DateTime('+1 hour');
        $endDateTime = new \DateTime('+2 hours');
        $event->setStartDateTime($startDateTime);
        $event->setEndDateTime($endDateTime);

        $this->assertFalse($event->isOngoing());
    }

    public function testIsOngoingWithPastEventReturnsFalse(): void
    {
        $event = new CalendarEvent();
        $startDateTime = new \DateTime('-2 hours');
        $endDateTime = new \DateTime('-1 hour');
        $event->setStartDateTime($startDateTime);
        $event->setEndDateTime($endDateTime);

        $this->assertFalse($event->isOngoing());
    }

    public function testIsOngoingWithNullDatesReturnsFalse(): void
    {
        $event = new CalendarEvent();

        $this->assertFalse($event->isOngoing());
    }

    public function testIsOngoingWithNullEndDateReturnsFalse(): void
    {
        $event = new CalendarEvent();
        $event->setStartDateTime(new \DateTime('-1 hour'));

        $this->assertFalse($event->isOngoing());
    }

    public function testNeedsSyncWithNullSyncedAtReturnsTrue(): void
    {
        $event = new CalendarEvent();

        $this->assertTrue($event->needsSync());
    }

    public function testNeedsSyncWithRecentSyncReturnsFalse(): void
    {
        $event = new CalendarEvent();
        $recentSync = new \DateTime('-30 minutes');
        $event->setSyncedAt($recentSync);

        $this->assertFalse($event->needsSync());
    }

    public function testNeedsSyncWithOldSyncReturnsTrue(): void
    {
        $event = new CalendarEvent();
        $oldSync = new \DateTime('-2 hours');
        $event->setSyncedAt($oldSync);

        $this->assertTrue($event->needsSync());
    }

    public function testNeedsSyncExactlyOneHourAgoReturnsTrue(): void
    {
        $event = new CalendarEvent();
        $oneHourAgo = new \DateTime('-1 hour -1 second');
        $event->setSyncedAt($oneHourAgo);

        $this->assertTrue($event->needsSync());
    }

    public function testMarkAsSyncedUpdatesSyncedAtAndUpdatedAt(): void
    {
        $event = new CalendarEvent();
        $beforeSync = new \DateTime();

        sleep(1); // Ensure time difference
        $result = $event->markAsSynced();

        $this->assertSame($event, $result);
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getSyncedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getUpdatedAt());
        $this->assertGreaterThanOrEqual($beforeSync, $event->getSyncedAt());
        $this->assertGreaterThanOrEqual($beforeSync, $event->getUpdatedAt());
    }

    public function testMarkAsSyncedUpdatesPreviousSyncedAt(): void
    {
        $event = new CalendarEvent();
        $oldSync = new \DateTime('-2 hours');
        $event->setSyncedAt($oldSync);

        $event->markAsSynced();

        $this->assertGreaterThan($oldSync, $event->getSyncedAt());
    }

    public function testToStringWithTitleReturnsTitle(): void
    {
        $event = new CalendarEvent();
        $title = 'Important Meeting';
        $event->setTitle($title);

        $this->assertSame($title, $event->__toString());
    }

    public function testToStringWithNullTitleReturnsDefaultString(): void
    {
        $event = new CalendarEvent();

        $this->assertSame('Calendar Event', $event->__toString());
    }

    public function testDateTimeInterfacesReturnCorrectTypes(): void
    {
        $event = new CalendarEvent();
        $dateTime = new \DateTime('2025-10-20 10:00:00');

        $event->setStartDateTime($dateTime);
        $event->setEndDateTime($dateTime);
        $event->setCreatedAt($dateTime);
        $event->setUpdatedAt($dateTime);
        $event->setSyncedAt($dateTime);

        $this->assertInstanceOf(\DateTimeInterface::class, $event->getStartDateTime());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getEndDateTime());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getUpdatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getSyncedAt());
    }
}
