<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelReportService
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, mixed>|null  $summaryRow
     * @param  array<int, string>  $footerLines
     * @param  array{
     *     rotated_header_columns?: array<int, int>,
     *     rotated_header_height?: float|int,
     *     fixed_column_widths?: array<int, float|int>,
     *     footer_start_column?: int
     * }  $options
     */
    public function streamTableReport(
        string $filename,
        string $sheetTitle,
        string $mainHeading,
        string $subHeading,
        array $headers,
        array $rows,
        ?array $summaryRow = null,
        array $footerLines = [],
        array $options = []
    ): StreamedResponse {
        return response()->streamDownload(function () use ($sheetTitle, $mainHeading, $subHeading, $headers, $rows, $summaryRow, $footerLines, $options): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($sheetTitle);

            $lastColumn = $this->excelColumnName(count($headers));
            $sheet->setCellValue('A1', $mainHeading);
            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $sheet->setCellValue('A2', $subHeading);
            $sheet->mergeCells("A2:{$lastColumn}2");
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

            foreach ($headers as $index => $header) {
                $sheet->setCellValue($this->excelColumnName($index + 1) . '4', $header);
            }
            $sheet->getStyle("A4:{$lastColumn}4")->getFont()->setBold(true);

            $rotatedHeaderColumns = array_values(array_filter(
                array_map('intval', $options['rotated_header_columns'] ?? []),
                static fn (int $index): bool => $index > 0
            ));
            if ($rotatedHeaderColumns !== []) {
                $rotatedHeaderHeight = (float) ($options['rotated_header_height'] ?? 110);
                $sheet->getRowDimension(4)->setRowHeight($rotatedHeaderHeight);

                foreach ($rotatedHeaderColumns as $columnIndex) {
                    $coordinate = $this->excelColumnName($columnIndex) . '4';
                    $sheet->getStyle($coordinate)->getAlignment()
                        ->setTextRotation(90)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_BOTTOM)
                        ->setWrapText(true);
                }
            }

            foreach (array_values($rows) as $rowIndex => $row) {
                $excelRow = $rowIndex + 5;
                foreach (array_values($row) as $columnIndex => $cell) {
                    $this->writeCell($sheet, $this->excelColumnName($columnIndex + 1) . $excelRow, $cell);
                }
            }

            if ($summaryRow !== null) {
                $summaryExcelRow = count($rows) + 5;
                foreach (array_values($summaryRow) as $columnIndex => $cell) {
                    $this->writeCell($sheet, $this->excelColumnName($columnIndex + 1) . $summaryExcelRow, $cell);
                }
            }

            $footerLines = array_values(array_filter($footerLines, static fn (string $line): bool => trim($line) !== ''));
            if ($footerLines !== []) {
                $footerStartRow = count($rows) + 6 + ($summaryRow !== null ? 1 : 0);
                $footerStartColumn = max(1, (int) ($options['footer_start_column'] ?? 1));
                $footerStartCoordinate = $this->excelColumnName($footerStartColumn);
                foreach ($footerLines as $index => $line) {
                    $footerRow = $footerStartRow + $index;
                    $sheet->setCellValue("{$footerStartCoordinate}{$footerRow}", $line);
                    $sheet->mergeCells("{$footerStartCoordinate}{$footerRow}:{$lastColumn}{$footerRow}");
                    $sheet->getStyle("{$footerStartCoordinate}{$footerRow}")->getFont()->setItalic(true)->setSize(10);
                }
            }

            $fixedColumnWidths = [];
            foreach (($options['fixed_column_widths'] ?? []) as $columnIndex => $width) {
                $columnNumber = (int) $columnIndex;
                if ($columnNumber <= 0) {
                    continue;
                }

                $fixedColumnWidths[$columnNumber] = (float) $width;
                $sheet->getColumnDimension($this->excelColumnName($columnNumber))
                    ->setAutoSize(false)
                    ->setWidth((float) $width);
            }

            for ($index = 1; $index <= count($headers); $index++) {
                if (array_key_exists($index, $fixedColumnWidths)) {
                    continue;
                }

                $sheet->getColumnDimension($this->excelColumnName($index))->setAutoSize(true);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  mixed  $cell
     */
    private function writeCell($sheet, string $coordinate, $cell): void
    {
        if (!is_array($cell) || !array_key_exists('value', $cell)) {
            $sheet->setCellValue($coordinate, $cell);
            return;
        }

        $value = $cell['value'];
        $type = (string) ($cell['type'] ?? '');

        if ($type === 'string') {
            $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
        } else {
            $sheet->setCellValue($coordinate, $value);
        }

        if (isset($cell['format']) && is_string($cell['format']) && $cell['format'] !== '') {
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode($cell['format']);
        }

        if (!empty($cell['bold'])) {
            $sheet->getStyle($coordinate)->getFont()->setBold(true);
        }

        if (isset($cell['align']) && is_string($cell['align']) && $cell['align'] !== '') {
            $sheet->getStyle($coordinate)->getAlignment()->setHorizontal($cell['align']);
        }

        if (!empty($cell['border'])) {
            $sheet->getStyle($coordinate)->getBorders()->getAllBorders()->setBorderStyle($cell['border']);
        }

        if (!empty($cell['top_border'])) {
            $sheet->getStyle($coordinate)->getBorders()->getTop()->setBorderStyle($cell['top_border']);
        }

        if (!empty($cell['bottom_border'])) {
            $sheet->getStyle($coordinate)->getBorders()->getBottom()->setBorderStyle($cell['bottom_border']);
        }
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
