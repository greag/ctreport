<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Report Viewer</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800">
        <div class="max-w-6xl mx-auto px-4 py-8">
            <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">Report Viewer</h1>
                    <p class="text-slate-600">Search by mobile number, user ID, or control number.</p>
                </div>
                <a href="/" class="text-indigo-600 hover:text-indigo-500 font-medium">Back to Upload</a>
            </header>

            @if(!$report)
                <form method="GET" action="/reports" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Mobile Number</label>
                            <input name="mobile_number" value="{{ $filters['mobile_number'] }}" class="w-full px-3 py-2 border border-slate-300 rounded-md" placeholder="Enter mobile number" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">User ID</label>
                            <input name="user_id" value="{{ $filters['user_id'] }}" class="w-full px-3 py-2 border border-slate-300 rounded-md" placeholder="Enter user ID" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Control Number</label>
                            <input name="control_number" value="{{ $filters['control_number'] }}" class="w-full px-3 py-2 border border-slate-300 rounded-md" placeholder="Enter control number" />
                        </div>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button class="px-5 py-2 bg-indigo-600 text-white rounded-md">Search</button>
                        <a href="/reports" class="px-5 py-2 border border-slate-300 rounded-md">Clear</a>
                    </div>
                </form>
            @endif

            @if(count($results))
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8">
                    <h2 class="text-xl font-semibold mb-4">Results</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-slate-500">
                                <tr>
                                    <th class="py-2 pr-4">Report ID</th>
                                    <th class="py-2 pr-4">User ID</th>
                                    <th class="py-2 pr-4">Mobile</th>
                                    <th class="py-2 pr-4">Control #</th>
                                    <th class="py-2 pr-4">Report Type</th>
                                    <th class="py-2 pr-4">Score</th>
                                    <th class="py-2 pr-4">Processed Date</th>
                                    <th class="py-2 pr-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $row)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2 pr-4">{{ $row->report_id }}</td>
                                        <td class="py-2 pr-4">{{ $row->user_id }}</td>
                                        <td class="py-2 pr-4">{{ $row->mobile_number }}</td>
                                        <td class="py-2 pr-4">{{ $row->report_order_number }}</td>
                                        <td class="py-2 pr-4">{{ $row->score_type }}</td>
                                        <td class="py-2 pr-4">{{ $row->credit_score }}</td>
                                        @php
                                            $processedAt = $row->generated_at;
                                            if ($processedAt) {
                                                try {
                                                    $processedAt = \Carbon\Carbon::parse($processedAt)->format('d/m/Y H:i');
                                                } catch (\Exception $e) {
                                                    $processedAt = $row->generated_at;
                                                }
                                            }
                                        @endphp
                                        <td class="py-2 pr-4">{{ $processedAt }}</td>
                                        <td class="py-2 pr-4">
                                            <div class="flex items-center gap-3">
                                                <a class="text-indigo-600 hover:text-indigo-500" href="/reports/{{ $row->report_id }}">View</a>
                                                @if(!empty($isAdmin))
                                                    <form method="POST" action="/reports/{{ $row->report_id }}/delete" onsubmit="return confirm('Delete this report? This cannot be undone.');">
                                                        @csrf
                                                        <button type="submit" class="text-red-600 hover:text-red-500">Delete</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($report)
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
                        <div>
                            <h2 class="text-2xl font-semibold">Report #{{ $report->report_id }}</h2>
                            <p class="text-slate-600">Control #: {{ $report->report_order_number }} | Type: {{ $report->score_type }}</p>
                        </div>
                        <div class="text-sm text-slate-600">
                            <div>User ID: {{ $report->user_id }}</div>
                            <div>Mobile: {{ $report->mobile_number }}</div>
                            @php
                                $reportProcessedAt = $report->generated_at;
                                if ($reportProcessedAt) {
                                    try {
                                        $reportProcessedAt = \Carbon\Carbon::parse($reportProcessedAt)->format('d/m/Y H:i');
                                    } catch (\Exception $e) {
                                        $reportProcessedAt = $report->generated_at;
                                    }
                                }
                            @endphp
                            <div>Processed: {{ $reportProcessedAt }}</div>
                        </div>
                    </div>

                    <div class="border-b border-slate-200 mb-4">
                        <nav class="flex flex-wrap gap-3 text-sm" id="tab-nav">
                            <button data-tab="report" class="tab-btn px-3 py-2 rounded-md bg-indigo-600 text-white">Report Info</button>
                            <button data-tab="personal" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Personal</button>
                            <button data-tab="identifications" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Identifications</button>
                            <button data-tab="addresses" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Addresses</button>
                            <button data-tab="phones" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Phones</button>
                            <button data-tab="emails" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Emails</button>
                            <button data-tab="employment" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Employment</button>
                            <button data-tab="accounts" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Accounts</button>
                            <button data-tab="history" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Payment History</button>
                            <button data-tab="enquiries" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Enquiries</button>
                            <button data-tab="warnings" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Warnings</button>
                            <button data-tab="other" class="tab-btn px-3 py-2 rounded-md bg-slate-100">Other</button>
                        </nav>
                    </div>

                    <div class="tab-panel" id="tab-report">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div><span class="font-semibold">Score:</span> {{ $report->credit_score }}</div>
                            <div><span class="font-semibold">Processed Date:</span> {{ $reportProcessedAt }}</div>
                            <div><span class="font-semibold">Report Type:</span> {{ $report->score_type }}</div>
                            <div><span class="font-semibold">Control Number:</span> {{ $report->report_order_number }}</div>
                        </div>
                    </div>

                    <div class="tab-panel hidden" id="tab-personal">
                        @if($sections['personal'])
                            @php
                                $dobValue = $sections['personal']->date_of_birth ?? null;
                                $dobDisplay = $dobValue ?: 'N/A';
                                if ($dobValue) {
                                    try {
                                        $dobDisplay = \Carbon\Carbon::parse($dobValue)->format('d/m/Y');
                                    } catch (\Exception $e) {
                                        $dobDisplay = $dobValue;
                                    }
                                }
                            @endphp
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div><span class="font-semibold">Name:</span> {{ $sections['personal']->full_name ?: 'N/A' }}</div>
                                <div><span class="font-semibold">DOB:</span> {{ $dobDisplay }}</div>
                                <div><span class="font-semibold">Gender:</span> {{ $sections['personal']->gender ?: 'N/A' }}</div>
                                <div><span class="font-semibold">Occupation:</span> {{ $sections['personal']->occupation ?: 'N/A' }}</div>
                            </div>
                        @else
                            <p class="text-sm text-slate-500">No personal info found.</p>
                        @endif
                    </div>

                    <div class="tab-panel hidden" id="tab-identifications">
                        <div>
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-4">Seq</th>
                                        <th class="py-2 pr-4">Type</th>
                                        <th class="py-2 pr-4">ID Number</th>
                                        <th class="py-2 pr-4">Reported Date</th>
                                        <th class="py-2 pr-4">Issue Date</th>
                                        <th class="py-2 pr-4">Expiry Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($sections['identifications'] as $row)
                                        <tr class="border-t border-slate-100">
                                            <td class="py-2 pr-4">{{ $row->seq }}</td>
                                            <td class="py-2 pr-4">{{ $row->type_of_document }}</td>
                                            <td class="py-2 pr-4">{{ $row->id_number }}</td>
                                            <td class="py-2 pr-4">{{ $row->reported_date }}</td>
                                            <td class="py-2 pr-4">{{ $row->issue_date ?: 'N/A' }}</td>
                                            <td class="py-2 pr-4">{{ $row->expiry_date ?: 'N/A' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="py-2 text-slate-500" colspan="6">No identifications found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-panel hidden" id="tab-addresses">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-4">Seq</th>
                                        <th class="py-2 pr-4">Address</th>
                                        <th class="py-2 pr-4">Category</th>
                                        <th class="py-2 pr-4">Residence Code</th>
                                        <th class="py-2 pr-4">Reported Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($sections['addresses'] as $row)
                                        @php
                                            $category = 'N/A';
                                            $residenceCode = 'N/A';
                                            if (!empty($row->type)) {
                                                $parts = array_map('trim', explode('|', $row->type));
                                                if (!empty($parts[0])) {
                                                    $category = $parts[0];
                                                }
                                                if (!empty($parts[1])) {
                                                    $residenceCode = $parts[1];
                                                }
                                            }
                                        @endphp
                                        <tr class="border-t border-slate-100 align-top">
                                            <td class="py-2 pr-4">{{ $row->seq }}</td>
                                            <td class="py-2 pr-4">{{ $row->address }}</td>
                                            <td class="py-2 pr-4">{{ $category }}</td>
                                            <td class="py-2 pr-4">{{ $residenceCode }}</td>
                                            <td class="py-2 pr-4">{{ $row->reported_date }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="py-2 text-slate-500" colspan="5">No addresses found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-panel hidden" id="tab-phones">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-4">Seq</th>
                                        <th class="py-2 pr-4">Type</th>
                                        <th class="py-2 pr-4">Number</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($sections['phones'] as $row)
                                        <tr class="border-t border-slate-100">
                                            <td class="py-2 pr-4">{{ $row->seq }}</td>
                                            <td class="py-2 pr-4">{{ $row->type_label ?: $row->type_code }}</td>
                                            <td class="py-2 pr-4">{{ $row->number }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="py-2 text-slate-500" colspan="3">No phone records found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-panel hidden" id="tab-emails">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-4">Seq</th>
                                        <th class="py-2 pr-4">Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($sections['emails'] as $row)
                                        <tr class="border-t border-slate-100">
                                            <td class="py-2 pr-4">{{ $row->seq }}</td>
                                            <td class="py-2 pr-4">{{ $row->emai_address }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="py-2 text-slate-500" colspan="2">No emails found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-panel hidden" id="tab-employment">
                        @if(!empty($sections['employment']))
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div><span class="font-semibold">Account Type:</span> {{ $sections['employment']['AccountType'] ?? 'N/A' }}</div>
                                <div><span class="font-semibold">Date Reported:</span> {{ $sections['employment']['DateReported'] ?? 'N/A' }}</div>
                                <div><span class="font-semibold">Occupation:</span> {{ $sections['employment']['Occupation'] ?? 'N/A' }}</div>
                                <div><span class="font-semibold">Income:</span> {{ $sections['employment']['Income'] ?? 'N/A' }}</div>
                                <div><span class="font-semibold">Monthly/Annual Indicator:</span> {{ $sections['employment']['MonthlyAnnualIncomeIndicator'] ?? 'N/A' }}</div>
                                <div><span class="font-semibold">Net/Gross Indicator:</span> {{ $sections['employment']['NetGrossIncomeIndicator'] ?? 'N/A' }}</div>
                            </div>
                        @else
                            <p class="text-sm text-slate-500">No employment info found.</p>
                        @endif
                    </div>

                    <div class="tab-panel hidden" id="tab-accounts">
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
                            @endphp
                            <div class="min-w-full text-sm max-h-[70vh] overflow-y-auto border border-slate-200 rounded-lg">
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
                                        @forelse($sections['accounts'] as $row)
                                            @php
                                                $rowClass = $loop->index % 2 === 0 ? 'bg-white' : 'bg-slate-50';
                                            @endphp
                                            <tr class="{{ $rowClass }}">
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->seq) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">
                                                    <button type="button"
                                                        class="text-indigo-600 hover:underline"
                                                        onclick="window.__showAccountHistory('{{ $displayValue($row->seq) }}','{{ addslashes($displayValue($row->institution)) }}')">
                                                        {{ $displayValue($row->institution) }}
                                                    </button>
                                                </td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->account_number) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->account_type) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->ownership_type) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->balance) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->PastDueAmount) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->sanction_amount) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->high_credit) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->credit_limit) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->cash_limit_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->data_opened) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->date_closed) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->date_reported) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->amount_overdue_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->rate_of_interest_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->repayment_tenure_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->emi_amount_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->payment_frequency_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->actual_payment_amount_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->last_payment_date_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->collateral_value_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->collateral_type_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->suit_filed_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->credit_facility_status_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->written_off_total_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->written_off_principal_value) }}</td>
                                                <td class="py-2 px-3 border border-slate-200 align-top break-words">{{ $displayValue($row->settlement_amount_value) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td class="py-2 text-slate-500" colspan="30">No accounts found.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-panel hidden" id="tab-history">
                        @php
                            $monthNames = [
                                '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
                                '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
                                '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
                            ];
                        @endphp
                        <div class="mb-3 flex items-center gap-2 text-sm">
                            <span class="text-slate-500">Filter:</span>
                            <span id="historyFilterLabel" class="text-slate-700">All accounts</span>
                            <button id="historyFilterClear" type="button" class="ml-2 rounded border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">Clear</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm border-collapse">
                                <thead class="text-left text-slate-500 bg-slate-100">
                                    <tr>
                                        <th class="py-2 px-3 border border-slate-200">Account Seq</th>
                                        <th class="py-2 px-3 border border-slate-200">Member Name</th>
                                        <th class="py-2 px-3 border border-slate-200">Account #</th>
                                        <th class="py-2 px-3 border border-slate-200">Payment Start Date</th>
                                        <th class="py-2 px-3 border border-slate-200">Payment End Date</th>
                                        <th class="py-2 px-3 border border-slate-200">Month</th>
                                        <th class="py-2 px-3 border border-slate-200">Year</th>
                                        <th class="py-2 px-3 border border-slate-200">DPD</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($sections['history'] as $row)
                                        @php
                                            $acct = $accountById[$row->cir_account_id] ?? [];
                                            $key = (string) ($row->key ?? '');
                                            $monthLabel = $key;
                                            $yearLabel = '';
                                            if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\\s+(\\d{4})$/i', $key, $m)) {
                                                $monthLabel = ucfirst(strtolower($m[1]));
                                                $yearLabel = $m[2];
                                            } elseif (preg_match('/^(\\d{2})-(\\d{2})$/', $key, $m)) {
                                                $monthLabel = $monthNames[$m[1]] ?? $m[1];
                                                $yearLabel = '20' . $m[2];
                                            } elseif (preg_match('/^(\\d{2})-(\\d{4})$/', $key, $m)) {
                                                $monthLabel = $monthNames[$m[1]] ?? $m[1];
                                                $yearLabel = $m[2];
                                            } elseif (preg_match('/^(\\d{4})-(\\d{2})$/', $key, $m)) {
                                                $monthLabel = $monthNames[$m[2]] ?? $m[2];
                                                $yearLabel = $m[1];
                                            }
                                        @endphp
                                        <tr class="border-t border-slate-100 history-row" data-account-seq="{{ $acct['seq'] ?? '' }}">
                                            <td class="py-2 px-3 border border-slate-200">{{ $acct['seq'] ?? '' }}</td>
                                            <td class="py-2 px-3 border border-slate-200">
                                                <button type="button"
                                                    class="history-filter-btn text-indigo-600 hover:underline"
                                                    data-account-seq="{{ $acct['seq'] ?? '' }}"
                                                    data-account-name="{{ $acct['institution'] ?? ($row->institution ?? '') }}"
                                                    onclick="event.stopPropagation(); window.__filterHistory('{{ $acct['seq'] ?? '' }}','{{ addslashes($acct['institution'] ?? ($row->institution ?? '')) }}')">
                                                    {{ $acct['institution'] ?? ($row->institution ?? '') }}
                                                </button>
                                            </td>
                                            <td class="py-2 px-3 border border-slate-200">{{ $acct['account_number'] ?? ($row->account_number ?? '') }}</td>
                                            <td class="py-2 px-3 border border-slate-200">{{ $acct['payment_start'] ?? '' }}</td>
                                            <td class="py-2 px-3 border border-slate-200">{{ $acct['payment_end'] ?? '' }}</td>
                                            <td class="py-2 px-3 border border-slate-200">{{ $monthLabel }}</td>
                                            <td class="py-2 px-3 border border-slate-200">{{ $yearLabel }}</td>
                                            <td class="py-2 px-3 border border-slate-200">{{ $row->payment_status }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="py-2 text-slate-500" colspan="8">No payment history found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-panel hidden" id="tab-enquiries">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-4">Seq</th>
                                        <th class="py-2 pr-4">Institution</th>
                                        <th class="py-2 pr-4">Purpose</th>
                                        <th class="py-2 pr-4">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($sections['enquiries'] as $row)
                                        <tr class="border-t border-slate-100">
                                            <td class="py-2 pr-4">{{ $row->seq }}</td>
                                            <td class="py-2 pr-4">{{ $row->Institution }}</td>
                                            <td class="py-2 pr-4">{{ $row->RequestPurpose }}</td>
                                            <td class="py-2 pr-4">{{ $row->Date }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="py-2 text-slate-500" colspan="4">No enquiries found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-panel hidden" id="tab-warnings">
                        @if(!empty($sections['warnings']))
                            <ul class="text-sm text-slate-700 list-disc pl-5 space-y-2">
                                @foreach($sections['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500">No warnings found.</p>
                        @endif
                    </div>

                    <div class="tab-panel hidden" id="tab-other">
                        @if($sections['other_key_ind'])
                            <div class="text-sm">
                                <div><span class="font-semibold">Age Of Oldest Trade:</span> {{ $sections['other_key_ind']->AgeOfOldestTrade ?? '' }}</div>
                                <div><span class="font-semibold">Number Of Open Trades:</span> {{ $sections['other_key_ind']->NumberOfOpenTrades ?? '' }}</div>
                            </div>
                        @else
                            <p class="text-slate-500">No additional data found.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <script>
            const tabs = document.querySelectorAll('.tab-btn');
            const panels = document.querySelectorAll('.tab-panel');
            const activateTab = (target) => {
                const btn = Array.from(tabs).find((b) => b.dataset.tab === target);
                if (!btn) {
                    return;
                }
                tabs.forEach((b) => b.classList.remove('bg-indigo-600', 'text-white'));
                tabs.forEach((b) => b.classList.add('bg-slate-100'));
                btn.classList.add('bg-indigo-600', 'text-white');
                btn.classList.remove('bg-slate-100');
                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.id !== `tab-${target}`);
                });
            };

            tabs.forEach((btn) => {
                btn.addEventListener('click', () => activateTab(btn.dataset.tab));
            });

            const historyRows = Array.from(document.querySelectorAll('.history-row'));
            const historyFilterLabel = document.getElementById('historyFilterLabel');
            const historyFilterClear = document.getElementById('historyFilterClear');
            const historyFilterBtns = Array.from(document.querySelectorAll('.history-filter-btn'));

            const applyHistoryFilter = (seq, name) => {
                historyRows.forEach((row) => {
                    row.classList.toggle('hidden', row.dataset.accountSeq !== seq);
                });
                historyFilterLabel.textContent = name ? `${name} (Seq ${seq})` : `Seq ${seq}`;
            };

            const clearHistoryFilter = () => {
                historyRows.forEach((row) => row.classList.remove('hidden'));
                historyFilterLabel.textContent = 'All accounts';
            };

            historyFilterBtns.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const seq = btn.dataset.accountSeq || '';
                    const name = btn.dataset.accountName || '';
                    if (!seq) {
                        return;
                    }
                    applyHistoryFilter(seq, name);
                });
            });

            if (historyFilterClear) {
                historyFilterClear.addEventListener('click', clearHistoryFilter);
            }

            window.__filterHistory = (seq, name) => {
                if (!seq) {
                    return;
                }
                applyHistoryFilter(seq, name || '');
            };

            window.__showAccountHistory = (seq, name) => {
                activateTab('history');
                window.__filterHistory(seq, name || '');
            };
        </script>
    </body>
</html>
