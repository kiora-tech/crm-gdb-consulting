<?php

namespace App\Data;

use App\Entity\EnergyProvider;
use App\Entity\ProspectOrigin;
use App\Entity\ProspectStatus;
use App\Entity\User;
use Symfony\Component\Serializer\Attribute\Groups;

class CustomerSearchData
{
    #[Groups(['search'])]
    public ?string $name = '';

    public int $page = 1;

    #[Groups(['search'])]
    public ?string $order = 'ASC';

    #[Groups(['search'])]
    public ?string $sort = '';

    public ?ProspectStatus $status = null;

    #[Groups(['search'])]
    public ?string $contactName = '';

    public ?int $userId = null;

    #[Groups(['search'])]
    public bool $unassigned = false;

    #[Groups(['search'])]
    public ?string $leadOrigin = '';

    public ?string $originValue = null;

    public ?int $energyProviderId = null;

    #[Groups(['search'])]
    public ?string $code = '';

    #[Groups(['search'])]
    public ?\DateTime $contractEndAfter = null;

    #[Groups(['search'])]
    public ?\DateTime $contractEndBefore = null;

    // Propriétés pour la relation avec les entités (non sérialisées)
    public ?User $user = null;
    public ?ProspectOrigin $origin = null;
    public ?EnergyProvider $energyProvider = null;

    public function getUserId(): ?int
    {
        return $this->user?->getId() ?? $this->userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function getOriginValue(): ?string
    {
        if ($this->origin) {
            return $this->origin->value;
        }

        return $this->originValue;
    }

    public function setOriginValue(?string $originValue): void
    {
        $this->originValue = $originValue;
    }

    public function getEnergyProviderId(): ?int
    {
        return $this->energyProvider?->getId() ?? $this->energyProviderId;
    }

    public function setEnergyProviderId(?int $energyProviderId): void
    {
        $this->energyProviderId = $energyProviderId;
    }

    public function getStatusValue(): ?string
    {
        return $this->status?->value;
    }

    public function setStatusValue(?string $statusValue): void
    {
        if ($statusValue) {
            $this->status = ProspectStatus::from($statusValue);
        }
    }
}
