<?php

namespace App\Services;

class CibilParser
{
    private array $warnings = [];

    public function parse(string $text, string $headerText = ''): array
    {
        $this->warnings = [];
        $lines = $this->cleanLines($text);
        $headerLines = $headerText ? $this->cleanLines($headerText) : [];

        $reportInfo = [
            'Score' => $this->extractScore($lines, $headerLines),
            'ReportDate' => $this->extractReportDate($lines, $headerLines),
            'ControlNumber' => $this->extractControlNumber($lines, $headerLines),
        ];

        $personal = [
            'Name' => $this->extractName($lines),
            'DateOfBirth' => $this->extractDateOfBirth($lines),
            'Gender' => $this->extractGender($lines),
        ];

        $identifications = $this->extractIdentifications($lines);
        if (count($identifications) === 0) {
            $pan = $this->extractPan($lines);
            if ($pan !== 'N/A') {
                $identifications[] = [
                    'Sequence' => '1',
                    'IdentificationType' => 'Income Tax ID Number (PAN)',
                    'IdNumber' => $pan,
                    'IssueDate' => 'N/A',
                    'ExpiryDate' => 'N/A',
                ];
            }
        }

        $addresses = $this->extractAddresses($lines);
        $telephones = $this->extractTelephones($lines);
        $emails = $this->extractEmails($lines);

        $employment = $this->extractEmployment($lines);
        $accounts = $this->extractAccounts($lines);
        $enquiries = $this->extractEnquiries($lines);
        $additional = $this->extractAdditionalInformation($lines);

        $payload = [
            'ReportInformation' => $reportInfo,
            'PersonalInformation' => $personal,
            'IDAndContactInfo' => [
                'Identifications' => $identifications,
                'ContactInformation' => [
                    'Addresses' => $addresses,
                    'Telephones' => $telephones,
                    'Emails' => $emails,
                ],
            ],
            'EmploymentInformation' => $employment,
            'Accounts' => $accounts,
            'Enquiries' => $enquiries,
            'AdditionalInformation' => $additional,
        ];

        $this->applyValidation($payload);
        $payload['Warnings'] = $this->warnings;

        return $payload;
    }

