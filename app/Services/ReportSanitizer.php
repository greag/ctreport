<?php

namespace App\Services;

class ReportSanitizer
{
    public function sanitize(array $data): array
    {
        $sanitized = json_decode(json_encode($data, JSON_UNESCAPED_UNICODE), true);
        $this->walk($sanitized);
        return $sanitized;
    }

    private function walk(array &$obj): void
    {
        foreach ($obj as $key => $value) {
            if (is_array($value)) {
                $this->walk($obj[$key]);
                continue;
            }

            $currencyFields = [
                'Income', 'CreditLimit', 'CashLimit', 'HighCredit', 'SanctionedAmount',
                'CurrentBalance', 'AmountOverdue', 'EmiAmount', 'ActualPaymentAmount',
                'ValueOfCollateral', 'WrittenOffAmountTotal', 'WrittenOffAmountPrincipal', 'SettlementAmount',
            ];

            if ($key === 'AccountNumber') {
                $obj[$key] = $this->cleanAccountNumber($value);
            } elseif (in_array($key, $currencyFields, true)) {
                $obj[$key] = $this->cleanCurrency($value);
            } elseif ($key === 'ControlNumber') {
                $obj[$key] = str_replace('.', '', $this->ensureNA($value));
            } else {
                $obj[$key] = $this->ensureNA($value);
            }
        }
    }

    private function ensureNA($value): string
    {
        $strValue = trim((string) ($value ?? ''));
        return $strValue === '' ? 'N/A' : $strValue;
    }

    private function cleanCurrency($value): string
    {
        $str = $this->ensureNA($value);
        if ($str === 'N/A') {
            return 'N/A';
        }
        return trim(str_replace(['â‚¹', ','], '', $str));
    }

    private function cleanAccountNumber($value): string
    {
        $str = trim((string) ($value ?? ''));
        if ($str === '' || strtoupper($str) === 'N/A') {
            return 'N/A';
        }
        $cleaned = preg_replace('/[^a-zA-Z0-9\-\s\/\.]/', '', $str);
        $cleaned = $cleaned ? trim($cleaned) : '';
        return $cleaned === '' ? 'N/A' : $cleaned;
    }
}
