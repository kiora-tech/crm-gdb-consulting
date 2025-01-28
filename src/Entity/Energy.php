<?php

namespace App\Entity;

use App\Repository\EnergyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EnergyRepository::class)]
class Energy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'energies')]
    private ?customer $customer = null;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: EnergyType::class)]
    private ?EnergyType $type = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $contractEnd = null;

     #[ORM\Column(nullable: true)]
    private ?int $power = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $basePrice = null;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: Segment::class, options: ['default' => 'C1'])]
    private ?Segment $segment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $peakHour = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $offPeakHour = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $horoSeason = null;

    #[ORM\Column(nullable: true)]
    private ?int $peakHourWinter = null;

    #[ORM\Column(nullable: true)]
    private ?int $peakHourSummer = null;

    #[ORM\Column(nullable: true)]
    private ?int $offPeakHourWinter = null;

    #[ORM\Column(nullable: true)]
    private ?int $offPeakHourSummer = null;

    #[ORM\Column(nullable: true)]
    private ?int $total = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?customer
    {
        return $this->customer;
    }

    public function setCustomer(?customer $customer): static
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

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function setCode(?int $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): static
    {
        $this->provider = $provider;

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

    public function getPower(): ?string
    {
        return $this->power;
    }

    public function setPower(?string $power): static
    {
        $this->power = $power;

        return $this;
    }

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(?string $basePrice): static
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getSegment(): ?Segment
    {
        return $this->segment;
    }

    public function setSegment(?Segment $segment): static
    {
        $this->segment = $segment;

        return $this;
    }

    public function getPeakHour(): ?string
    {
        return $this->peakHour;
    }

    public function setPeakHour(?string $peakHour): static
    {
        $this->peakHour = $peakHour;

        return $this;
    }

    public function getOffPeakHour(): ?string
    {
        return $this->offPeakHour;
    }

    public function setOffPeakHour(?string $offPeakHour): static
    {
        $this->offPeakHour = $offPeakHour;

        return $this;
    }

    public function getHoroSeason(): ?string
    {
        return $this->horoSeason;
    }

    public function setHoroSeason(?string $horoSeason): static
    {
        $this->horoSeason = $horoSeason;

        return $this;
    }

    public function getPeakHourWinter(): ?string
    {
        return $this->peakHourWinter;
    }

    public function setPeakHourWinter(?string $peakHourWinter): static
    {
        $this->peakHourWinter = $peakHourWinter;

        return $this;
    }

    public function getPeakHourSummer(): ?string
    {
        return $this->peakHourSummer;
    }

    public function setPeakHourSummer(?string $peakHourSummer): static
    {
        $this->peakHourSummer = $peakHourSummer;

        return $this;
    }

    public function getOffPeakHourWinter(): ?string
    {
        return $this->offPeakHourWinter;
    }

    public function setOffPeakHourWinter(string $offPeakHourWinter): static
    {
        $this->offPeakHourWinter = $offPeakHourWinter;

        return $this;
    }

    public function getOffPeakHourSummer(): ?string
    {
        return $this->offPeakHourSummer;
    }

    public function setOffPeakHourSummer(?string $offPeakHourSummer): static
    {
        $this->offPeakHourSummer = $offPeakHourSummer;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(?string $total): static
    {
        $this->total = $total;

        return $this;
    }
}
