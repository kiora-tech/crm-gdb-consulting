<?php

declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create valid test Excel file
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers
$sheet->setCellValue('A1', 'Raison sociale');
$sheet->setCellValue('B1', 'SIRET');
$sheet->setCellValue('C1', 'Email');
$sheet->setCellValue('D1', 'Téléphone');
$sheet->setCellValue('E1', 'Adresse');
$sheet->setCellValue('F1', 'Code postal');
$sheet->setCellValue('G1', 'Ville');

// Sample data rows
$sheet->setCellValue('A2', 'ACME Corporation');
$sheet->setCellValue('B2', '12345678901234');
$sheet->setCellValue('C2', 'contact@acme.fr');
$sheet->setCellValue('D2', '0123456789');
$sheet->setCellValue('E2', '123 rue de la Paix');
$sheet->setCellValue('F2', '75001');
$sheet->setCellValue('G2', 'Paris');

$sheet->setCellValue('A3', 'Tech Solutions SAS');
$sheet->setCellValue('B3', '98765432109876');
$sheet->setCellValue('C3', 'info@techsolutions.fr');
$sheet->setCellValue('D3', '0987654321');
$sheet->setCellValue('E3', '456 avenue des Champs');
$sheet->setCellValue('F3', '69001');
$sheet->setCellValue('G3', 'Lyon');

$sheet->setCellValue('A4', 'Global Services');
$sheet->setCellValue('B4', '11122233344455');
$sheet->setCellValue('C4', 'contact@global.fr');
$sheet->setCellValue('D4', '0147258369');
$sheet->setCellValue('E4', '789 boulevard Saint-Germain');
$sheet->setCellValue('F4', '33000');
$sheet->setCellValue('G4', 'Bordeaux');

$writer = new Xlsx($spreadsheet);
$writer->save(__DIR__.'/test_import_valid.xlsx');
echo "Created test_import_valid.xlsx\n";

// Create Excel file with large dataset (for batch testing)
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers
$sheet->setCellValue('A1', 'Raison sociale');
$sheet->setCellValue('B1', 'SIRET');
$sheet->setCellValue('C1', 'Email');

// Generate 250 rows
for ($i = 2; $i <= 251; ++$i) {
    $num = $i - 1;
    $sheet->setCellValue('A'.$i, 'Company '.$num);
    $sheet->setCellValue('B'.$i, str_pad((string) $num, 14, '0', STR_PAD_LEFT));
    $sheet->setCellValue('C'.$i, 'company'.$num.'@example.fr');
}

$writer = new Xlsx($spreadsheet);
$writer->save(__DIR__.'/test_import_large.xlsx');
echo "Created test_import_large.xlsx (250 rows)\n";

// Create Excel file with only headers (no data)
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Raison sociale');
$sheet->setCellValue('B1', 'SIRET');

$writer = new Xlsx($spreadsheet);
$writer->save(__DIR__.'/test_import_empty.xlsx');
echo "Created test_import_empty.xlsx (headers only)\n";

// Create Excel file with mixed content
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue('A1', 'Name');
$sheet->setCellValue('B1', 'Number');
$sheet->setCellValue('C1', 'Date');

$sheet->setCellValue('A2', 'Test Entry');
$sheet->setCellValue('B2', 12345);
$sheet->setCellValue('C2', '2024-01-15');

$writer = new Xlsx($spreadsheet);
$writer->save(__DIR__.'/test_import_mixed.xlsx');
echo "Created test_import_mixed.xlsx (mixed content)\n";

echo "\nAll test Excel files created successfully!\n";
