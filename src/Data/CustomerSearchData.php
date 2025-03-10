<?php

namespace App\Data;

use App\Entity\ProspectStatus;

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
}