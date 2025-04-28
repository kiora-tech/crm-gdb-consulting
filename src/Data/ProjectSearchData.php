<?php

namespace App\Data;

class ProjectSearchData
{
    public ?string $name = null;
    public ?string $status = null;
    public ?\DateTimeInterface $startDate = null;
    public ?\DateTimeInterface $deadline = null;
    public ?float $budget = null;
}
