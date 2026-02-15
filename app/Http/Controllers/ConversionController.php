<?php

namespace App\Http\Controllers;

use App\Services\ExcelExporter;
use App\Services\IntegrityValidator;
use App\Services\ReportProcessor;
use App\Services\ReportStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversionController extends Controller
{
    public function process(Request $request, ReportProcessor $processor, ReportStorageService $storage)
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $validator = Validator::make($request->all(), [
            'user_id' => ['nullable', 'string'],
            'mobile_number' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'report_type' => ['nullable', 'string'],
            'overwrite' => ['nullable'],
            'pdf' => ['required', 'file', 'mimes:pdf'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (empty($validated['user_id']) && empty($validated['mobile_number'])) {
            return response()->json([
                'message' => 'User ID or mobile number is required.',
            ], 422);
        }
        $file = $request->file('pdf');
        $baseFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $path = $file->storeAs('uploads', Str::uuid() . '.pdf');

        try {
            $userId = $storage->resolveUserId(
                $validated['user_id'] ?? null,
                $validated['mobile_number'] ?? null
            );
            $result = $processor->process(storage_path('app/' . $path), $validated['password'] ?? null, $userId);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }

        $reportType = trim((string) ($validated['report_type'] ?? 'CIBIL'));
        $controlNumber = $result['structuredData']['InputResponse']['ReportInformation']['ControlNumber'] ?? '';
        $overwrite = filter_var($validated['overwrite'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($controlNumber !== '') {
            $existing = $storage->findExistingReport($reportType, (string) $controlNumber);
            if ($existing && !$overwrite) {
                return response()->json([
                    'message' => 'Report already exists for this report type and control number.',
                    'existing' => $existing,
                    'report_type' => $reportType,
                    'control_number' => (string) $controlNumber,
                    'user_id' => $userId,
                    'mobile_number' => $validated['mobile_number'] ?? null,
                ], 409);
            }
        }

        $storeResult = $storage->storeReport($result['structuredData'], $userId, $reportType, $overwrite);
        if (($storeResult['status'] ?? '') === 'duplicate') {
            return response()->json([
                'message' => 'Report already exists for this report type and control number.',
                'existing' => $storeResult['existing'] ?? null,
                'report_type' => $reportType,
                'control_number' => (string) $controlNumber,
                'user_id' => $userId,
                'mobile_number' => $validated['mobile_number'] ?? null,
            ], 409);
        }

        $token = (string) Str::uuid();
        $meta = [
            'fileName' => $baseFileName,
            'failedAccounts' => $result['failedAccounts'],
            'structuredData' => $result['structuredData'],
            'storage' => [
                'report_id' => $storeResult['report_id'] ?? null,
                'report_type' => $reportType,
                'control_number' => (string) $controlNumber,
                'user_id' => $userId,
                'mobile_number' => $validated['mobile_number'] ?? null,
            ],
        ];

        Storage::makeDirectory('results');
        Storage::put("results/{$token}.json", json_encode($meta, JSON_UNESCAPED_UNICODE));
        Storage::put("results/{$token}.txt", $result['extractedText']);

        return response()->json([
            'token' => $token,
            'fileName' => $baseFileName,
            'failedAccounts' => $result['failedAccounts'],
            'storage' => $meta['storage'],
        ]);
    }

    public function downloadText(string $token)
    {
        $meta = $this->loadMeta($token);
        $textPath = "results/{$token}.txt";
        if (!Storage::exists($textPath)) {
            abort(404, 'Text result not found.');
        }

        return response()->download(
            Storage::path($textPath),
            $this->buildDownloadName($meta, 'txt'),
            ['Content-Type' => 'text/plain']
        );
    }

    public function downloadJson(string $token)
    {
        $meta = $this->loadMeta($token);
        $payload = $meta['structuredData'] ?? [];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $this->buildDownloadName($meta, 'json') . '"',
        ]);
    }

    public function downloadExcel(string $token, ExcelExporter $exporter): StreamedResponse|BinaryFileResponse
    {
        $meta = $this->loadMeta($token);
        $payload = $meta['structuredData']['InputResponse'] ?? [];
        $tempPath = $exporter->createExcel($payload, $meta['fileName']);

        return response()->download(
            $tempPath,
            $this->buildDownloadName($meta, 'xlsx')
        )->deleteFileAfterSend(true);
    }

    public function metadata(string $token)
    {
        $meta = $this->loadMeta($token);
        return response()->json([
            'fileName' => $meta['fileName'] ?? 'credit_report',
            'failedAccounts' => $meta['failedAccounts'] ?? [],
        ]);
    }

    public function validateResult(string $token, IntegrityValidator $validator, ExcelExporter $exporter)
    {
        $meta = $this->loadMeta($token);
        $textPath = Storage::path("results/{$token}.txt");
        if (!Storage::exists("results/{$token}.txt")) {
            abort(404, 'Text result not found.');
        }

        $report = $validator->validate($meta, $textPath, $exporter);
        Storage::put("results/{$token}_validation.json", json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return response()->json($report);
    }

    private function loadMeta(string $token): array
    {
        $metaPath = "results/{$token}.json";
        if (!Storage::exists($metaPath)) {
            abort(404, 'Result not found.');
        }
        $meta = json_decode(Storage::get($metaPath), true);
        return is_array($meta) ? $meta : [];
    }

    private function buildDownloadName(array $meta, string $extension): string
    {
        $name = $this->extractCustomerName($meta);
        $timestamp = now()->format('Ymd_His');
        return "{$name}_{$timestamp}.{$extension}";
    }

    private function extractCustomerName(array $meta): string
    {
        $name = $meta['structuredData']['InputResponse']['PersonalInformation']['Name']
            ?? $meta['fileName']
            ?? 'Customer';

        $name = trim((string) $name);
        if ($name === '') {
            $name = 'Customer';
        }

        $name = preg_replace('/\s+/', '_', $name);
        $name = preg_replace('/[^A-Za-z0-9_\-]/', '', $name);
        return $name ?: 'Customer';
    }
}
