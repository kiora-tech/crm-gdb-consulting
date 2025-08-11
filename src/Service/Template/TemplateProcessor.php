<?php

namespace App\Service\Template;

use App\Entity\Customer;
use App\Entity\Template;
use App\Entity\User;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TemplateProcessor
{
    private readonly VariableResolver $variableResolver;
    private readonly ExcelTemplateProcessor $excelProcessor;
    private readonly WordTemplateProcessor $wordProcessor;

    public function __construct(
        #[Autowire(service: 'templates.storage')]
        private readonly FilesystemOperator $templatesStorage,
        private readonly LoggerInterface $logger,
    ) {
        $this->variableResolver = new VariableResolver($logger);
        $this->excelProcessor = new ExcelTemplateProcessor($logger, $this->variableResolver);
        $this->wordProcessor = new WordTemplateProcessor($logger, $this->variableResolver);
    }

    /**
     * Traite un template en remplaçant les variables par les valeurs du customer.
     */
    public function processTemplate(Template $template, Customer $customer, ?User $currentUser = null): string
    {
        if (!$template->getId()) {
            throw new \InvalidArgumentException('Template ID ne peut pas être null');
        }

        if (!$template->getLabel()) {
            throw new \InvalidArgumentException('Template label ne peut pas être null');
        }

        if (!$template->getPath()) {
            throw new \InvalidArgumentException('Template path ne peut pas être null');
        }

        $this->logger->info('Début du traitement du template', [
            'template_id' => $template->getId(),
            'template_label' => $template->getLabel(),
            'template_path' => $template->getPath(),
        ]);

        $templatePath = $template->getPath();
        $this->logger->debug('Chemin du template', ['path' => $templatePath]);

        // Vérification que le fichier existe
        if (!$this->templatesStorage->fileExists($templatePath)) {
            $this->logger->critical('Fichier template introuvable', [
                'path' => $templatePath,
            ]);
            throw new \RuntimeException("Template file not found: $templatePath");
        }

        // Déterminer le type de fichier par son extension
        $extension = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
        $isExcel = in_array($extension, ['xlsx', 'xls']);

        $this->logger->debug('Type de fichier détecté', [
            'extension' => $extension,
            'isExcel' => $isExcel,
        ]);

        // Créer un fichier temporaire pour y copier le contenu du template
        $tempDir = sys_get_temp_dir();
        $tempTemplatePath = tempnam($tempDir, 'template_').'.'.$extension;

        // Écrire le contenu du template dans le fichier temporaire
        $templateContent = $this->templatesStorage->read($templatePath);
        if (empty($templateContent)) {
            $this->logger->critical('Impossible de lire le contenu du template', [
                'path' => $templatePath,
            ]);
            throw new \RuntimeException("Unable to read template content: $templatePath");
        }

        file_put_contents($tempTemplatePath, $templateContent);

        try {
            // Utiliser le processor approprié selon le type de fichier
            if ($isExcel) {
                $resultFile = $this->excelProcessor->process($tempTemplatePath, $customer, $currentUser);
            } else {
                $resultFile = $this->wordProcessor->process($tempTemplatePath, $customer, $currentUser);
            }

            return $resultFile;
        } catch (\Exception $e) {
            // Récupérer les informations de manière sécurisée pour la journalisation d'erreur
            $templateId = null;

            try {
                $templateId = $template->getId();
            } catch (\Throwable $th) {
                // Ignorer l'erreur pour ne pas interrompre la journalisation principale
            }

            $this->logger->error('Erreur lors du traitement du template', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'template_id' => $templateId,
                'server_environment' => $_SERVER['APP_ENV'] ?? 'unknown',
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'tempdir_writable' => is_writable(sys_get_temp_dir()) ? 'yes' : 'no',
                'template_path' => $templatePath,
            ]);
            throw $e;
        } finally {
            // Nettoyer le fichier temporaire du template
            if (file_exists($tempTemplatePath)) {
                unlink($tempTemplatePath);
            }
        }
    }
}
