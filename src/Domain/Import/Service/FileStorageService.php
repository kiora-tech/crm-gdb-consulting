<?php

declare(strict_types=1);

namespace App\Domain\Import\Service;

use App\Domain\Import\ValueObject\ImportFileInfo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service responsible for managing file storage for imports.
 *
 * Handles uploading, storing, and deleting import files in a dedicated directory.
 * Files are stored with unique names to prevent collisions.
 */
readonly class FileStorageService
{
    /**
     * @param SluggerInterface $slugger         Slugger for sanitizing filenames
     * @param string           $importDirectory Directory path where import files are stored
     */
    public function __construct(
        private SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/var/import')]
        private string $importDirectory,
    ) {
    }

    /**
     * Store an uploaded file and return its information.
     *
     * @param UploadedFile $file The uploaded file to store
     *
     * @return ImportFileInfo Information about the stored file
     *
     * @throws FileException If the file cannot be moved to the target directory
     */
    public function storeUploadedFile(UploadedFile $file): ImportFileInfo
    {
        $this->ensureDirectoryExists();

        $originalFilename = $file->getClientOriginalName();

        // Generate unique filename components
        $originalFilenamePath = pathinfo($originalFilename, PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilenamePath);
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension() ?? 'bin';
        $storedFilename = sprintf('%s-%s.%s', $safeFilename, uniqid(), $extension);

        // Get file info BEFORE moving (while temp file still exists)
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        try {
            // Move the uploaded file using Symfony's move() method
            // This uses move_uploaded_file() internally which is the only safe way
            $movedFile = $file->move(
                $this->importDirectory,
                $storedFilename
            );

            $storedFilePath = $movedFile->getPathname();
        } catch (\Exception $e) {
            throw new FileException(sprintf('Impossible de déplacer le fichier vers "%s/%s": %s', $this->importDirectory, $storedFilename, $e->getMessage()), 0, $e);
        }

        return new ImportFileInfo(
            originalName: $originalFilename,
            storedPath: $storedFilePath,
            storedFilename: $storedFilename,
            fileSize: $fileSize ?: 0,
            mimeType: $mimeType,
        );
    }

    /**
     * Get the absolute file path for an import.
     *
     * @param object $import The Import entity (must have getStoredFilename() method)
     *
     * @return string The absolute path to the import file
     */
    public function getImportFilePath(object $import): string
    {
        if (!method_exists($import, 'getStoredFilename')) {
            throw new \InvalidArgumentException('L\'entité Import doit avoir une méthode getStoredFilename()');
        }

        $filename = $import->getStoredFilename();
        if (empty($filename)) {
            throw new \InvalidArgumentException('Le nom de fichier stocké est vide');
        }

        return $this->importDirectory.'/'.$filename;
    }

    /**
     * Delete the file associated with an import.
     *
     * @param object $import The Import entity
     *
     * @throws \RuntimeException If the file cannot be deleted
     */
    public function deleteImportFile(object $import): void
    {
        $filePath = $this->getImportFilePath($import);

        if (!file_exists($filePath)) {
            return; // File already deleted or never existed
        }

        if (!is_writable($filePath)) {
            throw new \RuntimeException(sprintf('Le fichier "%s" n\'est pas accessible en écriture', $filePath));
        }

        if (!unlink($filePath)) {
            throw new \RuntimeException(sprintf('Impossible de supprimer le fichier "%s"', $filePath));
        }
    }

    /**
     * Ensure the import directory exists with proper permissions.
     *
     * @throws \RuntimeException If the directory cannot be created
     */
    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->importDirectory)) {
            return;
        }

        if (!mkdir($this->importDirectory, 0755, true)) {
            throw new \RuntimeException(sprintf('Impossible de créer le répertoire "%s"', $this->importDirectory));
        }
    }
}
