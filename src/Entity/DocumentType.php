<?php

namespace App\Entity;

use App\Repository\DocumentTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentTypeRepository::class)]
class DocumentType
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

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'type')]
    private Collection $documents;

    /**
     * @var Collection<int, Template>
     */
    #[ORM\OneToMany(targetEntity: Template::class, mappedBy: 'documentType', orphanRemoval: true)]
    private Collection $templates;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->templates = new ArrayCollection();
    }

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
            $document->setType($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getType() === $this) {
                $document->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Template>
     */
    public function getTemplates(): Collection
    {
        return $this->templates;
    }

    public function addTemplate(Template $template): static
    {
        if (!$this->templates->contains($template)) {
            $this->templates->add($template);
        }

        return $this;
    }

    public function removeTemplate(Template $template): static
    {
        if ($this->templates->removeElement($template)) {
            // Check if this DocumentType is associated with the template
            // Note: Template has a documentType (DocumentType) and type (TemplateType) property
            if ($template->getDocumentType() === $this) {
                $template->setDocumentType(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
