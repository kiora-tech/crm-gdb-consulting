<?php

namespace App\Data;

use App\Entity\ProspectStatus;

class CustomerSearchData
{
    public ?string $name = '';

    public int $page = 1;

    public ?string $order = null;

    public string $sort = 'name';

    public ?ProspectStatus $status = NULL;

    public ?string $contactName = '';
}