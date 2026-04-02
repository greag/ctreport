<?php

namespace App\Http\Controllers;

use App\Services\ExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAccountsController extends Controller
{
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

        $query = DB::table('cir_retail_account_details');
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

        $query = DB::table('cir_retail_account_details');
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
}

