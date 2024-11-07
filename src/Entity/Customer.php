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
}
