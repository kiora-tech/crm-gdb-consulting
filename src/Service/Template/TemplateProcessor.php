<?php

namespace App\Service\Template;

use App\Entity\Customer;
use App\Entity\Template;
use Doctrine\Common\Collections\Collection;
use PhpOffice\PhpWord\TemplateProcessor as TemplateProcessorVendor;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class TemplateProcessor
{
    private PropertyAccessor $propertyAccessor;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
        private readonly LoggerInterface $logger,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function processTemplate(Template $template, Customer $customer): string
    {
        // Charge le template avec le chemin complet
        $templatePath = $this->publicDir . '/' . ltrim($template->getPath(), '/');
        $templateProcessor = new TemplateProcessorVendor($templatePath);

        // Récupère toutes les variables du template
        $variables = $this->extractVariables($templateProcessor);
dump($variables);
        // Remplace chaque variable
        foreach ($variables as $variable) {
            $value = $this->resolveValue($customer, $variable);
            dump($value);
            $templateProcessor->setValue($variable, $this->formatValue($value));
        }

        // Génère un nom de fichier temporaire unique
        $tempFile = tempnam(sys_get_temp_dir(), 'doc_') . '.docx';
        $templateProcessor->saveAs($tempFile);

        return $tempFile;
    }

    /**
     * Extrait toutes les variables du template (pattern: ${variable})
     */
    private function extractVariables(TemplateProcessorVendor $processor): array
    {
        $variables = [];

        // Utilise la réflexion pour accéder à la propriété privée contenant les variables
        $reflection = new \ReflectionObject($processor);
        $property = $reflection->getProperty('tempDocumentMainPart');
        $property->setAccessible(true);
        $content = $property->getValue($processor);

        // Recherche tous les patterns ${...}
        preg_match_all('/\${([^}]+)}/', $content, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Résout la valeur d'une variable en utilisant le PropertyAccessor
     */
    private function resolveValue(object $object, string $path): mixed
    {
        try {
            // Si c'est une propriété simple (sans point), on l'utilise directement
            if (!str_contains($path, '.')) {
                return $this->propertyAccessor->getValue($object, $path);
            }

            // Pour les chemins complexes, on convertit en notation tableau
            $path = str_replace('.', '][', $path);
            $path = "[$path]";

            return $this->propertyAccessor->getValue($object, $path);
        } catch (\Exception $e) {
            $this->logger->error('Error resolving template variable: ' . $e->getMessage(), [
                'path' => $path,
                'object_class' => get_class($object)
            ]);
            return '';
        }
    }

    /**
     * Formate la valeur pour l'insertion dans le template
     */
    private function formatValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if ($value instanceof Collection) {
            return (string)$value->count();
        }

        // Pour les énums qui implémentent TranslatableInterface
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }

        return (string)$value;
    }
}