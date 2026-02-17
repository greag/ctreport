<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ReportStorageService
{
    public function resolveUserId(?string $userId, ?string $mobileNumber): string
    {
        if ($userId) {
            $row = DB::table('users')->where('user_id', $userId)->first();
            if (!$row) {
                if (!$mobileNumber) {
                    throw new RuntimeException('User ID not found in LMS database.');
                }
                $this->insertUserIfMissing($userId, $mobileNumber);
                return $userId;
            }
            if ($mobileNumber && empty($row->mobile_number ?? null)) {
                DB::table('users')->where('user_id', $userId)->update([
                    'mobile_number' => $mobileNumber,
                ]);
            }
            return $userId;
        }

        if (!$mobileNumber) {
            throw new RuntimeException('Mobile number is required when User ID is not provided.');
        }

        $row = DB::table('users')->where('mobile_number', $mobileNumber)->first();
        if (!$row) {
            throw new RuntimeException('Mobile number not found in LMS database.');
        }

        return (string) $row->user_id;
    }

    private function insertUserIfMissing(string $userId, string $mobileNumber): void
    {
        if (DB::table('users')->where('user_id', $userId)->exists()) {
            return;
        }
        $roleId = $this->resolveDefaultRoleId();
        $row = [
            'user_id' => $userId,
            'mobile_number' => $mobileNumber,
            'role_id' => $roleId,
        ];
        if (Schema::hasColumn('users', 'created_at')) {
            $row['created_at'] = now();
        }
        if (Schema::hasColumn('users', 'updated_at')) {
            $row['updated_at'] = now();
        }
        DB::table('users')->insert($row);
    }

    private function resolveDefaultRoleId(): int
    {
        $envRoleId = (int) (env('DEFAULT_ROLE_ID') ?: 0);
        if ($envRoleId > 0) {
            return $envRoleId;
        }
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'role_id')) {
            $roleId = DB::table('roles')
                ->where('is_active', 1)
                ->orderBy('role_id')
                ->value('role_id');
            if ($roleId) {
                return (int) $roleId;
            }
        }
        throw new RuntimeException('Default role_id is not configured. Set DEFAULT_ROLE_ID or add an active role.');
    }

    public function findExistingReport(string $reportType, string $controlNumber): ?array
    {
        $row = DB::table('credit_reports')
            ->leftJoin('users', 'credit_reports.user_id', '=', 'users.user_id')
            ->where('credit_reports.report_order_number', $controlNumber)
            ->where('credit_reports.score_type', $reportType)
            ->select([
                'credit_reports.report_id',
                'credit_reports.user_id',
                'credit_reports.report_order_number',
                'credit_reports.score_type',
                'users.mobile_number',
            ])
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'report_id' => (string) $row->report_id,
            'user_id' => (string) $row->user_id,
            'control_number' => (string) $row->report_order_number,
            'report_type' => (string) $row->score_type,
            'mobile_number' => (string) ($row->mobile_number ?? ''),
        ];
    }

    public function storeReport(array $payload, string $userId, string $reportType, bool $overwrite): array
    {
        $reportInfo = $payload['InputResponse']['ReportInformation'] ?? [];
        $controlNumber = (string) ($reportInfo['ControlNumber'] ?? '');
        if ($controlNumber === '') {
            throw new RuntimeException('Control Number not found in report.');
        }

        $existing = $this->findExistingReport($reportType, $controlNumber);
        if ($existing && !$overwrite) {
            return [
                'status' => 'duplicate',
                'existing' => $existing,
            ];
        }

        $reportId = DB::transaction(function () use ($payload, $userId, $reportType, $existing) {
            $input = $payload['InputResponse'] ?? [];
            $reportInfo = $input['ReportInformation'] ?? [];
            $warnings = $input['Warnings'] ?? [];

            $reportId = $existing['report_id'] ?? null;
            $creditRow = [
                'user_id' => $userId,
                'credit_score' => $this->numOrNull($reportInfo['Score'] ?? null),
                'generated_at' => $this->dateOrNull($reportInfo['ReportDate'] ?? null) ?? now(),
                'report_order_number' => $reportInfo['ControlNumber'] ?? null,
                'score_type' => $reportType,
                'json_response' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'warnings_json' => $warnings ? json_encode($warnings, JSON_UNESCAPED_UNICODE) : null,
            ];

            if ($reportId) {
                DB::table('credit_reports')->where('report_id', $reportId)->update($creditRow);
                $this->deleteChildRows($reportId);
            } else {
                $reportId = (string) DB::table('credit_reports')->insertGetId($creditRow);
            }

            $this->insertPersonalInfo($reportId, $input);
            $this->insertIdentifications($reportId, $input);
            $this->insertAddresses($reportId, $input);
            $this->insertPhones($reportId, $input);
            $this->insertEmails($reportId, $input);
            $this->insertOtherKeyIndicators($reportId, $input);
            $this->insertEnquiries($reportId, $input);
            $this->insertAccountsAndHistory($reportId, $userId, $input);

            return [$reportId, $warnings];
        });

        [$reportId, $warnings] = $reportId;
        $this->exportWarningsCsv((string) $reportId, $warnings);

        return [
            'status' => 'stored',
            'report_id' => (string) $reportId,
        ];
    }

    private function deleteChildRows(string $reportId): void
    {
        $tables = [
            'cir_personal_info',
            'cir_identity_info',
            'cir_address_info',
            'cir_phone_info',
            'cir_email_info',
            'cir_other_key_ind',
            'cir_enquires',
            'cir_history_48_months',
            'cir_retail_account_details',
        ];

        foreach ($tables as $table) {
            DB::table($table)->where('report_id', $reportId)->delete();
        }
    }

    private function insertPersonalInfo(string $reportId, array $input): void
    {
        $personal = $input['PersonalInformation'] ?? [];
        $employment = $input['EmploymentInformation'] ?? [];

        DB::table('cir_personal_info')->insert([
            'report_id' => $reportId,
            'full_name' => $personal['Name'] ?? null,
            'date_of_birth' => $this->dateOrNull($personal['DateOfBirth'] ?? null),
            'gender' => $personal['Gender'] ?? null,
            'occupation' => $employment['Occupation'] ?? null,
        ]);
    }

    private function insertIdentifications(string $reportId, array $input): void
    {
        $ids = $input['IDAndContactInfo']['Identifications'] ?? [];
        $rows = [];
        foreach ($ids as $id) {
            $rows[] = [
                'report_id' => $reportId,
                'seq' => $id['Sequence'] ?? null,
                'type_of_document' => $id['IdentificationType'] ?? null,
                'id_number' => $id['IdNumber'] ?? null,
                'reported_date' => $this->dateOrNull($id['IssueDate'] ?? null),
                'added_at' => now(),
            ];
        }
        if ($rows) {
            DB::table('cir_identity_info')->insert($rows);
        }
    }

    private function insertAddresses(string $reportId, array $input): void
    {
        $addresses = $input['IDAndContactInfo']['ContactInformation']['Addresses'] ?? [];
        $rows = [];
        foreach ($addresses as $addr) {
            $rows[] = [
                'report_id' => $reportId,
                'seq' => $addr['Sequence'] ?? null,
                'reported_date' => $this->dateOrNull($addr['DateReported'] ?? null),
                'address' => $addr['Address'] ?? null,
                'type' => $this->normalizeAddressType($addr['Type'] ?? null, $addr['ResidenceCode'] ?? null),
            ];
        }
        if ($rows) {
            DB::table('cir_address_info')->insert($rows);
        }
    }

    private function normalizeAddressType(?string $category, ?string $residenceCode): ?string
    {
        $parts = [];
        foreach ([$category, $residenceCode] as $value) {
            $value = $this->cleanValue($value);
            if ($value !== null) {
                $parts[] = $value;
            }
        }
        if (!$parts) {
            return null;
        }
        return implode(' | ', $parts);
    }

    private function cleanValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || $value === '-' || $value === '--' || strtoupper($value) === 'N/A') {
            return null;
        }
        return $value;
    }

    private function insertPhones(string $reportId, array $input): void
    {
        $phones = $input['IDAndContactInfo']['ContactInformation']['Telephones'] ?? [];
        $rows = [];
        foreach ($phones as $phone) {
            $rows[] = [
                'report_id' => $reportId,
                'seq' => $phone['Sequence'] ?? null,
                'type_code' => $this->normalizePhoneTypeCode($phone['Type'] ?? null),
                'number' => $phone['Number'] ?? null,
            ];
        }
        if ($rows) {
            DB::table('cir_phone_info')->insert($rows);
        }
    }

    private function normalizePhoneTypeCode(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $value = trim($type);
        if ($value === '' || $value === '-' || $value === '--' || strtoupper($value) === 'N/A') {
            return null;
        }
        $map = [
            'mobile phone' => 'M',
            'mobile phone (e)' => 'M',
            'mobile' => 'M',
            'residence phone' => 'H',
            'home phone' => 'H',
            'office phone' => 'O',
            'telephone' => 'T',
            'not classified' => 'N',
            'not categorized' => 'N',
            'not classified (e)' => 'N',
        ];
        $key = strtolower($value);
        foreach ($map as $label => $code) {
            if ($key === $label) {
                return $code;
            }
        }
        return substr($value, 0, 5);
    }

    private function insertEmails(string $reportId, array $input): void
    {
        $emails = $input['IDAndContactInfo']['ContactInformation']['Emails'] ?? [];
        $rows = [];
        foreach ($emails as $email) {
            $rows[] = [
                'report_id' => $reportId,
                'seq' => $email['Sequence'] ?? null,
                'emai_address' => $email['EmailAddress'] ?? null,
            ];
        }
        if ($rows) {
            DB::table('cir_email_info')->insert($rows);
        }
    }

    private function insertOtherKeyIndicators(string $reportId, array $input): void
    {
        if (!isset($input['EmploymentInformation'])) {
            return;
        }

        DB::table('cir_other_key_ind')->insert([
            'report_id' => $reportId,
        ]);
    }

    private function exportWarningsCsv(string $reportId, array $warnings): void
    {
        if (empty($warnings)) {
            return;
        }
        $dir = storage_path('app/warnings');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'report_' . $reportId . '_warnings.csv';
        $lines = ["ReportId,Warning"];
        foreach ($warnings as $warning) {
            $escaped = '"' . str_replace('"', '""', (string) $warning) . '"';
            $lines[] = $reportId . ',' . $escaped;
        }
        File::put($path, implode("\n", $lines));
    }

    private function insertEnquiries(string $reportId, array $input): void
    {
        $enquiries = $input['Enquiries'] ?? [];
        $rows = [];
        foreach ($enquiries as $enquiry) {
            $rawMember = $enquiry['MemberName'] ?? null;
            $rawDate = $enquiry['DateOfEnquiry'] ?? null;
            $rawPurpose = $enquiry['EnquiryPurpose'] ?? null;

            $memberName = $this->sanitizeEnquiryLabel($rawMember);
            $purpose = $this->sanitizeEnquiryLabel($rawPurpose);
            $date = $this->dateOrNull($rawDate);
            if ($date === null) {
                $date = $this->extractDateFromString($rawDate)
                    ?? $this->extractDateFromString($rawMember)
                    ?? $this->extractDateFromString($rawPurpose);
            }

            if (($purpose === null || $purpose === 'N/A') && is_string($rawDate)) {
                $purpose = $this->extractValueAfterLabel($rawDate, 'Enquiry Purpose');
            }

            $rows[] = [
                'report_id' => $reportId,
                'seq' => $enquiry['Sequence'] ?? null,
                'Institution' => $memberName,
                'RequestPurpose' => $purpose,
                'Date' => $date,
                'created_at' => now(),
            ];
        }
        if ($rows) {
            DB::table('cir_enquires')->insert($rows);
        }
    }

    private function insertAccountsAndHistory(string $reportId, string $userId, array $input): void
    {
        $accounts = $input['Accounts'] ?? [];
        foreach ($accounts as $account) {
            $accountRow = [
                'user_id' => $userId,
                'report_id' => $reportId,
                'seq' => $account['Sequence'] ?? null,
                'account_number' => $account['AccountNumber'] ?? null,
                'institution' => $account['MemberName'] ?? null,
                'account_type' => $account['AccountType'] ?? null,
                'ownership_type' => $account['OwnershipType'] ?? null,
                'balance' => $this->numOrNull($account['CurrentBalance'] ?? null),
                'PastDueAmount' => $this->numOrNull($account['AmountOverdue'] ?? null),
                'sanction_amount' => $this->numOrNull($account['SanctionedAmount'] ?? null),
                'last_payment_date' => $this->dateOrNull($account['LastPaymentDate'] ?? null),
                'date_reported' => $this->dateOrNull($account['DateReportedAndCertified'] ?? null),
                'data_opened' => $this->dateOrNull($account['DateOpened'] ?? null),
                'date_closed' => $this->dateOrNull($account['DateClosed'] ?? null),
                'write_off_amount' => $this->numOrNull($account['WrittenOffAmountTotal'] ?? null),
                'InterestRate' => $account['RateOfInterest'] ?? null,
                'repayment_tenure' => $account['RepaymentTenure'] ?? null,
                'installment_amount' => $this->numOrNull($account['EmiAmount'] ?? null),
                'term_frequency' => $account['PaymentFrequency'] ?? null,
                'account_status' => $account['CreditFacilityStatus'] ?? null,
                'high_credit' => $this->numOrNull($account['HighCredit'] ?? null),
                'credit_limit' => $this->numOrNull($account['CreditLimit'] ?? null),
                'suit_filed_status' => $account['SuitFiledWillfulDefault'] ?? null,
                'CollateralValue' => $this->numOrNull($account['ValueOfCollateral'] ?? null),
                'CollateralType' => $account['TypeOfCollateral'] ?? null,
                'payment_history' => null,
                'created_at' => now(),
            ];

            $cirAccountId = DB::table('cir_retail_account_details')->insertGetId($accountRow);

            $historyRows = [];
            foreach (($account['PaymentHistory'] ?? []) as $history) {
                $key = trim(($history['Month'] ?? '') . ' ' . ($history['Year'] ?? ''));
                $historyRows[] = [
                    'user_id' => $userId,
                    'report_id' => $reportId,
                    'cir_account_id' => $cirAccountId,
                    'key' => $key ?: null,
                    'payment_status' => $history['DaysPastDue'] ?? null,
                ];
            }

            if ($historyRows) {
                DB::table('cir_history_48_months')->insert($historyRows);
            }
        }
    }

    private function numOrNull($value): ?float
    {
        if ($value === null) {
            return null;
        }
        $val = trim((string) $value);
        if ($val === '' || $val === 'N/A' || $val === '--' || $val === '-') {
            return null;
        }
        $val = preg_replace('/[^0-9.]/', '', $val);
        return $val === '' ? null : (float) $val;
    }

    private function dateOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $val = trim((string) $value);
        if ($val === '' || $val === 'N/A' || $val === '--' || $val === '-') {
            return null;
        }
        // Normalize common dd/mm/yyyy to yyyy-mm-dd for MySQL date/timestamp fields.
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $val, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            return $val;
        }
        return null;
    }

    private function sanitizeEnquiryLabel($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $val = trim((string) $value);
        if ($val === '' || $val === 'N/A' || $val === '--' || $val === '-') {
            return null;
        }
        if (stripos($val, 'Date Of Enquiry') !== false || stripos($val, 'Enquiry Purpose') !== false) {
            return null;
        }
        return $val;
    }

    private function extractValueAfterLabel(string $value, string $label): ?string
    {
        $pos = stripos($value, $label);
        if ($pos === false) {
            return null;
        }
        $out = trim(substr($value, $pos + strlen($label)));
        return $out === '' ? null : $out;
    }

    private function extractDateFromString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = (string) $value;
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        return null;
    }
}
