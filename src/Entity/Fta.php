<?php

namespace App\Entity;

use App\Repository\FtaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FtaRepository::class)]
class Fta
{
    /**
     * @var int|null ID is set by Doctrine ORM and is initially null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Setter for id - mostly used for testing or data fixtures.
     */
    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column]
    private ?float $fixedCost = null;

    #[ORM\Column]
    private ?float $powerReservationPeak = null;

    #[ORM\Column]
    private ?float $powerReservationHPH = null;

    #[ORM\Column]
    private ?float $powerReservationHCH = null;

    #[ORM\Column]
    private ?float $powerReservationHPE = null;

    #[ORM\Column]
    private ?float $powerReservationHCE = null;

    #[ORM\Column]
    private ?float $consumptionPeak = null;

    #[ORM\Column]
    private ?float $consumptionHPH = null;

    #[ORM\Column]
    private ?float $consumptionHCH = null;

    #[ORM\Column]
    private ?float $consumptionHPE = null;

    #[ORM\Column]
    private ?float $consumptionHCE = null;

    /**
     * @var Collection<int, Energy>
     */
    #[ORM\OneToMany(targetEntity: Energy::class, mappedBy: 'fta')]
    private Collection $energies;

    public function __construct()
    {
        $this->energies = new ArrayCollection();
    }

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getFixedCost(): ?float
    {
        return $this->fixedCost;
    }

    public function setFixedCost(float $fixedCost): static
    {
        $this->fixedCost = $fixedCost;

        return $this;
    }

    public function getPowerReservationPeak(): ?float
    {
        return $this->powerReservationPeak;
    }

    public function setPowerReservationPeak(float $powerReservationPeak): static
    {
        $this->powerReservationPeak = $powerReservationPeak;

        return $this;
    }

    public function getPowerReservationHPH(): ?float
    {
        return $this->powerReservationHPH;
    }

    public function setPowerReservationHPH(float $powerReservationHPH): static
    {
        $this->powerReservationHPH = $powerReservationHPH;

        return $this;
    }

    public function getPowerReservationHCH(): ?float
    {
        return $this->powerReservationHCH;
    }

    public function setPowerReservationHCH(float $powerReservationHCH): static
    {
        $this->powerReservationHCH = $powerReservationHCH;

        return $this;
    }

    public function getPowerReservationHPE(): ?float
    {
        return $this->powerReservationHPE;
    }

    public function setPowerReservationHPE(float $powerReservationHPE): static
    {
        $this->powerReservationHPE = $powerReservationHPE;

        return $this;
    }

    public function getPowerReservationHCE(): ?float
    {
        return $this->powerReservationHCE;
    }

    public function setPowerReservationHCE(float $powerReservationHCE): static
    {
        $this->powerReservationHCE = $powerReservationHCE;

        return $this;
    }

    public function getConsumptionPeak(): ?float
    {
        return $this->consumptionPeak;
    }

    public function setConsumptionPeak(float $consumptionPeak): static
    {
        $this->consumptionPeak = $consumptionPeak;

        return $this;
    }

    public function getConsumptionHPH(): ?float
    {
        return $this->consumptionHPH;
    }

    public function setConsumptionHPH(float $consumptionHPH): static
    {
        $this->consumptionHPH = $consumptionHPH;

        return $this;
    }

    public function getConsumptionHCH(): ?float
    {
        return $this->consumptionHCH;
    }

    public function setConsumptionHCH(float $consumptionHCH): static
    {
        $this->consumptionHCH = $consumptionHCH;

        return $this;
    }

    public function getConsumptionHPE(): ?float
    {
        return $this->consumptionHPE;
    }

    public function setConsumptionHPE(float $consumptionHPE): static
    {
        $this->consumptionHPE = $consumptionHPE;

        return $this;
    }

    public function getConsumptionHCE(): ?float
    {
        return $this->consumptionHCE;
    }

    public function setConsumptionHCE(float $consumptionHCE): static
    {
        $this->consumptionHCE = $consumptionHCE;

        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }

    /**
     * @return Collection<int, Energy>
     */
    public function getEnergies(): Collection
    {
        return $this->energies;
    }

    public function addEnergy(Energy $energy): static
    {
        if (!$this->energies->contains($energy)) {
            $this->energies[] = $energy;
            $energy->setFta($this);
        }

        return $this;
    }

    public function removeEnergy(Energy $energy): static
    {
        if ($this->energies->removeElement($energy)) {
            if ($energy->getFta() === $this) {
                $energy->setFta(null);
            }
        }

        return $this;
    }

    /**
     * @param Collection<int, Energy> $energies
     */
    public function setEnergies(Collection $energies): static
    {
        $this->energies = $energies;

        return $this;
    }
}
