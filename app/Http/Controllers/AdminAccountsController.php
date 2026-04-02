<?php

namespace App\Http\Controllers;

use App\Services\ExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAccountsController extends Controller
{
    private const EXPORT_HEADERS = [
        'Seq',
        'Customer Mobile',
        'Institution',
        'Account #',
        'Type',
        'Ownership',
        'Balance',
        'Past Due',
        'Sanction',
        'High Credit',
        'Credit Limit',
        'Cash Limit',
        'Date Opened',
        'Date Closed',
        'Date Reported',
        'Amount Overdue',
        'Rate of Interest',
        'Repayment Tenure',
        'EMI Amount',
        'Payment Frequency',
        'Actual Payment Amount',
        'Last Payment Date',
        'Value of Collateral',
        'Type of Collateral',
        'Suit - Filed / Willful Default',
        'Credit Facility Status',
        'Written-off Amount (Total)',
        'Written-off Amount (Principal)',
        'Settlement Amount',
    ];

    public function index(Request $request)
    {
        $filters = [
            'institution' => trim((string) $request->query('institution', '')),
        ];

        $institutions = DB::table('cir_retail_account_details')
            ->select('institution')
            ->whereNotNull('institution')
            ->where('institution', '<>', '')
            ->distinct()
            ->orderBy('institution')
            ->pluck('institution');

        $query = DB::table('cir_retail_account_details')
            ->leftJoin('users', 'cir_retail_account_details.user_id', '=', 'users.user_id')
            ->select([
                'cir_retail_account_details.*',
                'users.mobile_number as customer_mobile',
            ]);
        if ($filters['institution'] !== '') {
            $query->where('institution', $filters['institution']);
        }

        $results = $query
            ->orderBy('institution')
            ->orderBy('report_id')
            ->orderBy('seq')
            ->paginate(100)
            ->withQueryString();

        return view('admin-accounts', [
            'filters' => $filters,
            'institutions' => $institutions,
            'results' => $results,
        ]);
    }

    public function downloadExcel(Request $request, ExcelExporter $exporter)
    {
        $institution = trim((string) $request->query('institution', ''));

        $query = DB::table('cir_retail_account_details')
            ->leftJoin('users', 'cir_retail_account_details.user_id', '=', 'users.user_id')
            ->select([
                'cir_retail_account_details.*',
                'users.mobile_number as customer_mobile',
            ]);
        if ($institution !== '') {
            $query->where('institution', $institution);
        }

        $rows = $query
            ->orderBy('institution')
            ->orderBy('report_id')
            ->orderBy('seq')
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'No accounts found for export.');
        }

        $safeInstitution = $institution !== '' ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $institution) : 'all';
        $fileName = 'accounts_' . $safeInstitution . '_' . now()->format('Ymd_His');
        $tempPath = $exporter->createAccountsExport($rows, $fileName);

        return response()->download($tempPath, $fileName . '.xlsx')->deleteFileAfterSend(true);
    }

    public function downloadCsv(Request $request)
    {
        $institution = trim((string) $request->query('institution', ''));

        $query = DB::table('cir_retail_account_details')
            ->leftJoin('users', 'cir_retail_account_details.user_id', '=', 'users.user_id')
            ->select([
                'cir_retail_account_details.*',
                'users.mobile_number as customer_mobile',
            ]);
        if ($institution !== '') {
            $query->where('institution', $institution);
        }

        $safeInstitution = $institution !== '' ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $institution) : 'all';
        $fileName = 'accounts_' . $safeInstitution . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::EXPORT_HEADERS);

            $query->orderBy('institution')->orderBy('report_id')->orderBy('seq')
                ->chunk(1000, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, $this->mapAccountRow($row));
                    }
                });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function mapAccountRow(object $row): array
    {
        return [
            $this->cleanValue($this->pickValue($row, ['seq'])),
            $this->cleanValue($this->pickValue($row, ['customer_mobile', 'mobile_number'])),
            $this->cleanValue($this->pickValue($row, ['institution'])),
            $this->cleanValue($this->pickValue($row, ['account_number'])),
            $this->cleanValue($this->pickValue($row, ['account_type'])),
            $this->cleanValue($this->pickValue($row, ['ownership_type'])),
            $this->cleanValue($this->pickValue($row, ['balance'])),
            $this->cleanValue($this->pickValue($row, ['PastDueAmount', 'past_due_amount', 'amount_overdue_value', 'amount_overdue'])),
            $this->cleanValue($this->pickValue($row, ['sanction_amount'])),
            $this->cleanValue($this->pickValue($row, ['high_credit'])),
            $this->cleanValue($this->pickValue($row, ['credit_limit'])),
            $this->cleanValue($this->pickValue($row, ['cash_limit_value', 'cash_limit', 'CashLimit'])),
            $this->cleanValue($this->pickValue($row, ['data_opened', 'date_opened'])),
            $this->cleanValue($this->pickValue($row, ['date_closed'])),
            $this->cleanValue($this->pickValue($row, ['date_reported', 'date_reported_and_certified'])),
            $this->cleanValue($this->pickValue($row, ['amount_overdue_value', 'amount_overdue', 'PastDueAmount', 'past_due_amount'])),
            $this->cleanValue($this->pickValue($row, ['rate_of_interest_value', 'InterestRate', 'interest_rate'])),
            $this->cleanValue($this->pickValue($row, ['repayment_tenure_value', 'repayment_tenure'])),
            $this->cleanValue($this->pickValue($row, ['emi_amount_value', 'installment_amount'])),
            $this->cleanValue($this->pickValue($row, ['payment_frequency_value', 'term_frequency'])),
            $this->cleanValue($this->pickValue($row, ['actual_payment_amount_value', 'last_payment'])),
            $this->cleanValue($this->pickValue($row, ['last_payment_date_value', 'last_payment_date'])),
            $this->cleanValue($this->pickValue($row, ['collateral_value_value', 'CollateralValue'])),
            $this->cleanValue($this->pickValue($row, ['collateral_type_value', 'CollateralType'])),
            $this->cleanValue($this->pickValue($row, ['suit_filed_value', 'suit_filed_status'])),
            $this->cleanValue($this->pickValue($row, ['credit_facility_status_value', 'account_status'])),
            $this->cleanValue($this->pickValue($row, ['written_off_total_value', 'write_off_amount'])),
            $this->cleanValue($this->pickValue($row, ['written_off_principal_value', 'write_off_amount'])),
            $this->cleanValue($this->pickValue($row, ['settlement_amount_value', 'settlement_amount'])),
        ];
    }

    private function pickValue(object $row, array $keys)
    {
        foreach ($keys as $key) {
            $val = data_get($row, $key);
            if ($val !== null) {
                return $val;
            }
        }
        return null;
    }

    private function cleanValue($value): string
    {
        if ($value === null) {
            return '';
        }
        $text = trim((string) $value);
        if ($text === '' || strtoupper($text) === 'N/A') {
            return '';
        }
        return $text;
    }
}
