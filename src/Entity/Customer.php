<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    private ?string $leadOrigin = null;

    /**
     * @var Collection<int, BusinessEntity>
     */
    #[ORM\OneToMany(targetEntity: BusinessEntity::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    private Collection $businessEntities;

    /**
     * @var Collection<int, Energy>
     */
    #[ORM\OneToMany(targetEntity: Energy::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    private Collection $energies;

    /**
     * @var Collection<int, Contact>
     */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    private Collection $contacts;

    /**
     * @var Collection<int, Prospect>
     */
    #[ORM\OneToMany(targetEntity: Prospect::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    private Collection $prospects;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $action = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contract = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $worth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commision = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $margin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyGroup = null;

    public function __construct()
    {
        $this->businessEntities = new ArrayCollection();
        $this->energies = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->prospects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLeadOrigin(): ?string
    {
        return $this->leadOrigin;
    }

    public function setLeadOrigin(string $leadOrigin): static
    {
        $this->leadOrigin = $leadOrigin;

        return $this;
    }

    /**
     * @return Collection<int, BusinessEntity>
     */
    public function getBusinessEntities(): Collection
    {
        return $this->businessEntities;
    }

    public function addBusinessEntity(BusinessEntity $businessEntity): static
    {
        if (!$this->businessEntities->contains($businessEntity)) {
            $this->businessEntities->add($businessEntity);
            $businessEntity->setCustomer($this);
        }

        return $this;
    }

    public function removeBusinessEntity(BusinessEntity $businessEntity): static
    {
        if ($this->businessEntities->removeElement($businessEntity)) {
            // set the owning side to null (unless already changed)
            if ($businessEntity->getCustomer() === $this) {
                $businessEntity->setCustomer(null);
            }
        }

        return $this;
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
            $this->energies->add($energy);
            $energy->setCustomer($this);
        }

        return $this;
    }

    public function removeEnergy(Energy $energy): static
    {
        if ($this->energies->removeElement($energy)) {
            // set the owning side to null (unless already changed)
            if ($energy->getCustomer() === $this) {
                $energy->setCustomer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setCustomer($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            // set the owning side to null (unless already changed)
            if ($contact->getCustomer() === $this) {
                $contact->setCustomer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Prospect>
     */
    public function getProspects(): Collection
    {
        return $this->prospects;
    }

    public function addProspect(Prospect $prospect): static
    {
        if (!$this->prospects->contains($prospect)) {
            $this->prospects->add($prospect);
            $prospect->setCustomer($this);
        }

        return $this;
    }

    public function removeProspect(Prospect $prospect): static
    {
        if ($this->prospects->removeElement($prospect)) {
            // set the owning side to null (unless already changed)
            if ($prospect->getCustomer() === $this) {
                $prospect->setCustomer(null);
            }
        }

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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getContract(): ?string
    {
        return $this->contract;
    }

    public function setContract(?string $contract): static
    {
        $this->contract = $contract;

        return $this;
    }

    public function getWorth(): ?string
    {
        return $this->worth;
    }

    public function setWorth(?string $worth): static
    {
        $this->worth = $worth;

        return $this;
    }

    public function getCommision(): ?string
    {
        return $this->commision;
    }

    public function setCommision(?string $commision): static
    {
        $this->commision = $commision;

        return $this;
    }

    public function getMargin(): ?string
    {
        return $this->margin;
    }

    public function setMargin(?string $margin): static
    {
        $this->margin = $margin;

        return $this;
    }

    public function getCompanyGroup(): ?string
    {
        return $this->companyGroup;
    }

    public function setCompanyGroup(?string $companyGroup): static
    {
        $this->companyGroup = $companyGroup;

        return $this;
    }
}
