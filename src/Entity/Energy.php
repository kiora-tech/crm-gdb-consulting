<?php

namespace App\Entity;

use App\Repository\EnergyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: EnergyRepository::class)]
#[UniqueEntity(['code', 'type', 'contractEnd'], message: 'Ce code est déjà utilisé pour ce type d\'énergie avec cette date de fin de contrat')]
class Energy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'energies')]
    private ?Customer $customer = null;

    #[ORM\Column(type: Types::STRING, enumType: EnergyType::class)]
    private ?EnergyType $type = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $code = null;  // PDL pour ELEC, PCE pour GAZ

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $contractEnd = null;

    // Champs spécifiques à l'électricité
    #[ORM\Column(nullable: true)]
    private ?int $powerKva = null;

    #[ORM\ManyToOne(targetEntity: Fta::class, inversedBy: 'energies')]
    private ?Fta $fta = null;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: Segment::class)]
    private ?Segment $segment = null;

    public function getSegment(): ?Segment
    {
        return $this->segment;
    }

    public function setSegment(?Segment $segment): static
    {
        $this->segment = $segment;

        return $this;
    }

    #[ORM\Column(nullable: true)]
    private ?float $peakConsumption = null;  // Conso pointe

    #[ORM\Column(nullable: true)]
    private ?float $hphConsumption = null;   // Conso HPH

    #[ORM\Column(nullable: true)]
    private ?float $hchConsumption = null;   // Conso HCH

    #[ORM\Column(nullable: true)]
    private ?float $hpeConsumption = null;   // Conso HPE

    #[ORM\Column(nullable: true)]
    private ?float $hceConsumption = null;   // Conso HCE

    #[ORM\Column(nullable: true)]
    private ?float $baseConsumption = null;  // Conso BASE

    #[ORM\Column(nullable: true)]
    private ?float $hpConsumption = null;    // Conso HP

    #[ORM\Column(nullable: true)]
    private ?float $hcConsumption = null;    // Conso HC

    // Champs spécifiques au gaz
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profile = null;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: GasTransportRate::class)]
    private ?GasTransportRate $transportRate = null;  // Tarif acheminement

    #[ORM\Column(nullable: true)]
    private ?float $totalConsumption = null;

    #[ORM\ManyToOne(inversedBy: 'energies')]
    #[ORM\JoinColumn(nullable: true)]
    private ?EnergyProvider $energyProvider = null;  // Conso TOTAL

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getType(): ?EnergyType
    {
        return $this->type;
    }

    public function setType(?EnergyType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getContractEnd(): ?\DateTimeInterface
    {
        return $this->contractEnd;
    }

    public function setContractEnd(?\DateTimeInterface $contractEnd): static
    {
        $this->contractEnd = $contractEnd;

        return $this;
    }

    public function getPowerKva(): ?int
    {
        return $this->powerKva;
    }

    public function setPowerKva(?int $powerKva): Energy
    {
        $this->powerKva = $powerKva;

        return $this;
    }

    public function getFta(): ?Fta
    {
        return $this->fta;
    }

    public function setFta(?Fta $fta): Energy
    {
        $this->fta = $fta;

        return $this;
    }

    public function getPeakConsumption(): ?float
    {
        return $this->peakConsumption;
    }

    public function setPeakConsumption(?float $peakConsumption): Energy
    {
        $this->peakConsumption = $peakConsumption;

        return $this;
    }

    public function getHphConsumption(): ?float
    {
        return $this->hphConsumption;
    }

    public function setHphConsumption(?float $hphConsumption): Energy
    {
        $this->hphConsumption = $hphConsumption;

        return $this;
    }

    public function getHchConsumption(): ?float
    {
        return $this->hchConsumption;
    }

    public function setHchConsumption(?float $hchConsumption): Energy
    {
        $this->hchConsumption = $hchConsumption;

        return $this;
    }

    public function getHpeConsumption(): ?float
    {
        return $this->hpeConsumption;
    }

    public function setHpeConsumption(?float $hpeConsumption): Energy
    {
        $this->hpeConsumption = $hpeConsumption;

        return $this;
    }

    public function getHceConsumption(): ?float
    {
        return $this->hceConsumption;
    }

    public function setHceConsumption(?float $hceConsumption): Energy
    {
        $this->hceConsumption = $hceConsumption;

        return $this;
    }

    public function getBaseConsumption(): ?float
    {
        return $this->baseConsumption;
    }

    public function setBaseConsumption(?float $baseConsumption): Energy
    {
        $this->baseConsumption = $baseConsumption;

        return $this;
    }

    public function getHpConsumption(): ?float
    {
        return $this->hpConsumption;
    }

    public function setHpConsumption(?float $hpConsumption): Energy
    {
        $this->hpConsumption = $hpConsumption;

        return $this;
    }

    public function getHcConsumption(): ?float
    {
        return $this->hcConsumption;
    }

    public function setHcConsumption(?float $hcConsumption): Energy
    {
        $this->hcConsumption = $hcConsumption;

        return $this;
    }

    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function setProfile(?string $profile): Energy
    {
        $this->profile = $profile;

        return $this;
    }

    public function getTransportRate(): ?GasTransportRate
    {
        return $this->transportRate;
    }

    public function setTransportRate(?GasTransportRate $transportRate): Energy
    {
        $this->transportRate = $transportRate;

        return $this;
    }

    public function getTotalConsumption(): ?float
    {
        return $this->totalConsumption;
    }

    public function setTotalConsumption(?float $totalConsumption): Energy
    {
        $this->totalConsumption = $totalConsumption;

        return $this;
    }

    public function getEnergyProvider(): ?EnergyProvider
    {
        return $this->energyProvider;
    }

    public function setEnergyProvider(?EnergyProvider $energyProvider): static
    {
        $this->energyProvider = $energyProvider;

        return $this;
    }
}
