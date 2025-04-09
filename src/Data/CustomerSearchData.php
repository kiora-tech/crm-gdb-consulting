<?php

namespace App\Data;

use App\Entity\ProspectStatus;
use App\Entity\User;

class CustomerSearchData
{
    public ?string $name = '';

    public int $page = 1;

    public ?string $order = 'ASC';

    public ?string $sort = '';

    public ?ProspectStatus $status = null;

    public ?string $contactName = '';

    public int $expirationPeriod  = 3; // en mois

    public bool $expiringContracts = false;

    public ?User $user = null;
}