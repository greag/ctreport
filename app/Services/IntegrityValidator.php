<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class IntegrityValidator
{
    public function validate(array $meta, string $textPath, ExcelExporter $exporter): array
    {
        $issues = [];
        $warnings = [];

        $payload = $meta['structuredData']['InputResponse'] ?? [];
        $text = is_file($textPath) ? file_get_contents($textPath) : '';

        $this->checkRequiredFields($payload, $issues);
        $this->checkCounts($payload, $issues);
        $this->checkTextPresence($payload, $text, $warnings);
        $excelReport = $this->checkExcelConsistency($payload, $exporter, $meta['fileName'] ?? 'credit_report');

        foreach ($excelReport['issues'] as $issue) {
            $issues[] = $issue;
        }
        foreach ($excelReport['warnings'] as $warning) {
            $warnings[] = $warning;
        }

        return [
            'ok' => count($issues) === 0,
            'issues' => $issues,
            'warnings' => $warnings,
            'counts' => $excelReport['counts'],
        ];
    }

    private function checkRequiredFields(array $payload, array &$issues): void
    {
        $report = $payload['ReportInformation'] ?? [];
        $required = [
            'Score' => $report['Score'] ?? null,
            'ReportDate' => $report['ReportDate'] ?? null,
            'ControlNumber' => $report['ControlNumber'] ?? null,
        ];

        foreach ($required as $key => $value) {
            if (!$value || $value === 'N/A') {
                $issues[] = [
                    'code' => 'missing_report_' . strtolower($key),
                    'message' => "{$key} is missing from ReportInformation.",
                ];
            }
        }
    }

    private function checkCounts(array $payload, array &$issues): void
    {
        $addresses = $payload['IDAndContactInfo']['ContactInformation']['Addresses'] ?? [];
        $accounts = $payload['Accounts'] ?? [];
        if (count($addresses) === 0) {
            $issues[] = [
                'code' => 'no_addresses',
                'message' => 'No addresses were captured.',
            ];
        }
        if (count($accounts) === 0) {
            $issues[] = [
                'code' => 'no_accounts',
                'message' => 'No accounts were captured.',
            ];
        }
    }

    private function checkTextPresence(array $payload, string $text, array &$warnings): void
    {
        $personal = $payload['PersonalInformation'] ?? [];
        $report = $payload['ReportInformation'] ?? [];
        $pan = $payload['IDAndContactInfo']['Identifications'][0]['IdNumber'] ?? null;

        $checks = [
            'ControlNumber' => $report['ControlNumber'] ?? null,
            'ReportDate' => $report['ReportDate'] ?? null,
            'Name' => $personal['Name'] ?? null,
            'PAN' => $pan,
        ];

        foreach ($checks as $label => $value) {
            if (!$value || $value === 'N/A') {
                continue;
            }
            if ($text !== '' && stripos($text, (string) $value) === false) {
                $warnings[] = [
                    'code' => 'text_missing_' . strtolower($label),
                    'message' => "{$label} not found in extracted text.",
                ];
            }
        }
    }

    private function checkExcelConsistency(array $payload, ExcelExporter $exporter, string $fileName): array
    {
        $issues = [];
        $warnings = [];
        $counts = [];

        $tempPath = $exporter->createExcel($payload, $fileName);

        try {
            $spreadsheet = IOFactory::load($tempPath);
        } catch (\Throwable $e) {
            return [
                'issues' => [[
                    'code' => 'excel_load_failed',
                    'message' => 'Failed to read generated Excel file for validation.',
                ]],
                'warnings' => [],
                'counts' => [],
            ];
        } finally {
            @unlink($tempPath);
        }

        $sheetCounts = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = $sheet->getTitle();
            $rows = max(0, $sheet->getHighestRow() - 1);
            $sheetCounts[$name] = $rows;
        }

        $expected = $this->expectedExcelCounts($payload);
        foreach ($expected as $sheet => $expectedCount) {
            if (!array_key_exists($sheet, $sheetCounts)) {
                $warnings[] = [
                    'code' => 'excel_sheet_missing',
                    'message' => "Expected sheet '{$sheet}' is missing.",
                ];
                continue;
            }
            $actual = $sheetCounts[$sheet];
            if ($expectedCount !== $actual) {
                $warnings[] = [
                    'code' => 'excel_count_mismatch',
                    'message' => "Sheet '{$sheet}' has {$actual} rows; expected {$expectedCount}.",
                ];
            }
        }

        $counts = [
            'expected' => $expected,
            'actual' => $sheetCounts,
        ];

        return [
            'issues' => $issues,
            'warnings' => $warnings,
            'counts' => $counts,
        ];
    }

    private function expectedExcelCounts(array $payload): array
    {
        $addresses = $payload['IDAndContactInfo']['ContactInformation']['Addresses'] ?? [];
        $telephones = $payload['IDAndContactInfo']['ContactInformation']['Telephones'] ?? [];
        $emails = $payload['IDAndContactInfo']['ContactInformation']['Emails'] ?? [];
        $identifications = $payload['IDAndContactInfo']['Identifications'] ?? [];
        $accounts = $payload['Accounts'] ?? [];
        $enquiries = $payload['Enquiries'] ?? [];
        $additional = $payload['AdditionalInformation'] ?? [];

        $paymentRows = 0;
        foreach ($accounts as $account) {
            $history = $account['PaymentHistory'] ?? [];
            $paymentRows += max(1, count($history));
        }

        return [
            'Report Information' => 1,
            'Personal Information' => 1,
            'Identification Details' => count($identifications),
            'Addresses' => count($addresses),
            'Telephones' => count($telephones),
            'Emails' => count($emails),
            'Employment' => 1,
            'Accounts' => count($accounts),
            'Payment Status' => $paymentRows,
            'Enquiries' => count($enquiries),
            'Additional Information' => count($additional),
        ];
    }
}
