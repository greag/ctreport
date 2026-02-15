<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeDirectory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OtpAuthController extends Controller
{
    public function show(Request $request)
    {
        if ($request->session()->get('otp_authenticated')) {
            return redirect('/');
        }

        return view('otp-login');
    }

    public function send(Request $request)
    {
        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', ''));
        if ($phone === '') {
            return response()->json(['message' => 'Mobile number is required.'], 422);
        }

        $throttleKey = 'otp_send_' . $phone;
        if (Cache::has($throttleKey)) {
            return response()->json(['message' => 'Please wait 2 minutes before requesting a new OTP.'], 429);
        }

        if (!$this->isEmployeePhone($phone)) {
            return response()->json(['message' => 'User is not authorized.'], 403);
        }

        if ($this->isLocalEnv()) {
            Cache::put($throttleKey, true, now()->addMinutes(2));
            $request->session()->put('otp_phone', $phone);
            return response()->json(['message' => 'OTP sent successfully (local default).']);
        }

        $baseUrl = rtrim((string) env('TUBELIGHT_BASE_URL', 'http://165.22.216.162'), '/');
        $apiKey = (string) env('TUBELIGHT_API_KEY', '');

        $client = Http::asJson();
        if ($apiKey !== '') {
            $client = $client->withHeaders(['api_key' => $apiKey]);
        }

        $response = $client->post($baseUrl . '/api/send-otp', [
            'phone' => $phone,
        ]);

        if (!$response->ok()) {
            $payload = $response->json();
            $message = $payload['data'] ?? $payload['message'] ?? 'Failed to send OTP.';
            return response()->json(['message' => $message], 400);
        }

        Cache::put($throttleKey, true, now()->addMinutes(2));
        $request->session()->put('otp_phone', $phone);

        return response()->json(['message' => 'OTP sent successfully.']);
    }

    public function verify(Request $request)
    {
        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', ''));
        $otp = trim((string) $request->input('otp', ''));
        if ($phone === '' || $otp === '') {
            return response()->json(['message' => 'Phone and OTP are required.'], 422);
        }

        if ($this->isLocalEnv()) {
            if ($otp !== '1980') {
                return response()->json(['message' => 'OTP verification failed.'], 400);
            }
            $request->session()->put('otp_authenticated', true);
            $request->session()->put('otp_authenticated_at', now());
            $request->session()->put('otp_phone', $phone);
            return response()->json(['message' => 'OTP verified.']);
        }

        $baseUrl = rtrim((string) env('TUBELIGHT_BASE_URL', 'http://165.22.216.162'), '/');
        $apiKey = (string) env('TUBELIGHT_API_KEY', '');

        $client = Http::asJson();
        if ($apiKey !== '') {
            $client = $client->withHeaders(['api_key' => $apiKey]);
        }

        $response = $client->post($baseUrl . '/api/verify-otp', [
            'phone' => $phone,
            'otp' => $otp,
        ]);

        if (!$response->ok()) {
            $payload = $response->json();
            $message = $payload['data'] ?? $payload['message'] ?? 'OTP verification failed.';
            return response()->json(['message' => $message], 400);
        }

        $payload = $response->json();
        $success = $payload['success'] ?? $payload['status'] ?? true;
        if ($success === false) {
            $message = $payload['data'] ?? $payload['message'] ?? 'OTP verification failed.';
            return response()->json(['message' => $message], 400);
        }

        $request->session()->put('otp_authenticated', true);
        $request->session()->put('otp_authenticated_at', now());
        $request->session()->put('otp_phone', $phone);

        return response()->json(['message' => 'OTP verified.']);
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['otp_authenticated', 'otp_authenticated_at', 'otp_phone']);
        return redirect()->route('otp.login');
    }

    private function isEmployeePhone(string $phone): bool
    {
        return EmployeeDirectory::query()
            ->where('mobile_number', $phone)
            ->where('is_active', true)
            ->exists();
    }

    private function isLocalEnv(): bool
    {
        return app()->environment('local');
    }
}
