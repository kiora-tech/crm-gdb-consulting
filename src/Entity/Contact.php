<?php

namespace App\Entity;

use App\Entity\Trait\SyncableEntity;
use App\Repository\ContactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Contact
{
    use SyncableEntity;
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

    #[ORM\ManyToOne(inversedBy: 'contacts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $position = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mobilePhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $addressNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $addressStreet = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $addressPostalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $addressCity = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isPrimary = false;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getMobilePhone(): ?string
    {
        return $this->mobilePhone;
    }

    public function setMobilePhone(?string $mobilePhone): static
    {
        $this->mobilePhone = $mobilePhone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getAddressNumber(): ?string
    {
        return $this->addressNumber;
    }

    public function setAddressNumber(?string $addressNumber): static
    {
        $this->addressNumber = $addressNumber;

        return $this;
    }

    public function getAddressStreet(): ?string
    {
        return $this->addressStreet;
    }

    public function setAddressStreet(?string $addressStreet): static
    {
        $this->addressStreet = $addressStreet;

        return $this;
    }

    public function getAddressPostalCode(): ?string
    {
        return $this->addressPostalCode;
    }

    public function setAddressPostalCode(?string $addressPostalCode): static
    {
        $this->addressPostalCode = $addressPostalCode;

        return $this;
    }

    public function getAddressCity(): ?string
    {
        return $this->addressCity;
    }

    public function setAddressCity(?string $addressCity): static
    {
        $this->addressCity = $addressCity;

        return $this;
    }

    public function getAddressFull(): ?string
    {
        // Si on a les nouveaux champs, les utiliser
        if ($this->addressStreet || $this->addressPostalCode || $this->addressCity) {
            $parts = [];
            if ($this->addressNumber || $this->addressStreet) {
                $streetParts = [];
                if ($this->addressNumber) {
                    $streetParts[] = $this->addressNumber;
                }
                if ($this->addressStreet) {
                    $streetParts[] = $this->addressStreet;
                }
                $parts[] = implode(' ', $streetParts);
            }
            if ($this->addressPostalCode || $this->addressCity) {
                $cityParts = [];
                if ($this->addressPostalCode) {
                    $cityParts[] = $this->addressPostalCode;
                }
                if ($this->addressCity) {
                    $cityParts[] = $this->addressCity;
                }
                $parts[] = implode(' ', $cityParts);
            }

            return implode(', ', $parts);
        }

        // Sinon, utiliser l'ancien champ
        return $this->address;
    }

    public function getAddressMultiline(): ?string
    {
        // Si on a les nouveaux champs, les utiliser
        if ($this->addressStreet || $this->addressPostalCode || $this->addressCity) {
            $lines = [];
            if ($this->addressNumber || $this->addressStreet) {
                $streetParts = [];
                if ($this->addressNumber) {
                    $streetParts[] = $this->addressNumber;
                }
                if ($this->addressStreet) {
                    $streetParts[] = $this->addressStreet;
                }
                $lines[] = implode(' ', $streetParts);
            }
            if ($this->addressPostalCode || $this->addressCity) {
                $cityParts = [];
                if ($this->addressPostalCode) {
                    $cityParts[] = $this->addressPostalCode;
                }
                if ($this->addressCity) {
                    $cityParts[] = $this->addressCity;
                }
                $lines[] = implode(' ', $cityParts);
            }

            return implode("\n", $lines);
        }

        // Sinon, utiliser l'ancien champ
        if (!$this->address) {
            return null;
        }

        return str_replace(', ', "\n", $this->address);
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s %s', $this->firstName, $this->lastName);
    }
}
