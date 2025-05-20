<?php

namespace App\Service\Template;

use App\Entity\Customer;
use App\Entity\Template;
use Doctrine\Common\Collections\Collection;
use League\Flysystem\FilesystemOperator;
use PhpOffice\PhpWord\TemplateProcessor as PhpWordProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class TemplateProcessor
{
    private PropertyAccessor $propertyAccessor;

    public function __construct(
        #[Autowire(service: 'templates.storage')]
        private readonly FilesystemOperator $templatesStorage,
        private readonly LoggerInterface $logger,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function processTemplate(Template $template, Customer $customer): string
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

        // Créer un fichier temporaire pour y copier le contenu du template
        $tempDir = sys_get_temp_dir();
        $tempTemplatePath = tempnam($tempDir, 'template_');

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
            // Crée le processeur de template
            $this->logger->debug('Création du processeur de template', ['temp_path' => $tempTemplatePath]);
            $processor = new PhpWordProcessor($tempTemplatePath);
            $this->logger->debug('Processeur de template créé avec succès');

            // Récupère toutes les variables du template
            $this->logger->debug('Extraction des variables du template');
            $variables = $this->extractVariables($processor);
            $this->logger->debug('Variables extraites', ['count' => count($variables), 'variables' => $variables]);

            // Remplace chaque variable
            foreach ($variables as $variable) {
                try {
                    $this->logger->debug('Résolution de la variable', ['variable' => $variable]);
                    $value = $this->resolveValue($customer, $variable);
                    $formattedValue = $this->formatValue($value);

                    $this->logger->debug('Traitement variable', [
                        'variable' => $variable,
                        'raw_value' => $value,
                        'formatted_value' => $formattedValue,
                    ]);

                    $processor->setValue($variable, $formattedValue);
                } catch (\Exception $e) {
                    $this->logger->warning('Échec du traitement de la variable', [
                        'variable' => $variable,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
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

            // Nettoyer le fichier temporaire du template
            if (file_exists($tempTemplatePath)) {
                unlink($tempTemplatePath);
            }

            $this->logger->info('Fichier temporaire créé avec succès', [
                'temp_file' => $tempFile,
                'file_size' => filesize($tempFile),
            ]);

            return $tempFile;
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
        }
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

    private function resolveValue(object $object, string $path): mixed
    {
        try {
            $trimmedPath = trim($path);
            $this->logger->debug('Tentative de résolution de valeur', [
                'path' => $trimmedPath,
                'object_class' => get_class($object),
            ]);

            // Vérifier si le chemin est accessible
            if (!$this->propertyAccessor->isReadable($object, $trimmedPath)) {
                $this->logger->warning('Propriété non accessible', [
                    'path' => $trimmedPath,
                    'object_class' => get_class($object),
                    'available_properties' => $this->getObjectProperties($object),
                ]);

                return '';
            }

            $value = $this->propertyAccessor->getValue($object, $trimmedPath);
            $this->logger->debug('Valeur résolue avec succès', [
                'path' => $trimmedPath,
                'value_type' => is_object($value) ? get_class($value) : gettype($value),
            ]);

            return $value;
        } catch (\Exception $e) {
            $this->logger->error('Échec de résolution de valeur', [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'object_properties' => $this->getObjectProperties($object),
            ]);

            return '';
        }
    }

    /**
     * Récupère les propriétés disponibles d'un objet pour le débogage.
     *
     * @return array<int, string>
     */
    private function getObjectProperties(object $object): array
    {
        $properties = [];

        try {
            $reflection = new \ReflectionObject($object);
            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                $properties[] = $property->getName();
            }

            // Ajouter les méthodes getters
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $methodName = $method->getName();
                if (0 === strpos($methodName, 'get') && 0 === $method->getNumberOfRequiredParameters()) {
                    $properties[] = lcfirst(substr($methodName, 3));
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Échec de récupération des propriétés de l\'objet', [
                'error' => $e->getMessage(),
            ]);
        }

        return $properties;
    }

    /**
     * Formate une valeur en chaîne de caractères pour l'insertion dans un template.
     */
    private function formatValue(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if ($value instanceof Collection) {
            return (string) $value->count();
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_array($value)) {
            $stringArray = array_map(function ($item) {
                return (string) $item;
            }, $value);

            return implode(', ', $stringArray);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }

        return (string) $value;
    }
}
