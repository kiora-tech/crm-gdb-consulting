<?php

namespace App\Service\Template;

use App\Entity\Customer;
use App\Entity\Template;
use Doctrine\Common\Collections\Collection;
use PhpOffice\PhpWord\TemplateProcessor as PhpWordProcessor;
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
        $templatePath = $this->publicDir . '/' . ltrim($template->getPath(), '/');

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: $templatePath");
        }

        // Crée le processeur de template
        $processor = new PhpWordProcessor($templatePath);

        // Récupère toutes les variables du template
        $variables = $this->extractVariables($processor);

        // Remplace chaque variable
        foreach ($variables as $variable) {
            try {
                $value = $this->resolveValue($customer, $variable);
                $formattedValue = $this->formatValue($value);

                $this->logger->debug('Processing variable', [
                    'variable' => $variable,
                    'value' => $formattedValue
                ]);

                $processor->setValue($variable, $formattedValue);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to process variable', [
                    'variable' => $variable,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Génère le fichier de sortie
        $tempFile = tempnam(sys_get_temp_dir(), 'doc_') . '.docx';
        $processor->saveAs($tempFile);

        return $tempFile;
    }

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
            return $this->propertyAccessor->getValue($object, trim($path));
        } catch (\Exception $e) {
            $this->logger->error('Failed to resolve value', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if ($value instanceof Collection) {
            return (string)$value->count();
        }

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
