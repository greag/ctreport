<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Account Directory</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800">
        <div class="max-w-7xl mx-auto px-4 py-8">
            <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">Account Directory</h1>
                    <p class="text-slate-600 text-sm">Filter retail account details by institution.</p>
                </div>
                <div class="flex flex-col items-start gap-2 md:items-end">
                    <a href="/" class="text-indigo-600 hover:text-indigo-500 font-medium">Back to Upload</a>
                    <a href="/reports" class="text-slate-600 hover:text-slate-900 text-sm font-medium">View Existing Reports</a>
                </div>
            </header>

            <form method="GET" action="/admin/accounts" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Institution</label>
                        <select name="institution" class="w-full px-3 py-2 border border-slate-300 rounded-md">
                            <option value="">All institutions</option>
                            @foreach($institutions as $institution)
                                <option value="{{ $institution }}" @selected($filters['institution'] === $institution)>
                                    {{ $institution }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button class="px-5 py-2 bg-indigo-600 text-white rounded-md">Search</button>
                        <a href="/admin/accounts" class="px-5 py-2 border border-slate-300 rounded-md">Clear</a>
                    </div>
                    <div class="md:text-right flex flex-wrap gap-2 md:justify-end">
                        <a href="/admin/accounts/download?institution={{ urlencode($filters['institution']) }}"
                           class="inline-flex items-center justify-center px-4 py-2 border border-emerald-500 text-emerald-700 rounded-md hover:bg-emerald-50">
                            Download Excel
                        </a>
                        <a href="/admin/accounts/download-csv?institution={{ urlencode($filters['institution']) }}"
                           class="inline-flex items-center justify-center px-4 py-2 border border-slate-300 text-slate-700 rounded-md hover:bg-slate-50">
                            Download CSV (Large)
                        </a>
                    </div>
                </div>
                @if($filters['institution'] === '')
                    <div class="mt-3 text-xs text-slate-500">
                        Full Excel export may fail on large datasets. Use CSV for full exports.
                    </div>
                @endif
            </form>

            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Accounts</h2>
                    <div class="text-sm text-slate-500">Total: {{ $results->total() }}</div>
                </div>
                <div class="overflow-x-auto">
                    @php
                        $displayValue = function ($value) {
                            if ($value === null) {
                                return '';
                            }
                            $text = trim((string) $value);
                            if ($text === '' || strtoupper($text) === 'N/A') {
                                return '';
                            }
                            return $text;
                        };
                        $getValue = function ($row, string $key) {
                            return data_get($row, $key);
                        };
                        $pickValue = function ($row, array $keys) use ($getValue) {
                            foreach ($keys as $key) {
                                $val = $getValue($row, $key);
                                if ($val !== null) {
                                    return $val;
                                }
                            }
                            return null;
                        };
                    @endphp
                    <div class="min-w-full text-sm border border-slate-200 rounded-lg">
                        <table class="min-w-full text-sm border-collapse table-fixed">
                            <thead class="text-left text-slate-600 bg-slate-100 sticky top-0 z-10">
                                <tr>
                                    <th class="py-2 px-3 w-16 border border-slate-200">Seq</th>
                                    <th class="py-2 px-3 w-40 border border-slate-200">Institution</th>
                                    <th class="py-2 px-3 w-44 border border-slate-200">Account #</th>
                                    <th class="py-2 px-3 w-36 border border-slate-200">Type</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Ownership</th>
                                    <th class="py-2 px-3 w-28 border border-slate-200">Balance</th>
                                    <th class="py-2 px-3 w-28 border border-slate-200">Past Due</th>
                                    <th class="py-2 px-3 w-28 border border-slate-200">Sanction</th>
                                    <th class="py-2 px-3 w-28 border border-slate-200">High Credit</th>
                                    <th class="py-2 px-3 w-28 border border-slate-200">Credit Limit</th>
                                    <th class="py-2 px-3 w-28 border border-slate-200">Cash Limit</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Date Opened</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Date Closed</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Date Reported</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Amount Overdue</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Rate of Interest</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Repayment Tenure</th>
                                    <th class="py-2 px-3 w-28 border border-slate-200">EMI Amount</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Payment Frequency</th>
                                    <th class="py-2 px-3 w-40 border border-slate-200">Actual Payment Amount</th>
                                    <th class="py-2 px-3 w-32 border border-slate-200">Last Payment Date</th>
                                    <th class="py-2 px-3 w-40 border border-slate-200">Value of Collateral</th>
                                    <th class="py-2 px-3 w-40 border border-slate-200">Type of Collateral</th>
                                    <th class="py-2 px-3 w-56 border border-slate-200">Suit - Filed / Willful Default</th>
                                    <th class="py-2 px-3 w-48 border border-slate-200">Credit Facility Status</th>
                                    <th class="py-2 px-3 w-56 border border-slate-200">Written-off Amount (Total)</th>
                                    <th class="py-2 px-3 w-56 border border-slate-200">Written-off Amount (Principal)</th>
                                    <th class="py-2 px-3 w-40 border border-slate-200">Settlement Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($results as $row)
                                    @php
                                        $rowClass = $loop->index % 2 === 0 ? 'bg-white' : 'bg-slate-50';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['seq'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['institution'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['account_number'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['account_type'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['ownership_type'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['balance'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['PastDueAmount', 'past_due_amount', 'amount_overdue_value', 'amount_overdue'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['sanction_amount'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['high_credit'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['credit_limit'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['cash_limit_value', 'cash_limit', 'CashLimit'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['data_opened', 'date_opened'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['date_closed'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['date_reported', 'date_reported_and_certified'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['amount_overdue_value', 'amount_overdue', 'PastDueAmount', 'past_due_amount'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['rate_of_interest_value', 'InterestRate', 'interest_rate'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['repayment_tenure_value', 'repayment_tenure'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['emi_amount_value', 'installment_amount'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['payment_frequency_value', 'term_frequency'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['actual_payment_amount_value', 'last_payment'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['last_payment_date_value', 'last_payment_date'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['collateral_value_value', 'CollateralValue'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['collateral_type_value', 'CollateralType'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['suit_filed_value', 'suit_filed_status'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['credit_facility_status_value', 'account_status'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['written_off_total_value', 'write_off_amount'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['written_off_principal_value', 'write_off_amount'])) }}</td>
                                        <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($pickValue($row, ['settlement_amount_value', 'settlement_amount'])) }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="py-2 text-slate-500" colspan="28">No accounts found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    {{ $results->links() }}
                </div>
            </div>
        </div>
    </body>
</html>
