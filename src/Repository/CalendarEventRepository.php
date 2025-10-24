<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CalendarEvent;
use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CalendarEvent>
 */
class CalendarEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarEvent::class);
    }

    /**
     * Find all future events (not cancelled) ordered by start date.
     *
     * @return CalendarEvent[]
     */
    public function findUpcomingEvents(int $limit = 10): array
    {
        return $this->createQueryBuilder('ce')
            ->andWhere('ce.startDateTime > :now')
            ->andWhere('ce.isCancelled = :false')
            ->setParameter('now', new \DateTime())
            ->setParameter('false', false)
            ->orderBy('ce.startDateTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all events for a specific customer.
     *
     * @return CalendarEvent[]
     */
    public function findByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('ce')
            ->andWhere('ce.customer = :customer')
            ->andWhere('ce.isCancelled = :false')
            ->setParameter('customer', $customer)
            ->setParameter('false', false)
            ->orderBy('ce.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all future events for a specific customer.
     *
     * @return CalendarEvent[]
     */
    public function findUpcomingEventsByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('ce')
            ->andWhere('ce.customer = :customer')
            ->andWhere('ce.startDateTime > :now')
            ->andWhere('ce.isCancelled = :false')
            ->setParameter('customer', $customer)
            ->setParameter('now', new \DateTime())
            ->setParameter('false', false)
            ->orderBy('ce.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all events created by a specific user.
     *
     * @return CalendarEvent[]
     */
    public function findByCreator(User $user): array
    {
        return $this->createQueryBuilder('ce')
            ->andWhere('ce.createdBy = :user')
            ->andWhere('ce.isCancelled = :false')
            ->setParameter('user', $user)
            ->setParameter('false', false)
            ->orderBy('ce.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all events for a specific user and customer.
     *
     * @return CalendarEvent[]
     */
    public function findByCreatorAndCustomer(User $user, Customer $customer): array
    {
        return $this->createQueryBuilder('ce')
            ->andWhere('ce.createdBy = :user')
            ->andWhere('ce.customer = :customer')
            ->andWhere('ce.isCancelled = :false')
            ->setParameter('user', $user)
            ->setParameter('customer', $customer)
            ->setParameter('false', false)
            ->orderBy('ce.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find an event by its Microsoft Event ID.
     */
    public function findByMicrosoftEventId(string $microsoftEventId): ?CalendarEvent
    {
        return $this->createQueryBuilder('ce')
            ->andWhere('ce.microsoftEventId = :microsoftEventId')
            ->setParameter('microsoftEventId', $microsoftEventId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find events that need synchronization.
     *
     * @return CalendarEvent[]
     */
    public function findEventsNeedingSync(): array
    {
        $oneHourAgo = new \DateTime('-1 hour');

        return $this->createQueryBuilder('ce')
            ->andWhere('ce.isCancelled = :false')
            ->andWhere('ce.startDateTime > :now')
            ->andWhere('ce.syncedAt IS NULL OR ce.syncedAt < :oneHourAgo')
            ->setParameter('false', false)
            ->setParameter('now', new \DateTime())
            ->setParameter('oneHourAgo', $oneHourAgo)
            ->orderBy('ce.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events within a date range.
     *
     * @return CalendarEvent[]
     */
    public function findEventsBetween(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('ce')
            ->andWhere('ce.startDateTime >= :startDate')
            ->andWhere('ce.startDateTime <= :endDate')
            ->andWhere('ce.isCancelled = :false')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('false', false)
            ->orderBy('ce.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count upcoming events for a customer.
     */
    public function countUpcomingEventsByCustomer(Customer $customer): int
    {
        return (int) $this->createQueryBuilder('ce')
            ->select('COUNT(ce.id)')
            ->andWhere('ce.customer = :customer')
            ->andWhere('ce.startDateTime > :now')
            ->andWhere('ce.isCancelled = :false')
            ->setParameter('customer', $customer)
            ->setParameter('now', new \DateTime())
            ->setParameter('false', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Save a calendar event.
     */
    public function save(CalendarEvent $calendarEvent, bool $flush = true): void
    {
        $this->getEntityManager()->persist($calendarEvent);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a calendar event.
     */
    public function remove(CalendarEvent $calendarEvent, bool $flush = true): void
    {
        $this->getEntityManager()->remove($calendarEvent);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
