<?php

namespace App\Service\Template;

use App\Entity\Customer;
use App\Entity\User;
use PhpOffice\PhpWord\TemplateProcessor as PhpWordProcessor;
use Psr\Log\LoggerInterface;

class WordTemplateProcessor
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly VariableResolver $variableResolver,
    ) {
    }

    /**
     * Traite un template Word en remplaçant les variables.
     */
    public function process(string $templatePath, Customer $customer, ?User $currentUser = null): string
    {
        // Crée le processeur de template
        $this->logger->debug('Création du processeur de template Word', ['template_path' => $templatePath]);
        $processor = new PhpWordProcessor($templatePath);
        $this->logger->debug('Processeur de template créé avec succès');

        // Récupère toutes les variables du template
        $this->logger->debug('Extraction des variables du template');
        $variables = $this->extractVariables($processor);
        $this->logger->debug('Variables extraites', ['count' => count($variables), 'variables' => $variables]);

        // Remplace chaque variable
        foreach ($variables as $variable) {
            $formattedValue = '';  // Valeur par défaut vide

            try {
                $this->logger->debug('Résolution de la variable', ['variable' => $variable]);

                $value = $this->variableResolver->resolve($variable, $customer, $currentUser);
                $formattedValue = $this->variableResolver->format($value);

                $this->logger->debug('Traitement variable', [
                    'variable' => $variable,
                    'raw_value' => $value,
                    'formatted_value' => $formattedValue,
                ]);
            } catch (\Exception $e) {
                $this->logger->debug('Variable non trouvée, remplacée par vide', [
                    'variable' => $variable,
                    'error' => $e->getMessage(),
                ]);
                // La variable reste vide
            }

            // Remplacer la variable (soit par la valeur trouvée, soit par vide)
            $processor->setValue($variable, $formattedValue);
        }

        // Génère le fichier de sortie
        $tempDir = sys_get_temp_dir();
        $this->logger->debug('Répertoire temporaire', ['dir' => $tempDir]);

        // Vérifier que le répertoire temporaire est accessible en écriture
        if (!is_writable($tempDir)) {
            $this->logger->critical('Répertoire temporaire non accessible en écriture', [
                'directory' => $tempDir,
                'permissions' => substr(sprintf('%o', fileperms($tempDir)), -4),
            ]);
            throw new \RuntimeException("Temporary directory not writable: $tempDir");
        }

        $tempFile = tempnam($tempDir, 'doc_').'.docx';
        $this->logger->debug('Sauvegarde du fichier temporaire', ['temp_file' => $tempFile]);

        $processor->saveAs($tempFile);

        if (!file_exists($tempFile)) {
            $this->logger->critical('Fichier temporaire non créé', [
                'path' => $tempFile,
                'error' => error_get_last(),
            ]);
            throw new \RuntimeException("Failed to create temporary file: $tempFile");
        }

        $this->logger->info('Fichier temporaire créé avec succès', [
            'temp_file' => $tempFile,
            'file_size' => filesize($tempFile),
        ]);

        return $tempFile;
    }

    /**
     * Extrait les variables du template (patterns comme ${variable}).
     *
     * @return array<int, string>
     */
    private function extractVariables(PhpWordProcessor $processor): array
    {
        $reflection = new \ReflectionObject($processor);
        $property = $reflection->getProperty('tempDocumentMainPart');
        $property->setAccessible(true);
        $content = $property->getValue($processor);

        preg_match_all('/\${([^}]+)}/', $content, $matches);

        return array_unique($matches[1]);
    }
}
