<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity('siret')]
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $leadOrigin = null;

    /**
     * @var Collection<int, Energy>
     */
    #[ORM\OneToMany(targetEntity: Energy::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['contractEnd' => 'DESC'])]
    private Collection $energies;

    /**
     * @var Collection<int, Contact>
     */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['firstName' => 'ASC'])]
    private Collection $contacts;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    private Collection $projects;

    #[ORM\Column(type: Types::STRING, enumType: ProspectOrigin::class)]
    #[Assert\NotBlank]
    private ProspectOrigin $origin;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: ProspectStatus::class)]
    private ?ProspectStatus $status = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $comments;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $action = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $worth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commision = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $margin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyGroup = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'customer', orphanRemoval: true)]
    private Collection $documents;

    #[ORM\ManyToOne(inversedBy: 'customers')]
    private ?User $user = null;

    #[ORM\Column(length: 14, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: CanalSignature::class)]
    private ?CanalSignature $canalSignature = null;

    public function __construct()
    {
        $this->origin = ProspectOrigin::RENOUVELLEMENT;
        $this->energies = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->projects = new ArrayCollection();
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

    public function setLeadOrigin(?string $leadOrigin): static
    {
        $this->leadOrigin = $leadOrigin;

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

    public function getLastEnergy(): Energy|false
    {
        return $this->energies->last();
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
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {   
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setCustomer($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            // Set the owning side to null (unless already changed)
            if ($project->getCustomer() === $this) {
                $project->setCustomer(null);
            }
        }

        return $this;
    }

    public function getOrigin(): ?ProspectOrigin
    {
        return $this->origin;
    }

    public function setOrigin(ProspectOrigin $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getStatus(): ?ProspectStatus
    {
        return $this->status;
    }

    public function setStatus(?ProspectStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setCustomer($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getCustomer() === $this) {
                $comment->setCustomer(null);
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

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setCustomer($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getCustomer() === $this) {
                $document->setCustomer(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return (string) $this->name;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): Customer
    {
        $this->siret = $siret;
        return $this;
    }

    public function getCanalSignature(): ?CanalSignature
    {
        return $this->canalSignature;
    }

    public function setCanalSignature(?CanalSignature $canalSignature): Customer
    {
        $this->canalSignature = $canalSignature;
        return $this;
    }
}
