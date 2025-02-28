<?php

namespace App\Service;

use App\Message\StartImportMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class ImportService
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/var/import')]
        private string $importDirectory
    ) {
    }

    /**
     * Initie l'importation d'un fichier Excel
     */
    public function importFromUpload(UploadedFile $file): string
    {
        // Valider que c'est bien un fichier Excel
        $this->validateExcelFile($file);

        // Sauvegarder le fichier localement avec un nom unique
        $filePath = $this->saveUploadedFile($file);

        // Démarrer le processus d'importation asynchrone
        $this->startImportProcess($filePath, $file->getClientOriginalName());

        return $filePath;
    }

    /**
     * Démarre l'importation à partir d'un chemin de fichier existant
     */
    public function importFromExcel(string $filePath, string $originalFilename = ''): void
    {
        $this->startImportProcess($filePath, $originalFilename ?: basename($filePath));
    }

    /**
     * Valide que le fichier est bien un Excel
     */
    private function validateExcelFile(UploadedFile $file): void
    {
        $allowedMimeTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.spreadsheet',
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \InvalidArgumentException(
                'Le fichier doit être au format Excel (.xls, .xlsx ou .ods)'
            );
        }
    }

    /**
     * Sauvegarde le fichier téléchargé dans un répertoire temporaire
     */
    private function saveUploadedFile(UploadedFile $file): string
    {
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($this->importDirectory)) {
            mkdir($this->importDirectory, 0777, true);
        }

        // Générer un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Déplacer le fichier
        $filePath = $this->importDirectory . '/' . $newFilename;
        $file->move($this->importDirectory, $newFilename);

        $this->logger->info('Fichier importé sauvegardé', [
            'original_name' => $file->getClientOriginalName(),
            'path' => $filePath
        ]);

        return $filePath;
    }

    /**
     * Démarre le processus d'importation via le message bus
     */
    private function startImportProcess(string $filePath, string $originalFilename): void
    {
        $message = new StartImportMessage($filePath, $originalFilename);
        $this->messageBus->dispatch($message);

        $this->logger->info('Processus d\'importation démarré', [
            'file' => $originalFilename,
            'path' => $filePath
        ]);
    }
}
