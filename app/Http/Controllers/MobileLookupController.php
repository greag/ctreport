<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MobileLookupController extends Controller
{
    public function keyStatus()
    {
        $hasKey = trim((string) config('services.mobile_api.key', '')) !== '';
        $hasBaseUrl = trim((string) config('services.mobile_api.base_url', '')) !== '';

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

        $baseUrl = rtrim((string) config('services.mobile_api.base_url', ''), '/');
        $apiKey = (string) config('services.mobile_api.key', '');
        if ($baseUrl === '') {
            return response()->json(['message' => 'Mobile API base URL is not configured.'], 500);
        }

        $candidatePhones = [$mobile];
        if (strlen($mobile) === 10) {
            $candidatePhones[] = '91' . $mobile;
        }

        foreach ($candidatePhones as $phone) {
            $response = null;
            $payload = null;
            $userId = null;
            $apiName = null;

            if ($apiKey !== '') {
                $response = Http::withHeaders(['api_key' => $apiKey])->asJson()->post(
                    $baseUrl . '/api/get-lead-detail',
                    ['phone' => $phone]
                );

                if (!$response->ok() && $response->status() === 401) {
                    $response = Http::withHeaders(['api_key' => $apiKey])->asForm()->post(
                        $baseUrl . '/api/get-lead-detail',
                        ['phone' => $phone]
                    );
                }

                if ($response->ok()) {
                    $payload = $response->json();
                } else {
                    $payload = $response ? $response->json() : null;
                    $message = is_array($payload) ? ($payload['data'] ?? $payload['message'] ?? null) : null;
                    if ($response && $response->status() === 401 && $message) {
                        $response = null;
                    }
                }
            }

            if (!$response || !$response->ok()) {
                $response = Http::get($baseUrl . '/api/check-ctlead', [
                    'mobile_number' => $phone,
                ]);
                if ($response->ok()) {
                    $payload = $response->json();
                } else {
                    $payload = $response->json();
                    $message = is_array($payload) ? ($payload['data'] ?? $payload['message'] ?? null) : null;
                    if ($response->status() === 401 && $message) {
                        return response()->json(['message' => $message], 401);
                    }
                    continue;
                }
            }

            $status = $payload['status'] ?? $payload['success'] ?? null;
            if ($status === false) {
                continue;
            }

            [$userId, $apiName] = $this->extractLeadDetails($payload);

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

    private function extractLeadDetails(array $payload): array
    {
        $userId = $payload['user_id'] ?? $payload['lead_id'] ?? $payload['data']['user_id'] ?? $payload['data']['lead_id'] ?? null;
        $apiName = $payload['lead_name'] ?? $payload['data']['lead_name'] ?? null;

        if (!$userId && isset($payload['data'][0]) && is_array($payload['data'][0])) {
            $userId = $payload['data'][0]['user_id'] ?? $payload['data'][0]['lead_id'] ?? null;
            if (!$apiName) {
                $apiName = $payload['data'][0]['lead_name'] ?? null;
            }
        }

        return [$userId, $apiName];
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