    private function cleanLines(string $text): array
    {
        $text = str_replace('--- PAGE BREAK ---', "\n--- PAGE BREAK ---\n", $text);
        $lines = preg_split('/\R/', $text) ?: [];
        $cleaned = [];
        $markers = [
            'ADDRESS DETAILS',
            'CONTACT DETAILS',
            'EMAIL DETAILS',
            'EMPLOYMENT DETAILS',
            'ALL ACCOUNTS',
            'OPEN ACCOUNTS',
            'CLOSED ACCOUNTS',
            'ACCOUNT DETAILS',
            'PAYMENT STATUS',
            'PAYMENT HISTORY',
            'ENQUIRY DETAILS',
        ];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));
            if ($line === '' || str_contains($line, 'myscore.cibil.com')) {
                continue;
            }
            $parts = $this->splitByMarkers($line, $markers);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $cleaned[] = $part;
            }
        }
        return $cleaned;
    }

    private function extractControlNumber(array $lines, array $headerLines): string
    {
        $sources = array_merge($headerLines, $lines);
        foreach ($sources as $line) {
            if (preg_match('/Control Number\s*:\s*([0-9,\.]+)/i', $line, $m)) {
                return preg_replace('/[^0-9]/', '', $m[1]);
            }
        }
        return 'N/A';
    }

    private function extractReportDate(array $lines, array $headerLines): string
    {
        $sources = array_merge($headerLines, $lines);
        foreach ($sources as $line) {
            if (preg_match('/Date\s*:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/i', $line, $m)) {
                return $m[1];
            }
            if (preg_match('/as of Date\s*:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/i', $line, $m)) {
                return $m[1];
            }
        }
        return 'N/A';
    }

    private function extractScore(array $lines, array $headerLines): string
    {
        $sources = array_merge($headerLines, $lines);
        foreach ($sources as $idx => $line) {
            if (preg_match('/Your CIBIL Score is\s+(\d{3})/i', $line, $m)) {
                return $m[1];
            }
            if (preg_match('/CIBIL Score is\s+(\d{3})/i', $line, $m)) {
                return $m[1];
            }
            if (preg_match('/Your CIBIL Score is/i', $line)) {
                $candidate = $this->nearbyScoreValue($sources, $idx);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        // Fallback: first standalone 3-digit score near the top of the report.
        for ($i = 0; $i < min(80, count($sources)); $i++) {
            $candidate = $this->parseStandaloneScore($sources[$i]);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return 'N/A';
    }

    private function nearbyScoreValue(array $lines, int $index): ?string
    {
        $start = max(0, $index - 3);
        $end = min(count($lines) - 1, $index + 5);
        for ($i = $start; $i <= $end; $i++) {
            $candidate = $this->parseStandaloneScore($lines[$i]);
            if ($candidate !== null) {
                return $candidate;
            }
        }
        return null;
    }

    private function parseStandaloneScore(string $line): ?string
    {
        $line = trim($line);
        if ($line === '300 900' || $line === '300' || $line === '900') {
            return null;
        }
        if (preg_match('/^\d{3}$/', $line)) {
            $value = (int) $line;
            if ($value >= 300 && $value <= 900) {
                return $line;
            }
        }
        return null;
    }

    private function extractName(array $lines): string
    {
        foreach ($lines as $line) {
            if (preg_match('/Hello,\s*(.+)$/i', $line, $m)) {
                return trim($m[1]);
            }
        }
        for ($i = 0; $i < count($lines); $i++) {
            if (strtoupper($lines[$i]) === 'NAME' && isset($lines[$i + 1])) {
                return $lines[$i + 1];
            }
        }
        return 'N/A';
    }

    private function extractDateOfBirth(array $lines): string
    {
        foreach ($lines as $line) {
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+(Male|Female|Other)/i', $line, $m)) {
                return $m[1];
            }
        }
        foreach ($lines as $line) {
            if (preg_match('/^Date Of Birth\s+(\d{2}\/\d{2}\/\d{4})$/i', $line, $m)) {
                return $m[1];
            }
            if (preg_match('/^Date Of Birth\s+(.+)$/i', $line, $m)) {
                $value = trim($m[1]);
                $date = $this->extractDateFromLine($value);
                if ($date !== null) {
                    return $date;
                }
            }
        }
        return 'N/A';
    }

    private function extractGender(array $lines): string
    {
        foreach ($lines as $line) {
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+(Male|Female|Other)/i', $line, $m)) {
                return $m[2];
            }
        }
        foreach ($lines as $line) {
            if (preg_match('/^Gender\s+(Male|Female|Other)$/i', $line, $m)) {
                return $m[1];
            }
        }
        return 'N/A';
    }

    private function extractPan(array $lines): string
    {
        foreach ($lines as $line) {
            if (preg_match('/\b[A-Z]{5}\d{4}[A-Z]\b/', $line, $m)) {
                return $m[0];
            }
        }
        return 'N/A';
    }

    private function extractIdentifications(array $lines): array
    {
        $ids = [];
        $inSection = false;
        $current = null;
        $sequence = 1;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if ($line === 'IDENTIFICATION DETAILS') {
                $inSection = true;
                continue;
            }
            if ($inSection && $line === 'ADDRESS DETAILS') {
                break;
            }
            if (!$inSection) {
                continue;
            }
            if ($this->isJunkLine($line) || $this->isPageNumberLine($line)) {
                continue;
            }

            if (preg_match('/^Identification Type\s+(.+)$/', $line, $m)) {
                if ($current && $current['IdentificationType'] !== 'N/A') {
                    $current['Sequence'] = (string) $sequence++;
                    $ids[] = $current;
                }
                $current = [
                    'Sequence' => '',
                    'IdentificationType' => $this->normalizeValue($m[1]),
                    'IdNumber' => 'N/A',
                    'IssueDate' => 'N/A',
                    'ExpiryDate' => 'N/A',
                ];
                continue;
            }
            if (preg_match('/^ID Number\s+(.+)$/', $line, $m) && $current) {
                $current['IdNumber'] = $this->normalizeValue($m[1]);
                continue;
            }
            if (preg_match('/^Issue Date\s+(.+)$/', $line, $m) && $current) {
                $current['IssueDate'] = $this->normalizeValue($m[1]);
                continue;
            }
            if (preg_match('/^Expiry Date\s+(.+)$/', $line, $m) && $current) {
                $current['ExpiryDate'] = $this->normalizeValue($m[1]);
                continue;
            }

            if ($line === 'Identification Type') {
                if ($current && $current['IdentificationType'] !== 'N/A') {
                    $current['Sequence'] = (string) $sequence++;
                    $ids[] = $current;
                }
                $current = [
                    'Sequence' => '',
                    'IdentificationType' => 'N/A',
                    'IdNumber' => 'N/A',
                    'IssueDate' => 'N/A',
                    'ExpiryDate' => 'N/A',
                ];
                $value = $this->nextIdentificationValue($lines, $i);
                if ($value !== null) {
                    $current['IdentificationType'] = $this->normalizeValue($value);
                }
                continue;
            }
            if ($line === 'ID Number' && $current) {
                $value = $this->nextIdentificationValue($lines, $i);
                if ($value !== null) {
                    $current['IdNumber'] = $this->normalizeValue($value);
                }
                continue;
            }
            if ($line === 'Issue Date' && $current) {
                $value = $this->nextIdentificationValue($lines, $i);
                if ($value !== null) {
                    $current['IssueDate'] = $this->normalizeValue($value);
                }
                continue;
            }
            if ($line === 'Expiry Date' && $current) {
                $value = $this->nextIdentificationValue($lines, $i);
                if ($value !== null) {
                    $current['ExpiryDate'] = $this->normalizeValue($value);
                }
                continue;
            }
        }

        if ($current && $current['IdentificationType'] !== 'N/A') {
            $current['Sequence'] = (string) $sequence++;
            $ids[] = $current;
        }

        return $ids;
    }

    private function nextIdentificationValue(array $lines, int $index): ?string
    {
        $labels = ['Identification Type', 'ID Number', 'Issue Date', 'Expiry Date', 'IDENTIFICATION DETAILS', 'ADDRESS DETAILS'];
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate) || $this->isPageNumberLine($candidate)) {
                continue;
            }
            if (in_array($candidate, $labels, true)) {
                return null;
            }
            return $candidate;
        }
        return null;
    }

    private function extractAddresses(array $lines): array
    {
        $addresses = [];
        $inAddressSection = false;
        $current = null;
        $sequence = 1;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if ($line === 'ADDRESS DETAILS') {
                $inAddressSection = true;
                continue;
            }
            if ($inAddressSection && in_array($line, ['CONTACT DETAILS', 'EMAIL DETAILS', 'EMPLOYMENT DETAILS', 'ALL ACCOUNTS', 'OPEN ACCOUNTS'], true)) {
                break;
            }
            if (!$inAddressSection) {
                continue;
            }

            if ($line === 'Address') {
                if ($current) {
                    $this->moveTrailingAddressCategory($current);
                    $current['Sequence'] = (string) $sequence++;
                    $addresses[] = $current;
                }
                $current = [
                    'Sequence' => '',
                    'Address' => '',
                    'Type' => 'N/A',
                    'ResidenceCode' => 'N/A',
                    'DateReported' => 'N/A',
                ];
                continue;
            }

            if ($current) {
                if ($line === '--- PAGE BREAK ---' || str_contains($line, 'Score Report') || str_contains($line, 'Cibil Dashboard')) {
                    continue;
                }
                if (str_contains($line, 'myscore.cibil.com') || str_contains($line, '/CreditView/')) {
                    continue;
                }
                if ($this->isPageNumberLine($line)) {
                    continue;
                }
                if ($line === 'Category') {
                    $next = $this->nextAddressValue($lines, $i);
                    if ($next !== null) {
                        $current['Type'] = $next;
                        $i = $this->findLineIndex($lines, $i + 1, $next);
                        continue;
                    }
                }
                if ($line === 'Residence Code') {
                    $next = $this->nextAddressValue($lines, $i);
                    if ($next !== null) {
                        $current['ResidenceCode'] = $next;
                        $i = $this->findLineIndex($lines, $i + 1, $next);
                        continue;
                    }
                }
                if ($line === 'Date Reported') {
                    $next = $this->nextAddressValue($lines, $i, true);
                    if ($next !== null) {
                        $current['DateReported'] = $next;
                        $i = $this->findLineIndex($lines, $i + 1, $next);
                        continue;
                    }
                }
                $inline = $line;
                if (preg_match('/Category\s+(.+?)(?=Residence Code|Date Reported|$)/i', $inline, $m)) {
                    $value = trim($m[1]);
                    if (!$this->isPlaceholderValue($value) && !$this->isPageNumberLine($value)) {
                        $current['Type'] = $value;
                    }
                    $inline = str_replace($m[0], '', $inline);
                }
                if (preg_match('/Residence Code\s+(.+?)(?=Date Reported|$)/i', $inline, $m)) {
                    $value = trim($m[1]);
                    if (!$this->isPlaceholderValue($value) && !$this->isPageNumberLine($value)) {
                        $current['ResidenceCode'] = $value;
                    }
                    $inline = str_replace($m[0], '', $inline);
                }
                if (preg_match('/Date Reported\s+(\d{2}\/\d{2}\/\d{4})/i', $inline, $m)) {
                    $current['DateReported'] = $m[1];
                    $inline = str_replace($m[0], '', $inline);
                }
                if (!in_array($line, ['Category', 'Residence Code', 'Date Reported'], true)) {
                    $addressLine = $this->normalizeAddressLine($inline);
                    if ($addressLine !== '') {
                        $current['Address'] = trim($current['Address'] . ' ' . $addressLine);
                    }
                }
            }
        }

        if ($current) {
            $this->moveTrailingAddressCategory($current);
            $current['Sequence'] = (string) $sequence++;
            $addresses[] = $current;
        }

        return $addresses;
    }

    private function extractTelephones(array $lines): array
    {
        $telephones = [];
        $inContact = false;
        $sequence = 1;
        $currentType = null;
        $expectNumber = false;
        $allowedTypes = [
            'mobile phone',
            'mobile phone (e)',
            'mobile',
            'not classified',
            'not classified (e)',
            'office phone',
            'office phone (e)',
            'residence phone',
            'home phone',
            'telephone',
        ];

        foreach ($lines as $index => $line) {
            if ($line === 'CONTACT DETAILS') {
                $inContact = true;
                continue;
            }
            if ($inContact && $line === 'EMAIL DETAILS') {
                break;
            }
            if (!$inContact) {
                continue;
            }
            if ($this->isJunkLine($line) || $this->isPageNumberLine($line)) {
                continue;
            }

            if ($line === 'Telephone Number Type') {
                $currentType = $lines[$index + 1] ?? null;
                while ($currentType !== null) {
                    if ($this->isJunkLine($currentType) && isset($lines[$index + 2])) {
                        $index++;
                        $currentType = $lines[$index + 1] ?? null;
                        continue;
                    }
                    $candidateType = strtolower(trim($currentType));
                    if (in_array($candidateType, $allowedTypes, true)) {
                        break;
                    }
                    if (isset($lines[$index + 2])) {
                        $index++;
                        $currentType = $lines[$index + 1] ?? null;
                        continue;
                    }
                    $currentType = null;
                    break;
                }
                continue;
            }
            if ($line === 'Telephone Number') {
                $expectNumber = true;
                continue;
            }
            if (preg_match('/^Telephone Number Type\s+(.+)$/i', $line, $m)) {
                $candidateType = strtolower(trim($m[1]));
                if (in_array($candidateType, $allowedTypes, true)) {
                    $currentType = trim($m[1]);
                } else {
                    $currentType = null;
                }
                continue;
            }
            if (preg_match('/^Telephone Number\s+(?!Type\b)(.+)$/i', $line, $m)) {
                $number = trim($m[1]);
                if ($currentType && $number !== '') {
                    $telephones[] = [
                        'Sequence' => (string) $sequence++,
                        'Number' => $number,
                        'Type' => $currentType,
                        'Extension' => 'N/A',
                    ];
                    $expectNumber = false;
                    continue;
                }
                $expectNumber = true;
                continue;
            }
            if (preg_match('/^Telephone Extension\b/i', $line)) {
                $expectNumber = false;
                continue;
            }
            if ($expectNumber && $currentType) {
                if ($this->isJunkLine($line)) {
                    continue;
                }
                if (!preg_match('/^\d{6,}$/', preg_replace('/\D+/', '', $line))) {
                    continue;
                }
                $telephones[] = [
                    'Sequence' => (string) $sequence++,
                    'Number' => $line,
                    'Type' => $currentType,
                    'Extension' => 'N/A',
                ];
                $expectNumber = false;
            }
        }

        return $telephones;
    }

    private function extractEmails(array $lines): array
    {
        $emails = [];
        $inEmail = false;
        $sequence = 1;
        foreach ($lines as $index => $line) {
            if ($line === 'EMAIL DETAILS') {
                $inEmail = true;
                continue;
            }
            if ($inEmail && $line === 'EMPLOYMENT DETAILS') {
                break;
            }
            if (!$inEmail) {
                continue;
            }
            if ($line === 'Email ID' && isset($lines[$index + 1])) {
                $value = $this->nextEmailValue($lines, $index);
                if ($value === null) {
                    continue;
                }
                $emails[] = [
                    'Sequence' => (string) $sequence++,
                    'EmailAddress' => $value,
                ];
                for ($i = $index + 2; $i < count($lines); $i++) {
                    $candidate = $lines[$i];
                    if ($candidate === 'EMPLOYMENT DETAILS' || $candidate === 'Email ID') {
                        break;
                    }
                    if ($this->isJunkLine($candidate) || $this->isPageNumberLine($candidate)) {
                        continue;
                    }
                    if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = [
                            'Sequence' => (string) $sequence++,
                            'EmailAddress' => $candidate,
                        ];
                        continue;
                    }
                    break;
                }
            }
        }
        return $emails;
    }

    private function extractEmployment(array $lines): array
    {
        $employment = [
            'AccountType' => 'N/A',
            'DateReported' => 'N/A',
            'Occupation' => 'N/A',
            'Income' => 'N/A',
            'MonthlyAnnualIncomeIndicator' => 'N/A',
            'NetGrossIncomeIndicator' => 'N/A',
        ];

        $inEmployment = false;
        for ($i = 0; $i < count($lines); $i++) {
            if ($this->matchLabel($lines[$i], ['EMPLOYMENT DETAILS']) === 'EMPLOYMENT DETAILS') {
                $inEmployment = true;
                continue;
            }
            if ($inEmployment && $this->matchLabel($lines[$i], ['ALL ACCOUNTS']) === 'ALL ACCOUNTS') {
                break;
            }
            if (!$inEmployment) {
                continue;
            }
            $rawLine = $lines[$i];
            $line = $this->matchLabel($rawLine, [
                'Date Reported',
                'Account Type',
                'Occupation',
                'Income',
                'Monthly / Annual Income Indicator',
                'Net / Gross Income Indicator',
            ]) ?: $lines[$i];

            if (str_contains($rawLine, 'Account Type') && str_contains($rawLine, 'Date Reported')) {
                $valueLine = $this->nextEmploymentValue($lines, $i);
                if ($valueLine) {
                    $date = $this->extractDateFromLine($valueLine);
                    if ($date) {
                        $employment['DateReported'] = $date;
                        $accountType = trim(str_replace($date, '', $valueLine));
                        if ($accountType !== '') {
                            $employment['AccountType'] = $this->normalizeValue($accountType);
                        }
                    }
                }
                continue;
            }
            if (str_contains($rawLine, 'Occupation') && str_contains($rawLine, 'Income')) {
                $valueLine = $this->nextEmploymentValue($lines, $i);
                if ($valueLine) {
                    $parts = preg_split('/\s+/', trim($valueLine)) ?: [];
                    if (count($parts) >= 2) {
                        $income = array_pop($parts);
                        $occupation = trim(implode(' ', $parts));
                        $employment['Occupation'] = $this->normalizeValue($occupation);
                        $employment['Income'] = $this->normalizeValue($income);
                    } else {
                        $employment['Occupation'] = $this->normalizeValue($valueLine);
                    }
                }
                continue;
            }
            if (str_contains($line, 'Monthly / Annual Income Indicator') && str_contains($line, 'Net / Gross Income Indicator')) {
                $valueLine = $this->nextEmploymentValue($lines, $i);
                if ($valueLine) {
                    $parts = preg_split('/\s+/', trim($valueLine)) ?: [];
                    if (count($parts) >= 2) {
                        $employment['MonthlyAnnualIncomeIndicator'] = $this->normalizeValue($parts[0]);
                        $employment['NetGrossIncomeIndicator'] = $this->normalizeValue($parts[1]);
                    } else {
                        $employment['MonthlyAnnualIncomeIndicator'] = $this->normalizeValue($valueLine);
                    }
                }
                continue;
            }
            if ($line === 'Date Reported') {
                $value = $this->nextEmploymentValue($lines, $i);
                if ($value && preg_match('/\d{2}\/\d{2}\/\d{4}/', $value)) {
                    $employment['DateReported'] = $value;
                }
                continue;
            }
            if ($line === 'Account Type') {
                $value = $this->nextEmploymentValue($lines, $i);
                if ($value) {
                    if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $value) && isset($lines[$i + 2])) {
                        $employment['DateReported'] = $value;
                        $employment['AccountType'] = $this->normalizeValue($lines[$i + 2]);
                    } else {
                        $employment['AccountType'] = $this->normalizeValue($value);
                    }
                }
                continue;
            }
            if ($line === 'Occupation') {
                $value = $this->nextEmploymentValue($lines, $i);
                if ($value) {
                    $employment['Occupation'] = $this->normalizeValue($value);
                }
                continue;
            }
            if ($line === 'Income') {
                $value = $this->nextEmploymentValue($lines, $i);
                if ($value) {
                    $employment['Income'] = $this->normalizeValue($value);
                }
                continue;
            }
            if ($line === 'Monthly / Annual Income Indicator') {
                $value = $this->nextEmploymentValue($lines, $i);
                if ($value) {
                    [$monthlyAnnual, $netGross] = $this->parseEmploymentIndicators($value);
                    $employment['MonthlyAnnualIncomeIndicator'] = $this->normalizeValue($monthlyAnnual);
                    if ($netGross !== null) {
                        $employment['NetGrossIncomeIndicator'] = $this->normalizeValue($netGross);
                    }
                }
                continue;
            }
            if ($line === 'Net / Gross Income Indicator') {
                $value = $this->nextEmploymentValue($lines, $i);
                if ($value) {
                    [$monthlyAnnual, $netGross] = $this->parseEmploymentIndicators($value);
                    if ($monthlyAnnual !== null) {
                        $employment['MonthlyAnnualIncomeIndicator'] = $this->normalizeValue($monthlyAnnual);
                    }
                    $employment['NetGrossIncomeIndicator'] = $this->normalizeValue($netGross ?? $value);
                }
                continue;
            }
        }

        return $employment;
    }

    private function nextEmailValue(array $lines, int $index): ?string
    {
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate) || $this->isPageNumberLine($candidate)) {
                continue;
            }
            if ($candidate === 'Email ID' || $candidate === 'EMPLOYMENT DETAILS') {
                return null;
            }
            return $candidate;
        }
        return null;
    }

    private function extractAccounts(array $lines): array
    {
        $accounts = [];
        $current = null;
        $sequence = 1;
        $inAccounts = false;
        $inPaymentStatus = false;
        $paymentStatusDpd = null;
        $paymentStatusExplicit = false;
        $labels = [
            'Member Name',
            'Account Type',
            'Account Number',
            'Ownership',
            'Credit Limit',
            'Cash Limit',
            'High Credit',
            'Sanctioned Amount',
            'Current Balance',
            'Amount Overdue',
            'Rate of Interest',
            'Repayment Tenure',
            'EMI Amount',
            'Payment Frequency',
            'Actual Payment Amount',
            'Date Opened / Disbursed',
            'Date Closed',
            'Date of Last Payment',
            'Date Reported And Certified',
            'Value of Collateral',
            'Type of Collateral',
            'Suit - Filed / Willful Default',
            'Suit - Filed / Wilful Default',
            'Credit Facility Status',
            'Written-off Amount (Total)',
            'Written-off Amount (Principal)',
            'Settlement Amount',
            'Payment Start Date',
            'Payment End Date',
            'Payment History',
            'ENQUIRY DETAILS',
            'PAYMENT STATUS',
        ];

        for ($i = 0; $i < count($lines); $i++) {
            $rawLine = $lines[$i];
            $line = $rawLine;
            if ($this->matchLabel($line, ['ALL ACCOUNTS', 'OPEN ACCOUNTS', 'CLOSED ACCOUNTS']) !== null) {
                $inAccounts = true;
                continue;
            }
            if ($inAccounts && stripos($line, 'ENQUIRY') !== false) {
                break;
            }

            if (!$inAccounts) {
                continue;
            }
            $label = $this->matchAccountLabel($line, $labels) ?: $line;

            if ($label === 'Member Name' && isset($lines[$i + 1])) {
                if ($current && $this->accountHasData($current)) {
                    $current['Sequence'] = (string) $sequence++;
                    $accounts[] = $current;
                    $current = null;
                }
                $current = $current ?: $this->blankAccount();
                $current['MemberName'] = $lines[$i + 1];
                $inPaymentStatus = false;
                $paymentStatusDpd = null;
                $paymentStatusExplicit = false;
                continue;
            }
            if ($label === 'Account Type') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Account Type');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['AccountType'] = $this->normalizeValue($value);
                }
                continue;
            }
            if ($label === 'Account Number') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Account Number');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['AccountNumber'] = $this->normalizeValue($value);
                }
                continue;
            }
            if ($label === 'Ownership') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Ownership');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['OwnershipType'] = $this->normalizeValue($value);
                }
                continue;
            }

            if ($label === 'Credit Limit') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Credit Limit');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['CreditLimit'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Cash Limit') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Cash Limit');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['CashLimit'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'High Credit') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'High Credit');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['HighCredit'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Sanctioned Amount') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Sanctioned Amount');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['SanctionedAmount'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Current Balance') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Current Balance');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['CurrentBalance'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Amount Overdue') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Amount Overdue');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['AmountOverdue'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Rate of Interest') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Rate of Interest');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['RateOfInterest'] = $this->normalizeValue($value);
                }
            }
            if ($label === 'Repayment Tenure') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Repayment Tenure');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['RepaymentTenure'] = $this->normalizeValue($value);
                }
            }
            if ($label === 'EMI Amount') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'EMI Amount');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['EmiAmount'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Payment Frequency') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Payment Frequency');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['PaymentFrequency'] = $this->normalizeValue($value);
                }
            }
            if ($label === 'Actual Payment Amount') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Actual Payment Amount');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['ActualPaymentAmount'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Date Opened / Disbursed') {
                $current = $current ?: $this->blankAccount();
                $value = $this->inlineDateForLabel($rawLine, 'Date Opened / Disbursed') ?? $this->nextDateValue($lines, $i);
                if ($value !== null) {
                    $current['DateOpened'] = $value;
                }
            }
            if ($label === 'Date Closed') {
                $current = $current ?: $this->blankAccount();
                $value = $this->inlineDateForLabel($rawLine, 'Date Closed') ?? $this->nextDateValue($lines, $i);
                if ($value !== null) {
                    $current['DateClosed'] = $value;
                }
            }
            if ($label === 'Date of Last Payment') {
                $current = $current ?: $this->blankAccount();
                $value = $this->inlineDateForLabel($rawLine, 'Date of Last Payment') ?? $this->nextDateValue($lines, $i);
                if ($value !== null) {
                    $current['LastPaymentDate'] = $value;
                }
            }
            if ($label === 'Date Reported And Certified') {
                $current = $current ?: $this->blankAccount();
                $value = $this->inlineDateForLabel($rawLine, 'Date Reported And Certified') ?? $this->nextDateValue($lines, $i);
                if ($value !== null) {
                    $current['DateReportedAndCertified'] = $value;
                }
            }
            if ($label === 'Value of Collateral') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Value of Collateral');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['ValueOfCollateral'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Type of Collateral') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Type of Collateral');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['TypeOfCollateral'] = $this->normalizeValue($value);
                }
            }
            if ($label === 'Suit - Filed / Willful Default') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Suit - Filed / Willful Default');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['SuitFiledWillfulDefault'] = $this->normalizeValue($value);
                }
            }
            if ($label === 'Credit Facility Status') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Credit Facility Status');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['CreditFacilityStatus'] = $this->normalizeValue($value);
                }
            }
            if ($label === 'Written-off Amount (Total)') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Written-off Amount (Total)');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['WrittenOffAmountTotal'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Written-off Amount (Principal)') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Written-off Amount (Principal)');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['WrittenOffAmountPrincipal'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Settlement Amount') {
                $current = $current ?: $this->blankAccount();
                $inline = $this->inlineValueForLabel($rawLine, 'Settlement Amount');
                $value = $this->sanitizeAccountValue($inline ?? $this->nextAccountValue($lines, $i), $labels);
                if ($value !== null) {
                    $current['SettlementAmount'] = $this->cleanAmountOrNA($value);
                }
            }
            if ($label === 'Payment Start Date') {
                $current = $current ?: $this->blankAccount();
                $value = $this->inlineDateForLabel($rawLine, 'Payment Start Date') ?? $this->nextDateValue($lines, $i);
                if ($value !== null) {
                    $current['PaymentStartDate'] = $value;
                }
            }
            if ($label === 'Payment End Date') {
                $current = $current ?: $this->blankAccount();
                $value = $this->inlineDateForLabel($rawLine, 'Payment End Date') ?? $this->nextDateValue($lines, $i);
                if ($value !== null) {
                    $current['PaymentEndDate'] = $value;
                }
            }
            if ($label === 'PAYMENT STATUS') {
                $inPaymentStatus = true;
                $paymentStatusExplicit = false;
                if (preg_match('/PAYMENT STATUS\s+(\d{1,3})\b(?!\/\d{2})/i', $rawLine, $m)) {
                    $paymentStatusDpd = $m[1];
                    $paymentStatusExplicit = true;
                } else {
                    $paymentStatusDpd = $this->nextPaymentStatusDpd($lines, $i);
                    if ($paymentStatusDpd !== null) {
                        $paymentStatusExplicit = true;
                    }
                }
                continue;
            }
            if ($label === 'Payment History' && isset($lines[$i + 1])) {
                $current = $current ?: $this->blankAccount();
                $j = $i + 1;
                while ($j < count($lines)) {
                    $historyLine = $lines[$j];
                    if ($inAccounts && stripos($historyLine, 'ENQUIRY') !== false) {
                        $j--;
                        break;
                    }
                    if ($this->matchLabel($historyLine, $labels) !== null) {
                        $j--;
                        break;
                    }
                    if ($this->isJunkLine($historyLine) || str_starts_with($historyLine, 'Payment History') || $this->isHistoryLegendLine($historyLine)) {
                        $j++;
                        continue;
                    }

                    $entries = $this->parsePaymentHistoryEntries($historyLine);
                    if (count($entries) > 0) {
                        $allNull = true;
                        foreach ($entries as $entry) {
                            if ($entry['DaysPastDue'] !== null) {
                                $allNull = false;
                                break;
                            }
                        }
                        if ($allNull) {
                            $nextLine = $lines[$j + 1] ?? null;
                            if ($nextLine !== null && !$this->isJunkLine($nextLine) && !$this->isHistoryLegendLine($nextLine)) {
                                $dpdTokens = $this->extractHistoryDpdTokens($nextLine);
                                if (count($dpdTokens) >= count($entries)) {
                                    foreach ($entries as $idx => $entry) {
                                        $entries[$idx]['DaysPastDue'] = $dpdTokens[$idx] ?? null;
                                    }
                                    $j++;
                                }
                            }
                        }
                        foreach ($entries as $entry) {
                            if ($entry['DaysPastDue'] === null) {
                                [$nextValue, $nextIndex] = $this->nextHistoryValue($lines, $j);
                                if ($nextValue !== null) {
                                    $entry['DaysPastDue'] = $nextValue;
                                    $j = $nextIndex;
                                }
                            }
                            if ($entry['DaysPastDue'] === null) {
                                if ($paymentStatusDpd !== null && $paymentStatusExplicit && $paymentStatusDpd !== '0') {
                                    $entry['DaysPastDue'] = $paymentStatusDpd;
                                    $paymentStatusDpd = null;
                                } else {
                                    $entry['DaysPastDue'] = 'N/A';
                                }
                            }
                            $current['PaymentHistory'][] = $entry;
                        }
                        $j++;
                        continue;
                    }

                    $j++;
                }
                $i = $j;
            }

            if ($inPaymentStatus && $current) {
                if ($this->isJunkLine($line) || $this->isHistoryLegendLine($line) || $this->isPlaceholderValue($line)) {
                    continue;
                }
                $entries = $this->parsePaymentHistoryEntries($line);
                foreach ($entries as $entry) {
                    if ($entry['DaysPastDue'] === null) {
                        if ($paymentStatusDpd !== null && $paymentStatusExplicit && $paymentStatusDpd !== '0') {
                            $entry['DaysPastDue'] = $paymentStatusDpd;
                            $paymentStatusDpd = null;
                        } else {
                            $entry['DaysPastDue'] = 'N/A';
                        }
                    }
                    $current['PaymentHistory'][] = $entry;
                }
            }
        }

        if ($current && $this->accountHasData($current)) {
            $current['Sequence'] = (string) $sequence++;
            $accounts[] = $current;
        }

        return $accounts;
    }

    private function blankAccount(): array
    {
        return [
            'Sequence' => '',
            'MemberName' => 'N/A',
            'AccountType' => 'N/A',
            'AccountNumber' => 'N/A',
            'OwnershipType' => 'N/A',
            'DateOpened' => 'N/A',
            'DateClosed' => 'N/A',
            'LastPaymentDate' => 'N/A',
            'DateReportedAndCertified' => 'N/A',
            'CreditLimit' => 'N/A',
            'CashLimit' => 'N/A',
            'HighCredit' => 'N/A',
            'SanctionedAmount' => 'N/A',
            'CurrentBalance' => 'N/A',
            'AmountOverdue' => 'N/A',
            'EmiAmount' => 'N/A',
            'ActualPaymentAmount' => 'N/A',
            'RateOfInterest' => 'N/A',
            'PaymentFrequency' => 'N/A',
            'PaymentStartDate' => 'N/A',
            'PaymentEndDate' => 'N/A',
            'RepaymentTenure' => 'N/A',
            'ValueOfCollateral' => 'N/A',
            'TypeOfCollateral' => 'N/A',
            'CreditFacilityStatus' => 'N/A',
            'SuitFiledWillfulDefault' => 'N/A',
            'WrittenOffAmountTotal' => 'N/A',
            'WrittenOffAmountPrincipal' => 'N/A',
            'SettlementAmount' => 'N/A',
            'PaymentHistory' => [],
        ];
    }

    private function cleanNumber(string $value): string
    {
        return trim(str_replace(['₹', ',', '=', ' '], '', $value));
    }

    private function cleanAmountOrNA(string $value): string
    {
        $cleaned = $this->cleanNumber($value);
        return $this->normalizeValue($cleaned);
    }

    private function normalizeValue(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '-' || $value === '--' || strtoupper($value) === 'NA') {
            return 'N/A';
        }
        if (preg_match('/^(-\s*){2,}$/', $value)) {
            return 'N/A';
        }
        return $value;
    }

    private function extractEnquiries(array $lines): array
    {
        $enquiries = [];
        $sequence = 1;
        $inSection = false;
        $current = [
            'MemberName' => null,
            'DateOfEnquiry' => null,
            'EnquiryPurpose' => null,
        ];
        $labels = ['Member Name', 'Date Of Enquiry', 'Enquiry Purpose'];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if ($this->matchLabel($line, ['ENQUIRY DETAILS']) === 'ENQUIRY DETAILS') {
                $inSection = true;
                continue;
            }
            if ($inSection && (str_starts_with($line, 'End of report') || str_starts_with($line, 'Disclaimer:') || str_starts_with($line, 'COPYRIGHT'))) {
                break;
            }
            if (!$inSection) {
                continue;
            }

            $label = $this->matchLabel($line, $labels) ?: $line;
            if ($label === 'Member Name' && isset($lines[$i + 1])) {
                $inline = $this->inlineValueForLabel($line, 'Member Name');
                if ($inline !== null) {
                    if ($this->enquiryHasData($current)) {
                        $current['Sequence'] = (string) $sequence++;
                        $enquiries[] = $current;
                    }
                    $current = [
                        'MemberName' => $inline,
                        'DateOfEnquiry' => null,
                        'EnquiryPurpose' => null,
                    ];
                    continue;
                }
                if ($this->enquiryHasData($current)) {
                    $current['Sequence'] = (string) $sequence++;
                    $enquiries[] = $current;
                }
                $current = [
                    'MemberName' => null,
                    'DateOfEnquiry' => null,
                    'EnquiryPurpose' => null,
                ];
                $next = $this->nextEnquiryMemberValue($lines, $i);
                if ($next !== null) {
                    $current['MemberName'] = $next;
                }
                continue;
            }
            if ($label === 'Date Of Enquiry' && isset($lines[$i + 1])) {
                $inline = $this->inlineValueForLabel($line, 'Date Of Enquiry');
                if ($inline !== null) {
                    $current['DateOfEnquiry'] = $inline;
                } else {
                    $value = $this->nextEnquiryDateValue($lines, $i);
                    if ($value !== null) {
                        $current['DateOfEnquiry'] = $value;
                    }
                }
                continue;
            }
            if ($label === 'Enquiry Purpose' && isset($lines[$i + 1])) {
                $inline = $this->inlineValueForLabel($line, 'Enquiry Purpose');
                if ($inline !== null) {
                    $current['EnquiryPurpose'] = $inline;
                    continue;
                }
                $value = $this->nextEnquiryValue($lines, $i);
                if ($value !== null) {
                    $current['EnquiryPurpose'] = $value;
                }
                continue;
            }

            if ($this->isJunkLine($line) || $this->isPageNumberLine($line)) {
                continue;
            }

            if (!in_array($label, $labels, true) && isset($lines[$i + 1])) {
                $nextLabel = $this->matchLabel($lines[$i + 1], $labels);
                if ($nextLabel === 'Date Of Enquiry') {
                    if ($this->enquiryHasData($current)) {
                        $current['Sequence'] = (string) $sequence++;
                        $enquiries[] = $current;
                        $current = [
                            'MemberName' => null,
                            'DateOfEnquiry' => null,
                            'EnquiryPurpose' => null,
                        ];
                    }
                    $current['MemberName'] = $line;
                }
            }
        }

        if ($this->enquiryHasData($current)) {
            $current['Sequence'] = (string) $sequence++;
            $enquiries[] = $current;
        }

        return $enquiries;
    }

    private function nextEnquiryValue(array $lines, int $index): ?string
    {
        $labels = ['Member Name', 'Date Of Enquiry', 'Enquiry Purpose', 'ENQUIRY DETAILS'];
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate)) {
                continue;
            }
            if ($this->isPageNumberLine($candidate)) {
                continue;
            }
            if ($this->matchLabel($candidate, $labels) !== null) {
                return null;
            }
            if ($this->isPlaceholderValue($candidate)) {
                continue;
            }
            return $candidate;
        }
        return null;
    }

    private function nextEnquiryMemberValue(array $lines, int $index): ?string
    {
        $labels = ['Member Name', 'Date Of Enquiry', 'Enquiry Purpose', 'ENQUIRY DETAILS'];
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate) || $this->isPageNumberLine($candidate)) {
                continue;
            }
            if ($this->matchLabel($candidate, $labels) !== null) {
                return null;
            }
            if ($this->isPlaceholderValue($candidate)) {
                continue;
            }
            return $candidate;
        }
        return null;
    }

    private function nextEnquiryDateValue(array $lines, int $index): ?string
    {
        $labels = ['Member Name', 'Date Of Enquiry', 'Enquiry Purpose', 'ENQUIRY DETAILS'];
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate) || $this->isPageNumberLine($candidate)) {
                continue;
            }
            if ($this->matchLabel($candidate, $labels) !== null) {
                return null;
            }
            if ($this->isPlaceholderValue($candidate)) {
                continue;
            }
            $date = $this->extractDateFromLine($candidate);
            if ($date !== null) {
                return $date;
            }
        }
        return null;
    }

    private function isAccountLabel(string $line): bool
    {
        return in_array($line, [
            'Member Name',
            'Account Type',
            'Account Number',
            'Ownership',
            'Credit Limit',
            'Cash Limit',
            'High Credit',
            'Sanctioned Amount',
            'Current Balance',
            'Amount Overdue',
            'Rate of Interest',
            'Repayment Tenure',
            'EMI Amount',
            'Payment Frequency',
            'Actual Payment Amount',
            'Date Opened / Disbursed',
            'Date Closed',
            'Date of Last Payment',
            'Date Reported And Certified',
            'Value of Collateral',
            'Type of Collateral',
            'Suit - Filed / Willful Default',
            'Credit Facility Status',
            'Written-off Amount (Total)',
            'Written-off Amount (Principal)',
            'Settlement Amount',
            'Payment Start Date',
            'Payment End Date',
            'Payment History',
            'ENQUIRY DETAILS',
        ], true);
    }

    private function accountHasData(array $account): bool
    {
        return $account['MemberName'] !== 'N/A'
            || $account['AccountNumber'] !== 'N/A'
            || $account['AccountType'] !== 'N/A';
    }

    private function enquiryHasData(array $enquiry): bool
    {
        $member = trim((string) ($enquiry['MemberName'] ?? ''));
        $date = trim((string) ($enquiry['DateOfEnquiry'] ?? ''));
        $purpose = trim((string) ($enquiry['EnquiryPurpose'] ?? ''));
        if ($member === '' || strtoupper($member) === 'N/A') {
            return false;
        }
        return ($date !== '' && strtoupper($date) !== 'N/A')
            || ($purpose !== '' && strtoupper($purpose) !== 'N/A');
    }

    private function splitByMarkers(string $line, array $markers): array
    {
        foreach ($markers as $marker) {
            $pattern = '/\b' . preg_quote($marker, '/') . '\b/i';
            $line = preg_replace($pattern, "\n{$marker}\n", $line);
        }
        return preg_split('/\R/', $line) ?: [$line];
    }

    private function extractAdditionalInformation(array $lines): array
    {
        $additional = [];
        $sequence = 1;
        $inNh = false;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (str_starts_with($line, 'Please note in some cases you might be displayed a CIBIL Score')) {
                $inNh = true;
                continue;
            }
            if ($inNh && preg_match('/^\d+\.\s+(.*)$/', $line, $m)) {
                $additional[] = [
                    'Sequence' => (string) $sequence++,
                    'Label' => "CIBIL Score 'NH' Reason",
                    'Value' => $m[1],
                ];
                continue;
            }
            if ($inNh && $line === 'PERSONAL DETAILS') {
                $inNh = false;
            }

            if (str_starts_with($line, 'Disclaimer:') || str_starts_with($line, 'All information contained in this credit report')) {
                $disclaimer = [$line];
                for ($j = $i + 1; $j < count($lines); $j++) {
                    $next = $lines[$j];
                    if ($next === '--- PAGE BREAK ---') {
                        continue;
                    }
                    $disclaimer[] = $next;
                }
                $additional[] = [
                    'Sequence' => (string) $sequence++,
                    'Label' => 'Report Disclaimer',
                    'Value' => trim(implode(' ', $disclaimer)),
                ];
                break;
            }
        }

        return $additional;
    }

    private function matchLabel(string $line, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (str_starts_with($line, $label)) {
                return $label;
            }
        }
        return null;
    }

    private function isJunkLine(string $line): bool
    {
        return $line === '--- PAGE BREAK ---'
            || $this->isPageNumberLine($line)
            || str_contains($line, 'Score Report')
            || str_contains($line, 'Cibil Dashboard')
            || str_contains($line, 'myscore.cibil.com')
            || str_contains($line, '/CreditView/');
    }

    private function isHistoryLegendLine(string $line): bool
    {
        return str_contains($line, 'STD:') || str_contains($line, 'DBT:')
            || str_contains($line, 'SMA:') || str_contains($line, 'LSS:')
            || str_contains($line, 'SUB:') || str_contains($line, '###')
            || str_contains($line, 'Not Reported');
    }

    private function parsePaymentHistoryLine(string $line): ?array
    {
        $line = $this->normalizeHistoryLine($line);
        if (preg_match('/^([A-Za-z]{3})\s+(\d{4})(?:\s+([A-Za-z]{3}|XXX|STD|DBT|SMA|SUB|LSS|\d{1,3}))?$/', $line, $m)) {
            return [
                'Month' => $m[1],
                'Year' => $m[2],
                'DaysPastDue' => $m[3] ?? null,
            ];
        }
        if (preg_match('/^([A-Za-z]{3})\s+(\d{4}).*?(\d{1,3}|STD|XXX|DBT|SMA|SUB|LSS)\b/', $line, $m)) {
            return [
                'Month' => $m[1],
                'Year' => $m[2],
                'DaysPastDue' => $m[3],
            ];
        }
        return null;
    }

    private function parsePaymentHistoryEntries(string $line): array
    {
        $line = $this->normalizeHistoryLine($line);
        $months = $this->extractHistoryMonthTokens($line);
        if (count($months) === 0) {
            $single = $this->parsePaymentHistoryLine($line);
            return $single ? [$single] : [];
        }

        $dpdTokens = $this->extractHistoryDpdTokens($line);
        $entries = [];
        foreach ($months as $idx => $monthYear) {
            [$month, $year] = $monthYear;
            $entries[] = [
                'Month' => $month,
                'Year' => $year,
                'DaysPastDue' => $dpdTokens[$idx] ?? null,
            ];
        }

        return $entries;
    }

    private function normalizeHistoryLine(string $line): string
    {
        $line = preg_replace('/\s+\d+\/\d+\s*$/', '', $line);
        return trim($line);
    }

    private function extractHistoryMonthTokens(string $line): array
    {
        if (!preg_match_all('/\b([A-Za-z]{3})\s+(\d{4})\b/', $line, $matches, PREG_SET_ORDER)) {
            return [];
        }
        $months = [];
        foreach ($matches as $m) {
            $months[] = [$m[1], $m[2]];
        }
        return $months;
    }

    private function extractHistoryDpdTokens(string $line): array
    {
        if (!preg_match_all('/\b(\d{1,3}|STD|XXX|DBT|SMA|SUB|LSS)\b/', $line, $matches)) {
            return [];
        }
        return $matches[1];
    }

    private function isPageNumberLine(string $line): bool
    {
        return (bool) preg_match('/^\d{1,2}\/\d{2}$/', trim($line));
    }

    private function normalizeAddressLine(string $line): string
    {
        $line = preg_replace('/\s+\d{1,2}\/\d{2}\s*$/', '', $line);
        return trim($line);
    }

    private function nextAddressValue(array $lines, int $index, bool $dateOnly = false): ?string
    {
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($candidate === '--- PAGE BREAK ---' || $this->isPageNumberLine($candidate) || $this->isJunkLine($candidate)) {
                continue;
            }
            if (in_array($candidate, ['Category', 'Residence Code', 'Date Reported'], true)) {
                return null;
            }
            if ($dateOnly && !preg_match('/\d{2}\/\d{2}\/\d{4}/', $candidate)) {
                return null;
            }
            return $candidate;
        }
        return null;
    }

    private function findLineIndex(array $lines, int $startIndex, string $value): int
    {
        for ($i = $startIndex; $i < count($lines); $i++) {
            if ($lines[$i] === $value) {
                return $i;
            }
        }
        return $startIndex - 1;
    }

    private function applyValidation(array &$payload): void
    {
        $this->validateReportInfo($payload);
        $this->validatePersonalInfo($payload);
        $this->validateAddresses($payload);
        $this->validatePhones($payload);
        $this->validatePhoneNumbers($payload);
        $this->validateEmails($payload);
        $this->validateEmploymentIndicators($payload);
        $this->validateAccountDates($payload);
        $this->validateAccountNumbers($payload);
        $this->validateAccountAmounts($payload);
        $this->validateScoreAndControl($payload);
        $this->validateEnquiries($payload);
        $this->validatePaymentHistory($payload);
    }

    private function validateReportInfo(array &$payload): void
    {
        $reportDate = $payload['ReportInformation']['ReportDate'] ?? 'N/A';
        if (!$this->isValidDateValue($reportDate)) {
            $this->addWarning('ReportInformation.ReportDate invalid: ' . (string) $reportDate);
            $payload['ReportInformation']['ReportDate'] = 'N/A';
        }
    }

    private function validatePersonalInfo(array &$payload): void
    {
        $dob = $payload['PersonalInformation']['DateOfBirth'] ?? 'N/A';
        if (!$this->isValidDateValue($dob)) {
            $this->addWarning('PersonalInformation.DateOfBirth invalid: ' . (string) $dob);
            $payload['PersonalInformation']['DateOfBirth'] = 'N/A';
        }
    }

    private function validateAddresses(array &$payload): void
    {
        $addresses = $payload['IDAndContactInfo']['ContactInformation']['Addresses'] ?? [];
        foreach ($addresses as $idx => $row) {
            $date = $row['DateReported'] ?? 'N/A';
            if (!$this->isValidDateValue($date)) {
                $this->addWarning('Addresses[' . $idx . '].DateReported invalid: ' . (string) $date);
                $payload['IDAndContactInfo']['ContactInformation']['Addresses'][$idx]['DateReported'] = 'N/A';
            }
        }
    }

    private function validatePhones(array &$payload): void
    {
        $allowedTypes = [
            'mobile phone',
            'mobile phone (e)',
            'mobile',
            'not classified',
            'not classified (e)',
            'office phone',
            'office phone (e)',
            'residence phone',
            'home phone',
            'telephone',
        ];
        $phones = $payload['IDAndContactInfo']['ContactInformation']['Telephones'] ?? [];
        foreach ($phones as $idx => $row) {
            $type = $row['Type'] ?? 'N/A';
            $typeKey = strtolower(trim((string) $type));
            if ($typeKey === '' || $typeKey === 'n/a') {
                continue;
            }
            if (!in_array($typeKey, $allowedTypes, true)) {
                $this->addWarning('Telephones[' . $idx . '].Type invalid: ' . (string) $type);
                $payload['IDAndContactInfo']['ContactInformation']['Telephones'][$idx]['Type'] = 'N/A';
            }
        }
    }

    private function validatePhoneNumbers(array &$payload): void
    {
        $phones = $payload['IDAndContactInfo']['ContactInformation']['Telephones'] ?? [];
        foreach ($phones as $idx => $row) {
            $number = $row['Number'] ?? '';
            $digits = preg_replace('/\D+/', '', (string) $number);
            if ($digits === '') {
                $this->addWarning('Telephones[' . $idx . '].Number missing.');
                $payload['IDAndContactInfo']['ContactInformation']['Telephones'][$idx]['Number'] = 'N/A';
                continue;
            }
            if (strlen($digits) < 8 || strlen($digits) > 15) {
                $this->addWarning('Telephones[' . $idx . '].Number invalid length: ' . (string) $number);
                $payload['IDAndContactInfo']['ContactInformation']['Telephones'][$idx]['Number'] = 'N/A';
            }
        }
    }

    private function validateEmails(array &$payload): void
    {
        $emails = $payload['IDAndContactInfo']['ContactInformation']['Emails'] ?? [];
        foreach ($emails as $idx => $row) {
            $value = trim((string) ($row['EmailAddress'] ?? ''));
            if ($value === '' || strtoupper($value) === 'N/A') {
                continue;
            }
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->addWarning('Emails[' . $idx . '].EmailAddress invalid: ' . $value);
                $payload['IDAndContactInfo']['ContactInformation']['Emails'][$idx]['EmailAddress'] = 'N/A';
            }
        }
    }

    private function validateEmploymentIndicators(array &$payload): void
    {
        if (!isset($payload['EmploymentInformation'])) {
            return;
        }

        $monthlyAnnual = $payload['EmploymentInformation']['MonthlyAnnualIncomeIndicator'] ?? 'N/A';
        $netGross = $payload['EmploymentInformation']['NetGrossIncomeIndicator'] ?? 'N/A';

        $payload['EmploymentInformation']['MonthlyAnnualIncomeIndicator'] = $this->normalizeIndicatorValue(
            (string) $monthlyAnnual,
            ['monthly', 'annual'],
            'EmploymentInformation.MonthlyAnnualIncomeIndicator'
        );

        $payload['EmploymentInformation']['NetGrossIncomeIndicator'] = $this->normalizeIndicatorValue(
            (string) $netGross,
            ['net', 'gross'],
            'EmploymentInformation.NetGrossIncomeIndicator'
        );
    }

    private function validateAccountDates(array &$payload): void
    {
        $accounts = $payload['Accounts'] ?? [];
        $dateFields = [
            'DateOpened',
            'DateClosed',
            'LastPaymentDate',
            'DateReportedAndCertified',
            'PaymentStartDate',
            'PaymentEndDate',
        ];
        foreach ($accounts as $idx => $acc) {
            foreach ($dateFields as $field) {
                $value = $acc[$field] ?? 'N/A';
                if (!$this->isValidDateValue($value)) {
                    $this->addWarning('Accounts[' . $idx . '].' . $field . ' invalid: ' . (string) $value);
                    $payload['Accounts'][$idx][$field] = 'N/A';
                }
            }
        }
    }

    private function validateAccountNumbers(array &$payload): void
    {
        $accounts = $payload['Accounts'] ?? [];
        foreach ($accounts as $idx => $acc) {
            $value = trim((string) ($acc['AccountNumber'] ?? ''));
            if ($value === '' || strtoupper($value) === 'N/A') {
                continue;
            }
            if ($this->looksLikeLabel($value)) {
                $this->addWarning('Accounts[' . $idx . '].AccountNumber looks like label: ' . $value);
                $payload['Accounts'][$idx]['AccountNumber'] = 'N/A';
            }
        }
    }

    private function validateAccountAmounts(array &$payload): void
    {
        $accounts = $payload['Accounts'] ?? [];
        $amountFields = [
            'CreditLimit',
            'CashLimit',
            'HighCredit',
            'SanctionedAmount',
            'CurrentBalance',
            'AmountOverdue',
            'EmiAmount',
            'ActualPaymentAmount',
            'ValueOfCollateral',
            'WrittenOffAmountTotal',
            'WrittenOffAmountPrincipal',
            'SettlementAmount',
        ];

        foreach ($accounts as $idx => $acc) {
            foreach ($amountFields as $field) {
                $value = $acc[$field] ?? 'N/A';
                if ($this->isPlaceholderValue((string) $value) || strtoupper((string) $value) === 'N/A') {
                    continue;
                }
                $digits = preg_replace('/[^0-9.]/', '', (string) $value);
                if ($digits === '' || !is_numeric($digits)) {
                    $this->addWarning('Accounts[' . $idx . '].' . $field . ' invalid: ' . (string) $value);
                    $payload['Accounts'][$idx][$field] = 'N/A';
                }
            }
        }
    }

    private function validateScoreAndControl(array &$payload): void
    {
        $score = $payload['ReportInformation']['Score'] ?? 'N/A';
        $scoreVal = (int) preg_replace('/\D+/', '', (string) $score);
        if ($score !== 'N/A' && ($scoreVal < 300 || $scoreVal > 900)) {
            $this->addWarning('ReportInformation.Score out of range: ' . (string) $score);
            $payload['ReportInformation']['Score'] = 'N/A';
        }

        $control = $payload['ReportInformation']['ControlNumber'] ?? 'N/A';
        if ($control !== 'N/A' && !preg_match('/^\d+$/', (string) $control)) {
            $this->addWarning('ReportInformation.ControlNumber invalid: ' . (string) $control);
            $payload['ReportInformation']['ControlNumber'] = 'N/A';
        }
    }

    private function validateEnquiries(array &$payload): void
    {
        $enquiries = $payload['Enquiries'] ?? [];
        foreach ($enquiries as $idx => $enq) {
            $date = $enq['DateOfEnquiry'] ?? 'N/A';
            if (!$this->isValidDateValue($date)) {
                $this->addWarning('Enquiries[' . $idx . '].DateOfEnquiry invalid: ' . (string) $date);
                $payload['Enquiries'][$idx]['DateOfEnquiry'] = 'N/A';
            }
        }
    }

    private function validatePaymentHistory(array &$payload): void
    {
        $accounts = $payload['Accounts'] ?? [];
        $allowedDpd = ['STD', 'XXX', 'DBT', 'SMA', 'SUB', 'LSS'];
        foreach ($accounts as $aIdx => $acc) {
            $history = $acc['PaymentHistory'] ?? [];
            foreach ($history as $hIdx => $row) {
                $month = $row['Month'] ?? '';
                $year = $row['Year'] ?? '';
                if (!preg_match('/^[A-Za-z]{3}$/', (string) $month)) {
                    $this->addWarning('Accounts[' . $aIdx . '].PaymentHistory[' . $hIdx . '].Month invalid: ' . (string) $month);
                    $payload['Accounts'][$aIdx]['PaymentHistory'][$hIdx]['Month'] = 'N/A';
                }
                if (!preg_match('/^\d{4}$/', (string) $year)) {
                    $this->addWarning('Accounts[' . $aIdx . '].PaymentHistory[' . $hIdx . '].Year invalid: ' . (string) $year);
                    $payload['Accounts'][$aIdx]['PaymentHistory'][$hIdx]['Year'] = 'N/A';
                }
                $dpd = $row['DaysPastDue'] ?? 'N/A';
                if ($this->isPlaceholderValue((string) $dpd) || strtoupper((string) $dpd) === 'N/A') {
                    continue;
                }
                if (!is_numeric($dpd) && !in_array(strtoupper((string) $dpd), $allowedDpd, true)) {
                    $this->addWarning('Accounts[' . $aIdx . '].PaymentHistory[' . $hIdx . '].DaysPastDue invalid: ' . (string) $dpd);
                    $payload['Accounts'][$aIdx]['PaymentHistory'][$hIdx]['DaysPastDue'] = 'N/A';
                }
            }
        }
    }

    private function normalizeIndicatorValue(string $value, array $allowed, string $path): string
    {
        $trim = trim($value);
        if ($trim === '' || $trim === '-' || $trim === '--' || strtoupper($trim) === 'N/A') {
            return 'N/A';
        }
        $valueLower = strtolower($trim);
        foreach ($allowed as $token) {
            if ($valueLower === $token) {
                return ucfirst($token);
            }
        }
        foreach ($allowed as $token) {
            if (str_contains($valueLower, $token)) {
                return ucfirst($token);
            }
        }
        $this->addWarning($path . ' invalid: ' . $value);
        return 'N/A';
    }

    private function looksLikeLabel(string $value): bool
    {
        $labelKeys = [
            'membername',
            'accounttype',
            'accountnumber',
            'ownership',
            'creditlimit',
            'cashlimit',
            'highcredit',
            'sanctionedamount',
            'currentbalance',
            'amountoverdue',
            'rateofinterest',
            'repaymenttenure',
            'emiamount',
            'paymentfrequency',
            'actualpaymentamount',
            'dateopeneddisbursed',
            'dateclosed',
            'dateoflastpayment',
            'datereportedandcertified',
            'valueofcollateral',
            'typeofcollateral',
            'suitfiledwillfuldefault',
            'creditfacilitystatus',
            'writtenoffamounttotal',
            'writtenoffamountprincipal',
            'settlementamount',
            'paymentstartdate',
            'paymentenddate',
        ];
        $key = $this->canonicalizeLabel($value);
        foreach ($labelKeys as $label) {
            if ($key === $label) {
                return true;
            }
        }
        return false;
    }

    private function isValidDateValue($value): bool
    {
        if ($value === null) {
            return false;
        }
        $trim = trim((string) $value);
        if ($trim === '' || $trim === '-' || $trim === '--' || strtoupper($trim) === 'N/A') {
            return true;
        }
        return (bool) preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trim);
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    private function moveTrailingAddressCategory(array &$current): void
    {
        if ($current['Address'] === '') {
            return;
        }
        if ($current['Type'] !== 'N/A' && $current['Type'] !== '') {
            return;
        }
        $categories = [
            'Permanent Address',
            'Residence Address',
            'Office Address',
            'Business Address',
            'Correspondence Address',
            'Current Address',
        ];
        foreach ($categories as $category) {
            $pattern = '/\s+' . preg_quote($category, '/') . '$/i';
            if (preg_match($pattern, $current['Address'])) {
                $current['Address'] = trim(preg_replace($pattern, '', $current['Address']));
                $current['Type'] = $category;
                return;
            }
        }
    }

    private function parseEmploymentIndicators(string $value): array
    {
        $tokens = preg_split('/\s+/', trim($value)) ?: [];
        $monthlyAnnual = null;
        $netGross = null;
        foreach ($tokens as $token) {
            $tokenUpper = strtoupper($token);
            if (in_array($tokenUpper, ['MONTHLY', 'ANNUAL'], true)) {
                $monthlyAnnual = ucfirst(strtolower($tokenUpper));
            }
            if (in_array($tokenUpper, ['NET', 'GROSS'], true)) {
                $netGross = ucfirst(strtolower($tokenUpper));
            }
        }
        if ($monthlyAnnual === null && $netGross === null) {
            return [$value, null];
        }
        return [$monthlyAnnual, $netGross];
    }

    private function nextHistoryValue(array $lines, int $index): array
    {
        $labels = [
            'Member Name',
            'Account Type',
            'Account Number',
            'Ownership',
            'Credit Limit',
            'Cash Limit',
            'High Credit',
            'Sanctioned Amount',
            'Current Balance',
            'Amount Overdue',
            'Rate of Interest',
            'Repayment Tenure',
            'EMI Amount',
            'Payment Frequency',
            'Actual Payment Amount',
            'Date Opened / Disbursed',
            'Date Closed',
            'Date of Last Payment',
            'Date Reported And Certified',
            'Value of Collateral',
            'Type of Collateral',
            'Suit - Filed / Willful Default',
            'Credit Facility Status',
            'Written-off Amount (Total)',
            'Written-off Amount (Principal)',
            'Settlement Amount',
            'Payment Start Date',
            'Payment End Date',
            'Payment History',
            'ENQUIRY DETAILS',
        ];
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate)) {
                continue;
            }
            if ($this->matchLabel($candidate, $labels) !== null) {
                return [null, $index];
            }
            if ($this->isPlaceholderValue($candidate)) {
                continue;
            }
            if ($this->isPageNumberLine($candidate)) {
                continue;
            }
            if ($this->isMonthYearLine($candidate)) {
                return [null, $index];
            }
            if (preg_match('/STD\s+Standard/i', $candidate)) {
                return ['STD', $i];
            }
            if (preg_match('/\bDBT[: ]+Doubtful\b/i', $candidate)) {
                return ['DBT', $i];
            }
            if (preg_match('/^:\s*(STD|XXX|DBT|SMA|SUB|LSS)\b/', $candidate, $m)) {
                return [$m[1], $i];
            }
            if (preg_match('/^(STD|XXX|DBT|SMA|SUB|LSS|\d{1,3})$/', $candidate, $m)) {
                return [$m[1], $i];
            }
            if ($this->isHistoryLegendLine($candidate)) {
                continue;
            }
            return [null, $index];
        }
        return [null, $index];
    }

    private function nextPaymentStatusDpd(array $lines, int $index): ?string
    {
        $stopLabels = [
            'Member Name',
            'Account Type',
            'Account Number',
            'Ownership',
            'Date Opened / Disbursed',
            'Date Reported And Certified',
            'Payment Start Date',
            'Payment End Date',
            'Payment History',
            'ENQUIRY DETAILS',
        ];
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate) || $this->isHistoryLegendLine($candidate)) {
                continue;
            }
            if ($this->isPlaceholderValue($candidate) || $this->isPageNumberLine($candidate)) {
                continue;
            }
            if ($this->matchLabel($candidate, $stopLabels) !== null) {
                return null;
            }
            if (preg_match('/^\d{1,3}$/', trim($candidate))) {
                return trim($candidate);
            }
        }
        return null;
    }

    private function isMonthYearLine(string $line): bool
    {
        return (bool) preg_match('/^[A-Za-z]{3}\s+\d{4}\b/', trim($line));
    }

    private function nextEmploymentValue(array $lines, int $index): ?string
    {
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate)) {
                continue;
            }
            if ($this->matchLabel($candidate, [
                'Date Reported',
                'Account Type',
                'Occupation',
                'Income',
                'Monthly / Annual Income Indicator',
                'Net / Gross Income Indicator',
                'ALL ACCOUNTS',
                'OPEN ACCOUNTS',
                'CLOSED ACCOUNTS',
            ]) !== null) {
                return null;
            }
            if ($this->isPlaceholderValue($candidate)) {
                continue;
            }
            if (in_array($candidate, ['ALL ACCOUNTS', 'OPEN ACCOUNTS', 'CLOSED ACCOUNTS'], true)) {
                return null;
            }
            return $candidate;
        }
        return null;
    }

    private function nextAccountValue(array $lines, int $index): ?string
    {
        $labels = [
            'Member Name',
            'Account Type',
            'Account Number',
            'Ownership',
            'Sanctioned Amount',
            'Current Balance',
            'Amount Overdue',
            'Date Opened / Disbursed',
            'Date Reported And Certified',
            'Written-off Amount (Total)',
            'Written-off Amount (Principal)',
            'Suit - Filed / Willful Default',
            'Suit - Filed / Wilful Default',
            'Type of Collateral',
            'Credit Facility Status',
            'Settlement Amount',
            'Payment Start Date',
            'Payment End Date',
            'Payment History',
            'ENQUIRY DETAILS',
        ];
        $canonicalLabelSet = [];
        foreach ($labels as $label) {
            $canonicalLabelSet[$this->canonicalizeLabel($label)] = true;
        }
        $canonicalLabelKeys = array_keys($canonicalLabelSet);
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate)) {
                continue;
            }
            if ($this->matchAccountLabel($candidate, $labels) !== null) {
                return null;
            }
            if ($this->isPlaceholderValue($candidate)) {
                continue;
            }
            if (trim($candidate) === '0') {
                continue;
            }
            if ($this->isPageNumberLine($candidate)) {
                continue;
            }
            $candidateKey = $this->canonicalizeLabel($candidate);
            if ($candidateKey !== '' && isset($canonicalLabelSet[$candidateKey])) {
                continue;
            }
            if ($candidateKey !== '') {
                $looksLikeLabel = false;
                foreach ($canonicalLabelKeys as $labelKey) {
                    if ($labelKey !== '' && str_contains($candidateKey, $labelKey)) {
                        $looksLikeLabel = true;
                        break;
                    }
                }
                if ($looksLikeLabel) {
                    continue;
                }
            }
            return $candidate;
        }
        return null;
    }

    private function matchAccountLabel(string $line, array $labels): ?string
    {
        $lineKey = $this->canonicalizeLabel($line);
        if ($lineKey === '') {
            return null;
        }
        foreach ($labels as $label) {
            $labelKey = $this->canonicalizeLabel($label);
            if ($labelKey !== '' && str_starts_with($lineKey, $labelKey)) {
                return $label;
            }
        }
        return null;
    }

    private function inlineValueForLabel(string $rawLine, string $label): ?string
    {
        $pattern = '/^' . preg_quote($label, '/') . '\s+(.+)$/i';
        if (preg_match($pattern, $rawLine, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function inlineDateForLabel(string $rawLine, string $label): ?string
    {
        $value = $this->inlineValueForLabel($rawLine, $label);
        if ($value === null) {
            return null;
        }
        return $this->extractDateFromLine($value);
    }

    private function canonicalizeLabel(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]/', '', $value);
        return $value ?? '';
    }

    private function sanitizeAccountValue(?string $value, array $labels): ?string
    {
        if ($value === null) {
            return null;
        }
        $candidateKey = $this->canonicalizeLabel($value);
        if ($candidateKey === '') {
            return null;
        }
        foreach ($labels as $label) {
            $labelKey = $this->canonicalizeLabel($label);
            if ($labelKey === '') {
                continue;
            }
            if ($candidateKey === $labelKey || str_contains($candidateKey, $labelKey)) {
                return null;
            }
        }
        return $value;
    }

    private function nextDateValue(array $lines, int $index): ?string
    {
        $labels = [
            'Member Name',
            'Account Type',
            'Account Number',
            'Ownership',
            'Sanctioned Amount',
            'Current Balance',
            'Amount Overdue',
            'Date Opened / Disbursed',
            'Date Closed',
            'Date of Last Payment',
            'Date Reported And Certified',
            'Payment Start Date',
            'Payment End Date',
            'Payment History',
            'ENQUIRY DETAILS',
        ];
        for ($i = $index + 1; $i < count($lines); $i++) {
            $candidate = $lines[$i];
            if ($this->isJunkLine($candidate)) {
                continue;
            }
            if ($this->matchLabel($candidate, $labels) !== null) {
                return null;
            }
            if ($this->isPlaceholderValue($candidate) || $this->isPageNumberLine($candidate)) {
                continue;
            }
            $date = $this->extractDateFromLine($candidate);
            if ($date !== null) {
                return $date;
            }
        }
        return null;
    }

    private function isPlaceholderValue(string $value): bool
    {
        $value = trim($value);
        return $value === '' || $value === '-' || $value === '--' || strtoupper($value) === 'NA';
    }

    private function extractDateFromLine(string $line): ?string
    {
        if (preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $line, $m)) {
            return $m[1];
        }
        return null;
    }
}
