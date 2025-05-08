<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Filtre de lecture pour ne charger que les lignes spécifiées de l'Excel.
 */
class ExcelBatchReadFilter implements IReadFilter
{
    private int $startRow;
    private int $endRow;

    public function __construct(int $startRow, int $endRow)
    {
        $this->startRow = $startRow;
        $this->endRow = $endRow;
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        // Toujours lire la ligne d'en-tête (ligne 1)
        if (1 == $row || ($row >= $this->startRow && $row <= $this->endRow)) {
            return true;
        }

        return false;
    }
}
