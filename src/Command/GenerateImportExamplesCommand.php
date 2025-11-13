<?php

declare(strict_types=1);

namespace App\Command;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:generate-examples',
    description: 'Generate example import files for each import type',
)]
class GenerateImportExamplesCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputDir = dirname(__DIR__, 2).'/public/examples';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Generate CUSTOMER import example
        $this->generateCustomerExample($outputDir);
        $io->success('Generated: '.$outputDir.'/import_clients_exemple.xlsx');

        // Generate ENERGY import example
        $this->generateEnergyExample($outputDir);
        $io->success('Generated: '.$outputDir.'/import_energies_exemple.xlsx');

        // Generate CONTACT import example
        $this->generateContactExample($outputDir);
        $io->success('Generated: '.$outputDir.'/import_contacts_exemple.xlsx');

        // Generate FULL import example
        $this->generateFullExample($outputDir);
        $io->success('Generated: '.$outputDir.'/import_complet_exemple.xlsx');

        $io->note('Ces fichiers sont disponibles dans public/examples/');
        $io->note('URL: http://localhost:8080/examples/import_clients_exemple.xlsx');
        $io->note('URL: http://localhost:8080/examples/import_energies_exemple.xlsx');
        $io->note('URL: http://localhost:8080/examples/import_contacts_exemple.xlsx');
        $io->note('URL: http://localhost:8080/examples/import_complet_exemple.xlsx');

        return Command::SUCCESS;
    }

    private function generateCustomerExample(string $outputDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Raison Sociale');
        $sheet->setCellValue('B1', 'SIRET');
        $sheet->setCellValue('C1', 'Origine du lead');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ],
        ];
        $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

        // Example data
        $examples = [
            ['ENTREPRISE EXEMPLE SAS', '12345678901234', 'Apport partenaire'],
            ['SOCIETE TEST SARL', '98765432109876', 'Prospection téléphonique'],
            ['DEMO COMPANY', '11122233344455', 'Salon professionnel'],
        ];

        $row = 2;
        foreach ($examples as $example) {
            $sheet->setCellValue('A'.$row, $example[0]);
            $sheet->setCellValue('B'.$row, $example[1]);
            $sheet->setCellValue('C'.$row, $example[2]);
            ++$row;
        }

        // Auto-size columns
        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Save
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputDir.'/import_clients_exemple.xlsx');
    }

    private function generateEnergyExample(string $outputDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = [
            'A1' => 'SIRET Client',
            'B1' => 'Raison Sociale',
            'C1' => 'Fournisseur',
            'D1' => 'PDL/PCE',
            'E1' => 'Type Energie',
            'F1' => 'Échéance',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ],
        ];
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

        // Example data
        $examples = [
            ['12345678901234', 'BOULANGERIE MARTIN', 'EDF', '12345678901234', 'ELEC', '31/12/2025'],
            ['98765432109876', 'GARAGE DUPONT SARL', 'ENGIE', '98765432109876', 'GAZ', '15/06/2026'],
            ['11122233344455', 'RESTAURANT LE BON COIN', 'TOTAL ENERGIES', '11122233344455', 'ELEC', '01/03/2026'],
        ];

        $row = 2;
        foreach ($examples as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col.$row, $value);
                ++$col;
            }
            ++$row;
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add notes sheet
        $notesSheet = $spreadsheet->createSheet();
        $notesSheet->setTitle('Instructions');
        $notesSheet->setCellValue('A1', 'INSTRUCTIONS POUR L\'IMPORT ÉNERGIES');
        $notesSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $instructions = [
            '',
            'Colonnes requises :',
            '  - SIRET Client OU Raison Sociale : Pour identifier le client',
            '',
            'Colonnes optionnelles :',
            '  - Fournisseur : Nom du fournisseur d\'énergie actuel',
            '  - PDL/PCE : Point de livraison (électricité) ou PCE (gaz)',
            '  - Type Energie : ELEC ou GAZ',
            '  - Échéance : Date d\'échéance du contrat (format JJ/MM/AAAA)',
            '',
            'Notes importantes :',
            '  - Le client doit exister dans la base de données',
            '  - Si SIRET fourni, recherche par SIRET',
            '  - Sinon, recherche par Raison Sociale',
            '  - Les contrats en doublon ne seront pas créés',
            '  - Un rapport détaillé sera généré avant l\'import',
        ];

        $row = 2;
        foreach ($instructions as $instruction) {
            $notesSheet->setCellValue('A'.$row, $instruction);
            ++$row;
        }

        $notesSheet->getColumnDimension('A')->setWidth(80);
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputDir.'/import_energies_exemple.xlsx');
    }

    private function generateContactExample(string $outputDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = [
            'A1' => 'SIRET Client',
            'B1' => 'Raison Sociale',
            'C1' => 'Prénom',
            'D1' => 'Nom',
            'E1' => 'Email',
            'F1' => 'Téléphone',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ],
        ];
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

        // Example data
        $examples = [
            ['12345678901234', 'BOULANGERIE MARTIN', 'Jean', 'Martin', 'j.martin@boulangerie-martin.fr', '0601020304'],
            ['98765432109876', 'GARAGE DUPONT SARL', 'Marie', 'Dupont', 'contact@garage-dupont.com', '0612345678'],
            ['11122233344455', 'RESTAURANT LE BON COIN', 'Pierre', 'Leblanc', 'pierre@leboncoin-resto.fr', '0623456789'],
        ];

        $row = 2;
        foreach ($examples as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col.$row, $value);
                ++$col;
            }
            ++$row;
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add notes sheet
        $notesSheet = $spreadsheet->createSheet();
        $notesSheet->setTitle('Instructions');
        $notesSheet->setCellValue('A1', 'INSTRUCTIONS POUR L\'IMPORT CONTACTS');
        $notesSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $instructions = [
            '',
            'Colonnes requises :',
            '  - SIRET Client OU Raison Sociale : Pour identifier le client',
            '  - Prénom OU Nom : Au moins un nom est requis',
            '',
            'Colonnes optionnelles :',
            '  - Email : Adresse email du contact',
            '  - Téléphone : Numéro de téléphone',
            '',
            'Notes importantes :',
            '  - Le client doit exister dans la base de données',
            '  - Si SIRET fourni, recherche par SIRET',
            '  - Sinon, recherche par Raison Sociale',
            '  - Les contacts en doublon (même email/téléphone) ne seront pas créés',
            '  - Un rapport détaillé sera généré avant l\'import',
        ];

        $row = 2;
        foreach ($instructions as $instruction) {
            $notesSheet->setCellValue('A'.$row, $instruction);
            ++$row;
        }

        $notesSheet->getColumnDimension('A')->setWidth(80);
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputDir.'/import_contacts_exemple.xlsx');
    }

    private function generateFullExample(string $outputDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers - All possible columns
        $headers = [
            'A1' => 'Raison Sociale',
            'B1' => 'SIRET',
            'C1' => 'Prénom',
            'D1' => 'Nom',
            'E1' => 'Email',
            'F1' => 'Téléphone',
            'G1' => 'Fournisseur',
            'H1' => 'PDL/PCE',
            'I1' => 'Type Energie',
            'J1' => 'Échéance',
            'K1' => 'Origine du lead',
            'L1' => 'Commentaire',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ],
        ];
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

        // Example data
        $examples = [
            [
                'BOULANGERIE MARTIN',
                '12345678901234',
                'Jean',
                'Martin',
                'j.martin@boulangerie-martin.fr',
                '0601020304',
                'EDF',
                '12345678901234',
                'ELEC',
                '31/12/2025',
                'Apport partenaire',
                'Intéressé par une offre verte',
            ],
            [
                'GARAGE DUPONT SARL',
                '98765432109876',
                'Marie',
                'Dupont',
                'contact@garage-dupont.com',
                '0612345678',
                'ENGIE',
                '98765432109876',
                'GAZ',
                '15/06/2026',
                'Prospection téléphonique',
                'Souhaite comparer les offres',
            ],
            [
                'RESTAURANT LE BON COIN',
                '11122233344455',
                'Pierre',
                'Leblanc',
                'pierre@leboncoin-resto.fr',
                '0623456789',
                'TOTAL ENERGIES',
                '11122233344455',
                'ELEC',
                '01/03/2026',
                'Site web',
                'Recherche économies d\'énergie',
            ],
        ];

        $row = 2;
        foreach ($examples as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col.$row, $value);
                ++$col;
            }
            ++$row;
        }

        // Auto-size columns
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add notes sheet
        $notesSheet = $spreadsheet->createSheet();
        $notesSheet->setTitle('Instructions');
        $notesSheet->setCellValue('A1', 'INSTRUCTIONS POUR L\'IMPORT COMPLET');
        $notesSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $instructions = [
            '',
            'Colonnes obligatoires :',
            '  - Raison Sociale : Nom de l\'entreprise (obligatoire)',
            '',
            'Colonnes optionnelles :',
            '  - SIRET : Numéro SIRET à 14 chiffres',
            '  - Prénom : Prénom du contact',
            '  - Nom : Nom de famille du contact',
            '  - Email : Adresse email du contact',
            '  - Téléphone : Numéro de téléphone',
            '  - Fournisseur : Nom du fournisseur d\'énergie actuel',
            '  - PDL/PCE : Point de livraison (électricité) ou PCE (gaz)',
            '  - Type Energie : ELEC ou GAZ',
            '  - Échéance : Date d\'échéance du contrat (format JJ/MM/AAAA)',
            '  - Origine du lead : Source du prospect',
            '  - Commentaire : Notes ou remarques',
            '',
            'Informations sur les contacts :',
            '  - Les contacts sont créés automatiquement si Prénom, Nom, Email ou Téléphone sont renseignés',
            '  - Les contacts en doublon (même email ou téléphone) ne seront pas recréés',
            '',
            'Formats de date acceptés :',
            '  - JJ/MM/AAAA (ex: 31/12/2025)',
            '  - AAAA-MM-JJ (ex: 2025-12-31)',
            '  - Numéro Excel (sera converti automatiquement)',
            '',
            'Notes importantes :',
            '  - Les lignes sans raison sociale seront ignorées',
            '  - Les doublons (même SIRET ou nom) ne seront pas créés',
            '  - Les données existantes peuvent être mises à jour',
            '  - Un rapport détaillé sera généré avant l\'import',
            '  - IMPORTANT : Les noms de colonnes doivent être uniques (pas de doublons)',
        ];

        $row = 2;
        foreach ($instructions as $instruction) {
            $notesSheet->setCellValue('A'.$row, $instruction);
            ++$row;
        }

        $notesSheet->getColumnDimension('A')->setWidth(80);

        // Set active sheet back to data
        $spreadsheet->setActiveSheetIndex(0);

        // Save
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputDir.'/import_complet_exemple.xlsx');
    }
}
