<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\EmployeeDirectory;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'mobile_number' => trim((string) $request->query('mobile_number', '')),
            'user_id' => trim((string) $request->query('user_id', '')),
            'control_number' => trim((string) $request->query('control_number', '')),
        ];

        $query = DB::table('credit_reports')
            ->leftJoin('users', 'credit_reports.user_id', '=', 'users.user_id')
            ->select([
                'credit_reports.report_id',
                'credit_reports.user_id',
                'credit_reports.report_order_number',
                'credit_reports.score_type',
                'credit_reports.credit_score',
                'credit_reports.generated_at',
                'users.mobile_number',
            ])
            ->orderByDesc('credit_reports.generated_at')
            ->orderByDesc('credit_reports.report_id');

        if ($filters['mobile_number'] !== '') {
            $query->where('users.mobile_number', $filters['mobile_number']);
        }
        if ($filters['user_id'] !== '') {
            $query->where('credit_reports.user_id', $filters['user_id']);
        }
        if ($filters['control_number'] !== '') {
            $query->where('credit_reports.report_order_number', $filters['control_number']);
        }

        $results = $query->limit(50)->get();

        return view('report-viewer', [
            'filters' => $filters,
            'results' => $results,
            'report' => null,
            'sections' => [],
            'isAdmin' => $this->isAdminUser($request),
        ]);
    }

    public function show(string $reportId)
    {
        $report = DB::table('credit_reports')
            ->leftJoin('users', 'credit_reports.user_id', '=', 'users.user_id')
            ->where('credit_reports.report_id', $reportId)
            ->select([
                'credit_reports.*',
                'users.mobile_number',
            ])
            ->first();

        if (!$report) {
            abort(404, 'Report not found.');
        }

        $sections = [
            'personal' => DB::table('cir_personal_info')->where('report_id', $reportId)->first(),
            'identifications' => DB::table('cir_identity_info')->where('report_id', $reportId)->orderBy('seq')->get(),
            'addresses' => DB::table('cir_address_info')->where('report_id', $reportId)->orderBy('seq')->get(),
            'phones' => DB::table('cir_phone_info')->where('report_id', $reportId)->orderBy('seq')->get(),
            'emails' => DB::table('cir_email_info')->where('report_id', $reportId)->orderBy('seq')->get(),
            'accounts' => DB::table('cir_retail_account_details')->where('report_id', $reportId)->orderBy('seq')->get(),
            'history' => DB::table('cir_history_48_months')
                ->leftJoin('cir_retail_account_details', 'cir_history_48_months.cir_account_id', '=', 'cir_retail_account_details.cir_account_id')
                ->where('cir_history_48_months.report_id', $reportId)
                ->orderBy('cir_history_48_months.cir_account_id')
                ->orderBy('cir_history_48_months.key')
                ->select([
                    'cir_history_48_months.*',
                    'cir_retail_account_details.institution',
                    'cir_retail_account_details.account_number',
                ])
                ->get(),
            'enquiries' => DB::table('cir_enquires')->where('report_id', $reportId)->orderBy('seq')->get(),
            'other_key_ind' => DB::table('cir_other_key_ind')->where('report_id', $reportId)->first(),
            'employment' => null,
            'warnings' => [],
        ];

        $identificationMeta = [];
        $accountMeta = [];
        $phoneMeta = [];
        $employmentMeta = null;
        $warnings = [];
        if (!empty($report->json_response)) {
            $payload = json_decode($report->json_response, true);
            if (is_array($payload)) {
                $warnings = $payload['InputResponse']['Warnings'] ?? [];
                $jsonIds = $payload['InputResponse']['IDAndContactInfo']['Identifications'] ?? [];
                foreach ($jsonIds as $id) {
                    $seq = isset($id['Sequence']) ? (string) $id['Sequence'] : '';
                    $idNumber = isset($id['IdNumber']) ? (string) $id['IdNumber'] : '';
                    $key = $seq !== '' ? $seq : $idNumber;
                    if ($key === '') {
                        continue;
                    }
                    $identificationMeta[$key] = [
                        'issue_date' => $id['IssueDate'] ?? null,
                        'expiry_date' => $id['ExpiryDate'] ?? null,
                        'id_number' => $idNumber,
                    ];
                }
                $jsonAccounts = $payload['InputResponse']['Accounts'] ?? [];
                foreach ($jsonAccounts as $acc) {
                    $seq = isset($acc['Sequence']) ? (string) $acc['Sequence'] : '';
                    if ($seq === '') {
                        continue;
                    }
                    $accountMeta[$seq] = $acc;
                }
                $jsonPhones = $payload['InputResponse']['IDAndContactInfo']['ContactInformation']['Telephones'] ?? [];
                foreach ($jsonPhones as $phone) {
                    $seq = isset($phone['Sequence']) ? (string) $phone['Sequence'] : '';
                    if ($seq === '') {
                        continue;
                    }
                    $phoneMeta[$seq] = $phone['Type'] ?? null;
                }
                $employmentMeta = $payload['InputResponse']['EmploymentInformation'] ?? null;
            }
        }
        $sections['warnings'] = is_array($warnings) ? $warnings : [];
        if (is_array($employmentMeta)) {
            $employmentLabels = [
                'Account Type',
                'Date Reported',
                'Occupation',
                'Income',
                'Monthly / Annual Income Indicator',
                'Net / Gross Income Indicator',
            ];
            $cleanEmployment = function ($value) use ($employmentLabels) {
                if ($value === null) {
                    return null;
                }
                $trim = trim((string) $value);
                if ($trim === '' || strtoupper($trim) === 'N/A') {
                    return null;
                }
                foreach ($employmentLabels as $label) {
                    if (strcasecmp($trim, $label) === 0) {
                        return null;
                    }
                }
                return $trim;
            };
            $sections['employment'] = [
                'AccountType' => $cleanEmployment($employmentMeta['AccountType'] ?? null),
                'DateReported' => $cleanEmployment($employmentMeta['DateReported'] ?? null),
                'Occupation' => $cleanEmployment($employmentMeta['Occupation'] ?? null),
                'Income' => $cleanEmployment($employmentMeta['Income'] ?? null),
                'MonthlyAnnualIncomeIndicator' => $cleanEmployment($employmentMeta['MonthlyAnnualIncomeIndicator'] ?? null),
                'NetGrossIncomeIndicator' => $cleanEmployment($employmentMeta['NetGrossIncomeIndicator'] ?? null),
            ];
        }

        foreach ($sections['identifications'] as $row) {
            $key = $row->seq ? (string) $row->seq : '';
            $meta = $key !== '' && isset($identificationMeta[$key]) ? $identificationMeta[$key] : null;
            if (!$meta && !empty($row->id_number)) {
                $meta = $identificationMeta[$row->id_number] ?? null;
            }
            $row->issue_date = $meta['issue_date'] ?? null;
            $row->expiry_date = $meta['expiry_date'] ?? null;
        }

        foreach ($sections['phones'] as $row) {
            $seq = $row->seq ? (string) $row->seq : '';
            $row->type_label = $seq !== '' ? ($phoneMeta[$seq] ?? null) : null;
        }

        $useValue = function ($primary, $fallback) {
            if ($primary === null) {
                return $fallback;
            }
            if (is_string($primary)) {
                $trim = trim($primary);
                if ($trim === '' || strtoupper($trim) === 'N/A') {
                    return $fallback;
                }
            }
            return $primary;
        };

        foreach ($sections['accounts'] as $row) {
            $seq = $row->seq ? (string) $row->seq : '';
            $meta = $seq !== '' ? ($accountMeta[$seq] ?? null) : null;
            $row->cash_limit_value = $useValue(null, $meta['CashLimit'] ?? null);
            $row->amount_overdue_value = $useValue($row->PastDueAmount ?? null, $meta['AmountOverdue'] ?? null);
            $row->rate_of_interest_value = $useValue($row->InterestRate ?? null, $meta['RateOfInterest'] ?? null);
            $row->repayment_tenure_value = $useValue($row->repayment_tenure ?? null, $meta['RepaymentTenure'] ?? null);
            $row->emi_amount_value = $useValue($row->installment_amount ?? null, $meta['EmiAmount'] ?? null);
            $row->payment_frequency_value = $useValue($row->term_frequency ?? null, $meta['PaymentFrequency'] ?? null);
            $row->actual_payment_amount_value = $useValue(null, $meta['ActualPaymentAmount'] ?? null);
            $row->last_payment_date_value = $useValue($row->last_payment_date ?? null, $meta['LastPaymentDate'] ?? null);
            $row->collateral_value_value = $useValue($row->CollateralValue ?? null, $meta['ValueOfCollateral'] ?? null);
            $row->collateral_type_value = $useValue($row->CollateralType ?? null, $meta['TypeOfCollateral'] ?? null);
            $row->suit_filed_value = $useValue($row->suit_filed_status ?? null, $meta['SuitFiledWillfulDefault'] ?? null);
            $row->credit_facility_status_value = $useValue($row->account_status ?? null, $meta['CreditFacilityStatus'] ?? null);
            $row->written_off_total_value = $useValue($row->write_off_amount ?? null, $meta['WrittenOffAmountTotal'] ?? null);
            $row->written_off_principal_value = $useValue(null, $meta['WrittenOffAmountPrincipal'] ?? null);
            $row->settlement_amount_value = $useValue(null, $meta['SettlementAmount'] ?? null);
            $row->payment_start_date_value = $useValue(null, $meta['PaymentStartDate'] ?? null);
            $row->payment_end_date_value = $useValue(null, $meta['PaymentEndDate'] ?? null);
        }

        $accountById = [];
        foreach ($sections['accounts'] as $row) {
            if (!$row->cir_account_id) {
                continue;
            }
            $accountById[$row->cir_account_id] = [
                'seq' => $row->seq ?? null,
                'institution' => $row->institution ?? null,
                'account_number' => $row->account_number ?? null,
                'payment_start' => $row->payment_start_date_value ?? null,
                'payment_end' => $row->payment_end_date_value ?? null,
            ];
        }

        $historyByAccount = [];
        foreach ($sections['history'] as $row) {
            $historyByAccount[$row->cir_account_id][] = [
                'key' => $row->key,
                'status' => $row->payment_status,
            ];
        }
        if ($sections['history']->count()) {
            $monthMap = [
                'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
                'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
            ];
            $rows = $sections['history']->all();
            usort($rows, function ($a, $b) use ($monthMap) {
                $parseKey = function ($key) use ($monthMap) {
                    $key = trim((string) $key);
                    if (preg_match('/^([A-Za-z]{3})\s+(\d{4})$/', $key, $m)) {
                        return [(int) $m[2], $monthMap[strtolower($m[1])] ?? 0];
                    }
                    if (preg_match('/^(\d{2})-(\d{2})$/', $key, $m)) {
                        return [2000 + (int) $m[2], (int) $m[1]];
                    }
                    if (preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
                        return [(int) $m[1], (int) $m[2]];
                    }
                    return [0, 0];
                };
                if ($a->cir_account_id !== $b->cir_account_id) {
                    return $a->cir_account_id <=> $b->cir_account_id;
                }
                [$ya, $ma] = $parseKey($a->key);
                [$yb, $mb] = $parseKey($b->key);
                if ($ya === $yb) {
                    return $mb <=> $ma;
                }
                return $yb <=> $ya;
            });
            $sections['history'] = collect($rows);
        }

        return view('report-viewer', [
            'filters' => [
                'mobile_number' => '',
                'user_id' => '',
                'control_number' => '',
            ],
            'results' => [],
            'report' => $report,
            'sections' => $sections,
            'historyByAccount' => $historyByAccount,
            'accountById' => $accountById,
            'isAdmin' => $this->isAdminUser(request()),
        ]);
    }

    public function destroy(Request $request, string $reportId)
    {
        $fileMeta = $this->findResultFiles((string) $reportId);

        DB::transaction(function () use ($reportId) {
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
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->where('report_id', $reportId)->delete();
                }
            }

            DB::table('credit_reports')->where('report_id', $reportId)->delete();
        });

        $this->deleteResultFiles($fileMeta);

        return redirect('/reports')->with('status', 'Report deleted.');
    }

    private function isAdminUser(Request $request): bool
    {
        $phone = (string) $request->session()->get('otp_phone', '');
        if ($phone === '') {
            return false;
        }
        return EmployeeDirectory::query()
            ->where('mobile_number', $phone)
            ->where('is_active', true)
            ->where('is_admin', true)
            ->exists();
    }

    private function findResultFiles(string $reportId): array
    {
        $matches = [];
        foreach (Storage::files('results') as $path) {
            if (!str_ends_with($path, '.json') || str_contains($path, '_validation.json')) {
                continue;
            }
            $meta = json_decode(Storage::get($path), true);
            if (!is_array($meta)) {
                continue;
            }
            $storage = $meta['storage'] ?? [];
            $storedReportId = (string) ($storage['report_id'] ?? '');
            if ($storedReportId !== $reportId) {
                continue;
            }
            $token = pathinfo($path, PATHINFO_FILENAME);
            $matches[] = [
                'token' => $token,
                'file_name' => (string) ($meta['fileName'] ?? ''),
                'upload_path' => (string) (($meta['upload']['path'] ?? '') ?: ($storage['upload_path'] ?? '')),
            ];
        }

        return $matches;
    }

    private function deleteResultFiles(array $matches): void
    {
        foreach ($matches as $meta) {
            $token = $meta['token'] ?? '';
            if ($token !== '') {
                Storage::delete("results/{$token}.json");
                Storage::delete("results/{$token}.txt");
                Storage::delete("results/{$token}_validation.json");
            }

            $fileName = trim((string) ($meta['file_name'] ?? ''));
            if ($fileName !== '') {
                $excel = "results/{$fileName}_Extracted_Info.xlsx";
                if (Storage::exists($excel)) {
                    Storage::delete($excel);
                }
            }

            $uploadPath = trim((string) ($meta['upload_path'] ?? ''));
            if ($uploadPath !== '') {
                Storage::delete($uploadPath);
            }
        }
    }
}
