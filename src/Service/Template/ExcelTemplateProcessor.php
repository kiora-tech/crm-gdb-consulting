<?php

namespace App\Service\Template;

use App\Entity\Customer;
use App\Entity\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;

class ExcelTemplateProcessor
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly VariableResolver $variableResolver,
    ) {
    }

    /**
     * Traite un template Excel en remplaçant les variables.
     */
    public function process(string $templatePath, Customer $customer, ?User $currentUser = null): string
    {
        $this->logger->debug('Traitement du template Excel', ['template_path' => $templatePath]);

        // Charger le fichier Excel
        $spreadsheet = IOFactory::load($templatePath);

        // Compter le nombre de feuilles
        $sheetCount = $spreadsheet->getSheetCount();
        $this->logger->info('Nombre de feuilles dans le fichier Excel', ['count' => $sheetCount]);

        // Parcourir toutes les feuilles par index pour être sûr de toutes les traiter
        for ($sheetIndex = 0; $sheetIndex < $sheetCount; ++$sheetIndex) {
            $worksheet = $spreadsheet->getSheet($sheetIndex);
            $this->logger->debug('Traitement de la feuille', [
                'sheet_index' => $sheetIndex,
                'sheet_name' => $worksheet->getTitle(),
            ]);

            // Utiliser getCoordinates() pour obtenir toutes les cellules avec des valeurs
            $coordinates = $worksheet->getCoordinates();
            $this->logger->debug('Nombre de cellules avec valeurs', [
                'sheet' => $worksheet->getTitle(),
                'count' => count($coordinates),
            ]);

            foreach ($coordinates as $coordinate) {
                $cell = $worksheet->getCell($coordinate);

                // Vérifier si la cellule n'est pas une formule
                if (!$cell->hasHyperlink() && !$cell->isFormula()) {
                    $cellValue = $cell->getValue();

                    // Vérifier si la cellule contient des variables
                    if (is_string($cellValue) && preg_match_all('/\${([^}]+)}/', $cellValue, $matches)) {
                        $newValue = $this->processVariables($cellValue, $matches, $customer, $currentUser, $coordinate, $worksheet->getTitle());

                        // Mettre à jour la cellule avec la nouvelle valeur
                        // Utiliser setValueExplicit pour éviter l'interprétation comme formule
                        $cell->setValueExplicit($newValue, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                        $this->logger->debug('Cellule mise à jour', [
                            'cell' => $coordinate,
                            'old_value' => $cellValue,
                            'new_value' => $newValue,
                        ]);
                    }
                } elseif ($cell->isFormula()) {
                    $this->logger->debug('Cellule avec formule ignorée', [
                        'cell' => $coordinate,
                        'formula' => $cell->getValue(),
                    ]);
                }
            }
        }

        // Sauvegarder le fichier Excel modifié
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'excel_').'.xlsx';

        $writer = new Xlsx($spreadsheet);

        // Configurer le writer pour préserver les formules
        $writer->setPreCalculateFormulas(false);

        try {
            $writer->save($tempFile);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la sauvegarde du fichier Excel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Erreur lors de la génération du document Excel: '.$e->getMessage());
        }

        $this->logger->info('Fichier Excel temporaire créé avec succès', [
            'temp_file' => $tempFile,
            'file_size' => filesize($tempFile),
        ]);

        return $tempFile;
    }

    /**
     * Traite les variables dans une chaîne.
     *
     * @param array<int, array<int, string>> $matches
     */
    private function processVariables(string $value, array $matches, Customer $customer, ?User $currentUser, string $coordinate, string $sheetName): string
    {
        $newValue = $value;

        $this->logger->debug('Variables trouvées dans cellule', [
            'cell' => $coordinate,
            'sheet' => $sheetName,
            'value' => $value,
            'variables_count' => count($matches[0]),
        ]);

        foreach ($matches[0] as $index => $placeholder) {
            $variable = $matches[1][$index];
            $formattedValue = '';  // Valeur par défaut vide

            try {
                $this->logger->debug('Résolution de variable Excel', [
                    'cell' => $coordinate,
                    'variable' => $variable,
                ]);

                $resolvedValue = $this->variableResolver->resolve($variable, $customer, $currentUser);
                $formattedValue = $this->variableResolver->format($resolvedValue);

                $this->logger->debug('Variable résolue avec succès', [
                    'variable' => $variable,
                    'value' => $formattedValue,
                ]);
            } catch (\Exception $e) {
                $this->logger->debug('Variable non résolue, remplacée par vide', [
                    'variable' => $variable,
                    'cell' => $coordinate,
                    'sheet' => $sheetName,
                    'reason' => $e->getMessage(),
                ]);
                // La variable reste vide
            }

            // Remplacer la variable dans la chaîne (soit par la valeur trouvée, soit par vide)
            $newValue = str_replace($placeholder, $formattedValue, $newValue);
        }

        return $newValue;
    }
}
