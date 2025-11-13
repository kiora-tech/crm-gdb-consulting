<?php

declare(strict_types=1);

namespace App\Domain\Import\Service;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service for sending email notifications about import operations.
 *
 * Sends templated emails to users at key stages of the import process:
 * - Analysis complete (awaiting confirmation)
 * - Processing complete (successful import)
 * - Failure (import error)
 * - Cancellation (import cancelled by user)
 */
readonly class ImportNotifier
{
    /**
     * Default sender email address and name.
     */
    private const string SENDER_EMAIL = 'noreply@crm-gdb.com';
    private const string SENDER_NAME = 'CRM GDB Consulting';

    /**
     * @param MailerInterface       $mailer       Symfony mailer service
     * @param UrlGeneratorInterface $urlGenerator URL generator for creating absolute URLs
     * @param LoggerInterface       $logger       Logger for tracking email sends
     */
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Notify user that import analysis is complete and awaiting confirmation.
     *
     * @param object $import The Import entity (must have getId(), getUser(), getOriginalFilename(), getTotalRows())
     *
     * @throws TransportExceptionInterface If email cannot be sent
     */
    public function notifyAnalysisComplete(object $import): void
    {
        $this->validateImportEntity($import, ['getId', 'getUser', 'getOriginalFilename', 'getTotalRows']);

        $user = $import->getUser();
        if (!method_exists($user, 'getEmail')) {
            throw new \InvalidArgumentException('L\'entité User doit avoir une méthode getEmail()');
        }

        $detailUrl = $this->generateImportDetailUrl($import->getId());

        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to($user->getEmail())
            ->subject('Analyse d\'importation terminée - Confirmation requise')
            ->htmlTemplate('emails/import/analysis_complete.html.twig')
            ->context([
                'import' => $import,
                'detailUrl' => $detailUrl,
                'fileName' => $import->getOriginalFilename(),
                'totalRows' => $import->getTotalRows(),
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email d\'analyse terminée envoyé', [
                'import_id' => $import->getId(),
                'recipient' => $user->getEmail(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec de l\'envoi d\'email d\'analyse terminée', [
                'import_id' => $import->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Notify user that import processing is complete and successful.
     *
     * @param object $import The Import entity
     *
     * @throws TransportExceptionInterface If email cannot be sent
     */
    public function notifyProcessingComplete(object $import): void
    {
        $this->validateImportEntity($import, ['getId', 'getUser', 'getOriginalFilename', 'getProcessedRows', 'getSuccessRows']);

        $user = $import->getUser();
        if (!method_exists($user, 'getEmail')) {
            throw new \InvalidArgumentException('L\'entité User doit avoir une méthode getEmail()');
        }

        $detailUrl = $this->generateImportDetailUrl($import->getId());

        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to($user->getEmail())
            ->subject('Importation terminée avec succès')
            ->htmlTemplate('emails/import/processing_complete.html.twig')
            ->context([
                'import' => $import,
                'detailUrl' => $detailUrl,
                'fileName' => $import->getOriginalFilename(),
                'processedRows' => $import->getProcessedRows(),
                'successCount' => $import->getSuccessRows(),
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email de traitement terminé envoyé', [
                'import_id' => $import->getId(),
                'recipient' => $user->getEmail(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec de l\'envoi d\'email de traitement terminé', [
                'import_id' => $import->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Notify user that import has failed.
     *
     * @param object $import The Import entity
     *
     * @throws TransportExceptionInterface If email cannot be sent
     */
    public function notifyFailure(object $import): void
    {
        $this->validateImportEntity($import, ['getId', 'getUser', 'getOriginalFilename']);

        $user = $import->getUser();
        if (!method_exists($user, 'getEmail')) {
            throw new \InvalidArgumentException('L\'entité User doit avoir une méthode getEmail()');
        }

        $detailUrl = $this->generateImportDetailUrl($import->getId());
        $errorMessage = method_exists($import, 'getErrorMessage') ? $import->getErrorMessage() : null;

        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to($user->getEmail())
            ->subject('Échec de l\'importation')
            ->htmlTemplate('emails/import/failure.html.twig')
            ->context([
                'import' => $import,
                'detailUrl' => $detailUrl,
                'fileName' => $import->getOriginalFilename(),
                'errorMessage' => $errorMessage,
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email d\'échec d\'importation envoyé', [
                'import_id' => $import->getId(),
                'recipient' => $user->getEmail(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec de l\'envoi d\'email d\'échec d\'importation', [
                'import_id' => $import->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Notify user that import has been cancelled.
     *
     * @param object $import The Import entity
     *
     * @throws TransportExceptionInterface If email cannot be sent
     */
    public function notifyCancellation(object $import): void
    {
        $this->validateImportEntity($import, ['getId', 'getUser', 'getOriginalFilename']);

        $user = $import->getUser();
        if (!method_exists($user, 'getEmail')) {
            throw new \InvalidArgumentException('L\'entité User doit avoir une méthode getEmail()');
        }

        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to($user->getEmail())
            ->subject('Importation annulée')
            ->htmlTemplate('emails/import/cancellation.html.twig')
            ->context([
                'import' => $import,
                'fileName' => $import->getOriginalFilename(),
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email d\'annulation d\'importation envoyé', [
                'import_id' => $import->getId(),
                'recipient' => $user->getEmail(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec de l\'envoi d\'email d\'annulation d\'importation', [
                'import_id' => $import->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate absolute URL to the import detail page.
     *
     * @param int $importId The import ID
     *
     * @return string Absolute URL to the import detail page
     */
    private function generateImportDetailUrl(int $importId): string
    {
        return $this->urlGenerator->generate(
            'app_import_show',
            ['id' => $importId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * Validate that the import entity has required methods.
     *
     * @param object        $import          The Import entity
     * @param array<string> $requiredMethods Methods that must exist
     *
     * @throws \InvalidArgumentException If required methods are missing
     */
    private function validateImportEntity(object $import, array $requiredMethods): void
    {
        foreach ($requiredMethods as $method) {
            if (!method_exists($import, $method)) {
                throw new \InvalidArgumentException(sprintf('L\'entité Import doit avoir une méthode %s()', $method));
            }
        }
    }
}
