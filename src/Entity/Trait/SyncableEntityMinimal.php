<?php

namespace App\Entity\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * SyncableEntityMinimal trait adds only synchronization-specific fields to entities
 * that already have createdAt/updatedAt fields.
 */
trait SyncableEntityMinimal
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
     * This method assumes the entity has updatedAt method.
     */
    public function needsSync(): bool
    {
        if (method_exists($this, 'getUpdatedAt')) {
            $updatedAt = $this->getUpdatedAt();

            return null === $this->syncedAt
                   || (null !== $updatedAt && $updatedAt > $this->syncedAt);
        }

        return null === $this->syncedAt;
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
     * Update the timestamp to mark entity as modified.
     * This method calls touch() if it exists on the entity.
     */
    public function touch(): self
    {
        if (method_exists($this, 'setUpdatedAt')) {
            $this->setUpdatedAt(new \DateTime());
        }
        $this->syncedAt = null; // Mark as needing sync

        return $this;
    }

    /**
     * Get sync metadata as array for API responses.
     */
    public function getSyncMetadata(): array
    {
        $metadata = [
            'syncedAt' => $this->syncedAt?->format(\DateTimeInterface::ATOM),
            'version' => $this->version,
            'clientId' => $this->clientId,
            'needsSync' => $this->needsSync(),
        ];

        // Add createdAt and updatedAt if methods exist
        if (method_exists($this, 'getCreatedAt')) {
            $createdAt = $this->getCreatedAt();
            $metadata['createdAt'] = $createdAt instanceof \DateTimeInterface
                ? $createdAt->format(\DateTimeInterface::ATOM)
                : null;
        }

        if (method_exists($this, 'getUpdatedAt')) {
            $updatedAt = $this->getUpdatedAt();
            $metadata['updatedAt'] = $updatedAt instanceof \DateTimeInterface
                ? $updatedAt->format(\DateTimeInterface::ATOM)
                : null;
        }

        return $metadata;
    }

    /**
     * Check if this entity conflicts with another version.
     */
    public function hasConflictWith($other): bool
    {
        if (!method_exists($other, 'getVersion') || !method_exists($other, 'getClientId')) {
            return false;
        }

        return $this->version !== $other->getVersion()
               && $this->clientId !== $other->getClientId();
    }

    /**
     * Prepare entity for synchronization by updating metadata.
     */
    #[ORM\PreUpdate]
    public function onSyncPreUpdate(): void
    {
        $this->incrementVersion();
        $this->syncedAt = null; // Mark as needing sync
    }
}
