<?php
require 'vendor/autoload.php';

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('cuentas_supercias.xlsm');
$sheets = ['ESF', 'ERI', 'ECP'];

foreach ($sheets as $sheetName) {
    echo "--- SHEET: $sheetName ---\n";
    $sheet = $spreadsheet->getSheetByName($sheetName);
    if (!$sheet) {
        echo "Sheet not found\n";
        continue;
    }
    
    // Read rows 1 to 20 just to see structure
    $rows = $sheet->toArray(null, true, true, true);
    for ($i = 1; $i <= 20; $i++) {
        if (!isset($rows[$i])) break;
        echo "Row $i: " . json_encode($rows[$i]) . "\n";
    }
}
