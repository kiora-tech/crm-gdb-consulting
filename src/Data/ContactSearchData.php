<?php

namespace App\Data;

use Symfony\Component\Serializer\Annotation\Groups;

class ContactSearchData
{
    #[Groups(['search'])]
    public ?string $firstName = '';

    #[Groups(['search'])]
    public ?string $lastName = '';

    #[Groups(['search'])]
    public ?string $email = '';

    public int $page = 1;

    #[Groups(['search'])]
    public ?string $order = 'ASC';

    #[Groups(['search'])]
    public ?string $sort = '';

    /**
     * Filter by lead origin from associated customer.
     */
    #[Groups(['search'])]
    public ?string $leadOrigin = '';

    /**
     * Filter by customer's contract expiration date (contracts expiring after this date).
     */
    #[Groups(['search'])]
    public ?\DateTime $contractEndAfter = null;

    /**
     * Filter by customer's contract expiration date (contacts whose customer's last contract expires before this date).
     */
    #[Groups(['search'])]
    public ?\DateTime $contractEndBefore = null;
}
