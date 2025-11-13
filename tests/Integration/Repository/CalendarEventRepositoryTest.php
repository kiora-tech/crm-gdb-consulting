<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\CalendarEvent;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\ProspectOrigin;
use App\Entity\User;
use App\Repository\CalendarEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CalendarEventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CalendarEventRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $repository = $this->entityManager->getRepository(CalendarEvent::class);
        assert($repository instanceof CalendarEventRepository);
        $this->repository = $repository;
    }

    private function createTestCompany(): Company
    {
        $company = new Company();
        $company->setName('Test Company');

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return $company;
    }

    private function createTestUser(Company $company, string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('password');
        $user->setCompany($company);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createTestCustomer(User $user, string $name = 'Test Customer'): Customer
    {
        $customer = new Customer();
        $customer->setName($name);
        $customer->setOrigin(ProspectOrigin::ACQUISITION);
        $customer->setUser($user);

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    private function createTestEvent(
        User $user,
        Customer $customer,
        \DateTimeInterface $startDateTime,
        string $title = 'Test Event',
        bool $cancelled = false,
    ): CalendarEvent {
        $event = new CalendarEvent();
        $event->setTitle($title);
        $event->setCreatedBy($user);
        $event->setCustomer($customer);
        $event->setStartDateTime($startDateTime);
        $endDateTime = \DateTime::createFromInterface($startDateTime);
        $endDateTime->modify('+1 hour');
        $event->setEndDateTime($endDateTime);
        $event->setMicrosoftEventId('microsoft-event-'.uniqid());
        $event->setIsCancelled($cancelled);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    public function testFindUpcomingEventsReturnsOnlyFutureEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        // Create past, present, and future events
        $this->createTestEvent($user, $customer, new \DateTime('-2 days'), 'Past Event');
        $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Future Event 1');
        $this->createTestEvent($user, $customer, new \DateTime('+2 days'), 'Future Event 2');

        $this->entityManager->clear();

        $upcomingEvents = $this->repository->findUpcomingEvents();

        $this->assertCount(2, $upcomingEvents);
        $this->assertSame('Future Event 1', $upcomingEvents[0]->getTitle());
        $this->assertSame('Future Event 2', $upcomingEvents[1]->getTitle());
    }

    public function testFindUpcomingEventsExcludesCancelledEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Active Event', false);
        $this->createTestEvent($user, $customer, new \DateTime('+2 days'), 'Cancelled Event', true);

        $this->entityManager->clear();

        $upcomingEvents = $this->repository->findUpcomingEvents();

        $this->assertCount(1, $upcomingEvents);
        $this->assertSame('Active Event', $upcomingEvents[0]->getTitle());
    }

    public function testFindUpcomingEventsRespectsLimit(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        for ($i = 1; $i <= 15; ++$i) {
            $this->createTestEvent($user, $customer, new \DateTime("+{$i} days"), "Event {$i}");
        }

        $this->entityManager->clear();

        $upcomingEvents = $this->repository->findUpcomingEvents(5);

        $this->assertCount(5, $upcomingEvents);
    }

    public function testFindUpcomingEventsOrdersByStartDateAsc(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $this->createTestEvent($user, $customer, new \DateTime('+3 days'), 'Event 3');
        $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Event 1');
        $this->createTestEvent($user, $customer, new \DateTime('+2 days'), 'Event 2');

        $this->entityManager->clear();

        $upcomingEvents = $this->repository->findUpcomingEvents();

        $this->assertSame('Event 1', $upcomingEvents[0]->getTitle());
        $this->assertSame('Event 2', $upcomingEvents[1]->getTitle());
        $this->assertSame('Event 3', $upcomingEvents[2]->getTitle());
    }

    public function testFindByCustomerReturnsCustomerEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer1 = $this->createTestCustomer($user, 'Customer 1');
        $customer2 = $this->createTestCustomer($user, 'Customer 2');

        $this->createTestEvent($user, $customer1, new \DateTime('+1 day'), 'Customer 1 Event');
        $this->createTestEvent($user, $customer2, new \DateTime('+1 day'), 'Customer 2 Event');

        $this->entityManager->clear();

        // Reload customer to avoid detached entity issues
        $customer1 = $this->entityManager->getRepository(Customer::class)->find($customer1->getId());

        $events = $this->repository->findByCustomer($customer1);

        $this->assertCount(1, $events);
        $this->assertSame('Customer 1 Event', $events[0]->getTitle());
    }

    public function testFindByCustomerExcludesCancelledEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Active Event', false);
        $this->createTestEvent($user, $customer, new \DateTime('+2 days'), 'Cancelled Event', true);

        $this->entityManager->clear();

        $customer = $this->entityManager->getRepository(Customer::class)->find($customer->getId());
        $events = $this->repository->findByCustomer($customer);

        $this->assertCount(1, $events);
        $this->assertSame('Active Event', $events[0]->getTitle());
    }

    public function testFindUpcomingEventsByCustomerReturnsOnlyFutureEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $this->createTestEvent($user, $customer, new \DateTime('-1 day'), 'Past Event');
        $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Future Event');

        $this->entityManager->clear();

        $customer = $this->entityManager->getRepository(Customer::class)->find($customer->getId());
        $events = $this->repository->findUpcomingEventsByCustomer($customer);

        $this->assertCount(1, $events);
        $this->assertSame('Future Event', $events[0]->getTitle());
    }

    public function testFindByCreatorReturnsUserEvents(): void
    {
        $company = $this->createTestCompany();
        $user1 = $this->createTestUser($company, 'user1@example.com');
        $user2 = $this->createTestUser($company, 'user2@example.com');
        $customer = $this->createTestCustomer($user1);

        $this->createTestEvent($user1, $customer, new \DateTime('+1 day'), 'User 1 Event');
        $this->createTestEvent($user2, $customer, new \DateTime('+1 day'), 'User 2 Event');

        $this->entityManager->clear();

        $user1 = $this->entityManager->getRepository(User::class)->find($user1->getId());
        $events = $this->repository->findByCreator($user1);

        $this->assertCount(1, $events);
        $this->assertSame('User 1 Event', $events[0]->getTitle());
    }

    public function testFindByCreatorAndCustomerReturnsFilteredEvents(): void
    {
        $company = $this->createTestCompany();
        $user1 = $this->createTestUser($company, 'user1@example.com');
        $user2 = $this->createTestUser($company, 'user2@example.com');
        $customer1 = $this->createTestCustomer($user1, 'Customer 1');
        $customer2 = $this->createTestCustomer($user1, 'Customer 2');

        $this->createTestEvent($user1, $customer1, new \DateTime('+1 day'), 'User1-Customer1');
        $this->createTestEvent($user1, $customer2, new \DateTime('+1 day'), 'User1-Customer2');
        $this->createTestEvent($user2, $customer1, new \DateTime('+1 day'), 'User2-Customer1');

        $this->entityManager->clear();

        $user1 = $this->entityManager->getRepository(User::class)->find($user1->getId());
        $customer1 = $this->entityManager->getRepository(Customer::class)->find($customer1->getId());
        $events = $this->repository->findByCreatorAndCustomer($user1, $customer1);

        $this->assertCount(1, $events);
        $this->assertSame('User1-Customer1', $events[0]->getTitle());
    }

    public function testFindByMicrosoftEventIdFindsEvent(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $event = $this->createTestEvent($user, $customer, new \DateTime('+1 day'));
        $microsoftEventId = $event->getMicrosoftEventId();

        $this->entityManager->clear();

        $foundEvent = $this->repository->findByMicrosoftEventId($microsoftEventId);

        $this->assertNotNull($foundEvent);
        $this->assertSame($microsoftEventId, $foundEvent->getMicrosoftEventId());
    }

    public function testFindByMicrosoftEventIdReturnsNullIfNotFound(): void
    {
        $event = $this->repository->findByMicrosoftEventId('non-existent-id');

        $this->assertNull($event);
    }

    public function testFindEventsNeedingSyncReturnsUnsyncedEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        // Create event never synced
        $event1 = $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Never synced');
        $event1->setSyncedAt(null);

        // Create event synced 2 hours ago
        $event2 = $this->createTestEvent($user, $customer, new \DateTime('+2 days'), 'Old sync');
        $event2->setSyncedAt(new \DateTime('-2 hours'));

        // Create event synced 30 minutes ago (should not need sync)
        $event3 = $this->createTestEvent($user, $customer, new \DateTime('+3 days'), 'Recent sync');
        $event3->setSyncedAt(new \DateTime('-30 minutes'));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $events = $this->repository->findEventsNeedingSync();

        $this->assertCount(2, $events);
        $titles = array_map(fn ($e) => $e->getTitle(), $events);
        $this->assertContains('Never synced', $titles);
        $this->assertContains('Old sync', $titles);
        $this->assertNotContains('Recent sync', $titles);
    }

    public function testFindEventsNeedingSyncExcludesCancelledEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $event = $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Cancelled', true);
        $event->setSyncedAt(null);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $events = $this->repository->findEventsNeedingSync();

        $this->assertCount(0, $events);
    }

    public function testFindEventsNeedingSyncExcludesPastEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $event = $this->createTestEvent($user, $customer, new \DateTime('-1 day'), 'Past event');
        $event->setSyncedAt(null);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $events = $this->repository->findEventsNeedingSync();

        $this->assertCount(0, $events);
    }

    public function testFindEventsBetweenReturnsEventsInRange(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $this->createTestEvent($user, $customer, new \DateTime('2025-10-20 10:00:00'), 'Event 1');
        $this->createTestEvent($user, $customer, new \DateTime('2025-10-22 10:00:00'), 'Event 2');
        $this->createTestEvent($user, $customer, new \DateTime('2025-10-25 10:00:00'), 'Event 3');

        $this->entityManager->clear();

        $startDate = new \DateTime('2025-10-20 00:00:00');
        $endDate = new \DateTime('2025-10-23 23:59:59');

        $events = $this->repository->findEventsBetween($startDate, $endDate);

        $this->assertCount(2, $events);
        $this->assertSame('Event 1', $events[0]->getTitle());
        $this->assertSame('Event 2', $events[1]->getTitle());
    }

    public function testFindEventsBetweenExcludesCancelledEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $this->createTestEvent($user, $customer, new \DateTime('2025-10-20 10:00:00'), 'Active', false);
        $this->createTestEvent($user, $customer, new \DateTime('2025-10-21 10:00:00'), 'Cancelled', true);

        $this->entityManager->clear();

        $startDate = new \DateTime('2025-10-20 00:00:00');
        $endDate = new \DateTime('2025-10-22 23:59:59');

        $events = $this->repository->findEventsBetween($startDate, $endDate);

        $this->assertCount(1, $events);
        $this->assertSame('Active', $events[0]->getTitle());
    }

    public function testCountUpcomingEventsByCustomerReturnsCorrectCount(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Event 1');
        $this->createTestEvent($user, $customer, new \DateTime('+2 days'), 'Event 2');
        $this->createTestEvent($user, $customer, new \DateTime('-1 day'), 'Past Event');

        $this->entityManager->clear();

        $customer = $this->entityManager->getRepository(Customer::class)->find($customer->getId());
        $count = $this->repository->countUpcomingEventsByCustomer($customer);

        $this->assertSame(2, $count);
    }

    public function testCountUpcomingEventsByCustomerExcludesCancelledEvents(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $this->createTestEvent($user, $customer, new \DateTime('+1 day'), 'Active', false);
        $this->createTestEvent($user, $customer, new \DateTime('+2 days'), 'Cancelled', true);

        $this->entityManager->clear();

        $customer = $this->entityManager->getRepository(Customer::class)->find($customer->getId());
        $count = $this->repository->countUpcomingEventsByCustomer($customer);

        $this->assertSame(1, $count);
    }

    public function testSavePersistsAndFlushesEvent(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $event = new CalendarEvent();
        $event->setTitle('New Event');
        $event->setCreatedBy($user);
        $event->setCustomer($customer);
        $event->setStartDateTime(new \DateTime('+1 day'));
        $event->setEndDateTime(new \DateTime('+1 day +1 hour'));
        $event->setMicrosoftEventId('microsoft-event-new');

        $this->repository->save($event, true);

        $this->assertNotNull($event->getId());

        $this->entityManager->clear();

        $foundEvent = $this->repository->find($event->getId());
        $this->assertNotNull($foundEvent);
        $this->assertSame('New Event', $foundEvent->getTitle());
    }

    public function testSaveWithoutFlushDoesNotPersistImmediately(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $event = new CalendarEvent();
        $event->setTitle('New Event');
        $event->setCreatedBy($user);
        $event->setCustomer($customer);
        $event->setStartDateTime(new \DateTime('+1 day'));
        $event->setEndDateTime(new \DateTime('+1 day +1 hour'));
        $event->setMicrosoftEventId('microsoft-event-new');

        $this->repository->save($event, false);

        // Event should not have ID yet
        $this->assertNull($event->getId());

        // Manually flush
        $this->entityManager->flush();

        $this->assertNotNull($event->getId());
    }

    public function testRemoveDeletesEvent(): void
    {
        $company = $this->createTestCompany();
        $user = $this->createTestUser($company);
        $customer = $this->createTestCustomer($user);

        $event = $this->createTestEvent($user, $customer, new \DateTime('+1 day'));
        $eventId = $event->getId();

        $this->entityManager->clear();

        $event = $this->repository->find($eventId);
        $this->repository->remove($event, true);

        $this->entityManager->clear();

        $foundEvent = $this->repository->find($eventId);
        $this->assertNull($foundEvent);
    }
}
