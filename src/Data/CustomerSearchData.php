<?php

namespace App\Data;

use App\Entity\EnergyProvider;
use App\Entity\ProspectOrigin;
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

    public ?User $user = null;

    public bool $unassigned = false;

    public ?string $leadOrigin = '';

    public ?ProspectOrigin $origin = null;

    public ?EnergyProvider $energyProvider = null;

    public ?string $code = '';

    public ?\DateTime $contractEndAfter = null;
    public ?\DateTime $contractEndBefore = null;
}
