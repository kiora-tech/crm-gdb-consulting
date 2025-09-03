<?php

namespace App\Entity\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * SyncableEntity trait adds synchronization fields to entities for offline mode support.
 *
 * This trait provides fields needed for tracking synchronization state between
 * the client and server in offline-first applications.
 */
trait SyncableEntity
{
    /**
     * Timestamp of when this entity was last synchronized with the server.
     * NULL means the entity has never been synchronized or needs to be synchronized.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;

    /**
     * Version number for optimistic locking and conflict resolution.
     * Incremented on each update to detect conflicts during synchronization.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 1])]
    private int $version = 1;

    /**
     * Unique identifier for the client that created or last modified this entity.
     * Used for conflict resolution and tracking offline changes.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $clientId = null;

    /**
     * Timestamp of when this entity was created.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $createdAt;

    /**
     * Timestamp of when this entity was last updated.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Get the last synchronization timestamp.
     */
    public function getSyncedAt(): ?\DateTimeImmutable
    {
        return $this->syncedAt;
    }

    /**
     * Set the synchronization timestamp.
     */
    public function setSyncedAt(?\DateTimeImmutable $syncedAt): self
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    /**
     * Mark entity as synchronized with current timestamp.
     */
    public function markAsSynced(): self
    {
        $this->syncedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Check if entity needs synchronization.
     */
    public function needsSync(): bool
    {
        return null === $this->syncedAt
               || (null !== $this->updatedAt && $this->updatedAt > $this->syncedAt);
    }

    /**
     * Get the version number.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Set the version number.
     */
    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Increment the version number.
     */
    public function incrementVersion(): self
    {
        ++$this->version;

        return $this;
    }

    /**
     * Get the client ID.
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * Set the client ID.
     */
    public function setClientId(?string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * Get the creation timestamp.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set the creation timestamp.
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the last update timestamp.
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Set the last update timestamp.
     */
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Update the timestamp to mark entity as modified.
     */
    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->syncedAt = null; // Mark as needing sync

        return $this;
    }

    /**
     * Initialize sync fields for new entities.
     * Should be called in entity constructor or via Doctrine lifecycle events.
     */
    public function initializeSyncFields(?string $clientId = null): self
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTimeImmutable();
        }

        if (null !== $clientId) {
            $this->clientId = $clientId;
        }

        return $this;
    }

    /**
     * Prepare entity for synchronization by updating metadata.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->incrementVersion();
        $this->syncedAt = null; // Mark as needing sync
    }

    /**
     * Set creation timestamp for new entities.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    /**
     * Get sync metadata as array for API responses.
     */
    public function getSyncMetadata(): array
    {
        return [
            'syncedAt' => $this->syncedAt?->format(\DateTimeInterface::ATOM),
            'version' => $this->version,
            'clientId' => $this->clientId,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
            'needsSync' => $this->needsSync(),
        ];
    }

    /**
     * Check if this entity conflicts with another version.
     */
    public function hasConflictWith(self $other): bool
    {
        return $this->version !== $other->getVersion()
               && $this->clientId !== $other->getClientId();
    }
}
