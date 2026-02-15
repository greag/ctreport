<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MobileLookupController extends Controller
{
    public function keyStatus()
    {
        $hasKey = trim((string) env('MOBILE_API_KEY', '')) !== '';
        $hasBaseUrl = trim((string) env('MOBILE_API_BASE_URL', '')) !== '';

        return response()->json([
            'mobile_api_key_loaded' => $hasKey,
            'mobile_api_base_url_loaded' => $hasBaseUrl,
        ]);
    }

    public function lookup(Request $request)
    {
        $mobile = preg_replace('/\D+/', '', (string) $request->input('mobile_number', ''));
        if ($mobile === '') {
            return response()->json(['message' => 'Mobile number is required.'], 422);
        }

        $baseUrl = rtrim((string) env('MOBILE_API_BASE_URL', 'http://165.22.216.162'), '/');
        $apiKey = (string) env('MOBILE_API_KEY', '');
        if ($apiKey === '') {
            return response()->json(['message' => 'Mobile API key is not configured.'], 500);
        }

        $url = $baseUrl . '/api/get-lead-detail';
        $candidatePhones = [$mobile];
        if (strlen($mobile) === 10) {
            $candidatePhones[] = '91' . $mobile;
        }

        foreach ($candidatePhones as $phone) {
            $response = Http::withHeaders(['api_key' => $apiKey])->asJson()->post($url, [
                'phone' => $phone,
            ]);

            if (!$response->ok() && $response->status() === 401) {
                $response = Http::withHeaders(['api_key' => $apiKey])->asForm()->post($url, [
                    'phone' => $phone,
                ]);
            }

            if (!$response->ok()) {
                $payload = $response->json();
                $message = $payload['data'] ?? $payload['message'] ?? null;
                if ($response->status() === 401 && $message) {
                    return response()->json(['message' => $message], 401);
                }
                continue;
            }

            $payload = $response->json();
            $status = $payload['status'] ?? $payload['success'] ?? null;
            if ($status === false) {
                continue;
            }

            $userId = $payload['user_id'] ?? null;
            $apiName = $payload['lead_name'] ?? null;
            if (!$userId && isset($payload['data'][0])) {
                $userId = $payload['data'][0]['user_id'] ?? $payload['data'][0]['lead_id'] ?? null;
                if (!$apiName) {
                    $apiName = $payload['data'][0]['lead_name'] ?? null;
                }
            }

            if ($userId) {
                $customerName = $apiName ?: $this->lookupCustomerName($phone);
                return response()->json([
                    'exists' => true,
                    'user_id' => $userId,
                    'mobile_number' => $phone,
                    'customer_name' => $customerName,
                ]);
            }
        }

        return response()->json(['message' => 'User does not exist.'], 404);
    }

    private function lookupCustomerName(string $phone): ?string
    {
        $candidates = [$phone];
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $candidates[] = substr($phone, 2);
        }
        foreach ($candidates as $candidate) {
            try {
                $row = DB::connection('ctlms')
                    ->table('users')
                    ->join('customers', 'customers.user_id', '=', 'users.id')
                    ->where('users.phone', $candidate)
                    ->select('customers.first_name', 'customers.last_name')
                    ->first();
                if ($row) {
                    $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                    return $name !== '' ? $name : null;
                }
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
}
