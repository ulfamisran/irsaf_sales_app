<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExporter
{
    public static function downloadFromHtml(string $html, string $filename, string $module = 'generic'): StreamedResponse
    {
        $reader = new HtmlReader();
        $spreadsheet = $reader->loadFromString($html);
        $sheet = $spreadsheet->getActiveSheet();

        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        // 1) Auto width all used columns first.
        $colEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        for ($i = 1; $i <= $colEnd; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 2) Table-like styling for used range.
        $usedRange = 'A1:' . $highestColumn . $highestRow;
        $sheet->getStyle($usedRange)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFDDDDDD'],
                ],
            ],
            'font' => [
                'name' => 'Calibri',
                'size' => 10,
            ],
        ]);

        // 3) Header row formatting (module-specific row index).
        $headerRow = self::headerRowFor($module);
        $headerRange = 'A' . $headerRow . ':' . $highestColumn . $headerRow;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF3F4F6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->freezePane('A' . ($headerRow + 1));

        // 4) Module-specific polish (fixed width, numeric alignment, currency format, merged titles).
        self::applyModuleFormatting($sheet, $module, $highestColumn, $highestRow, $headerRow);

        $safeFilename = str_ends_with(strtolower($filename), '.xlsx')
            ? $filename
            : ($filename . '.xlsx');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $safeFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, must-revalidate, no-cache, no-store',
            'Pragma' => 'public',
            'Expires' => '0',
        ]);
    }

    private static function headerRowFor(string $module): int
    {
        return in_array($module, ['sales', 'services', 'rentals'], true) ? 3 : 1;
    }

    private static function applyModuleFormatting(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $module,
        string $highestColumn,
        int $highestRow,
        int $headerRow
    ): void {
        // Merge and style title rows for report modules with two heading lines.
        if (in_array($module, ['sales', 'services', 'rentals'], true)) {
            $sheet->mergeCells('A1:' . $highestColumn . '1');
            $sheet->mergeCells('A2:' . $highestColumn . '2');
            $sheet->getStyle('A1:A2')->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);

            // Fixed widths to keep layout stable.
            $widths = ['A' => 22, 'B' => 14, 'C' => 16, 'D' => 16, 'E' => 14, 'F' => 26, 'G' => 18, 'H' => 18];
            foreach ($widths as $col => $w) {
                if ($col <= $highestColumn) {
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            }

            // Currency columns for total/kurang bayar.
            $numRange = 'C' . ($headerRow + 1) . ':D' . $highestRow;
            $sheet->getStyle($numRange)->getNumberFormat()->setFormatCode('"Rp" #,##0');
            $sheet->getStyle($numRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        if ($module === 'stock') {
            $widths = [
                'A' => 18, 'B' => 16, 'C' => 16, 'D' => 20, 'E' => 44, 'F' => 20, 'G' => 20,
                'H' => 14, 'I' => 14, 'J' => 14, 'K' => 22, 'L' => 16, 'M' => 22,
            ];
            foreach ($widths as $col => $w) {
                if ($col <= $highestColumn) {
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            }

            $moneyRange = 'H' . ($headerRow + 1) . ':I' . $highestRow;
            $sheet->getStyle($moneyRange)->getNumberFormat()->setFormatCode('"Rp" #,##0');
            $sheet->getStyle($moneyRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
}
