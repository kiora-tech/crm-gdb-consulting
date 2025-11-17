#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__.'/../public/examples/import_complet_exemple.xlsx';

echo "Adding 'Commercial' column to import_complet_exemple.xlsx...\n";

// Load the spreadsheet
$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();

// Find the next available column after the last header
$lastColumn = $sheet->getHighestDataColumn();
$nextColumn = ++$lastColumn; // Increment to next column letter

echo "Last column: {$lastColumn}, Next column: {$nextColumn}\n";

// Add "Commercial" header
$sheet->setCellValue($nextColumn.'1', 'Commercial');

// Get the number of data rows
$highestRow = $sheet->getHighestRow();

echo "Total rows: {$highestRow}\n";

// Add example commercial emails to some rows (optional)
// Using emails from UserFixtures.php
// Row 2: admin@test.com (admin_user)
// Row 3: user@test.com (regular_user)
// Row 4: leave empty (to test optional behavior - will use import user)
if ($highestRow >= 2) {
    $sheet->setCellValue($nextColumn.'2', 'admin@test.com');
    echo "Row 2: Set commercial to 'admin@test.com'\n";
}
if ($highestRow >= 3) {
    $sheet->setCellValue($nextColumn.'3', 'user@test.com');
    echo "Row 3: Set commercial to 'user@test.com'\n";
}
// Row 4 is intentionally left empty to test default behavior

// Save the modified file
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($file);

echo "✓ Commercial column added successfully!\n";
echo "Column {$nextColumn} now contains 'Commercial' header\n";
