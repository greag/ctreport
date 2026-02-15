<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OtpAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        $isAuthenticated = (bool) $request->session()->get('otp_authenticated', false);
        $authenticatedAt = $request->session()->get('otp_authenticated_at');
        $ttlMinutes = (int) env('OTP_SESSION_TTL_MINUTES', 30);

        if (!$isAuthenticated || !$authenticatedAt) {
            return redirect()->route('otp.login');
        }

        if (!($authenticatedAt instanceof \Illuminate\Support\Carbon)) {
            $authenticatedAt = \Illuminate\Support\Carbon::parse($authenticatedAt);
        }
        $elapsedSeconds = now()->diffInSeconds($authenticatedAt);
        if ($elapsedSeconds > ($ttlMinutes * 60)) {
            $request->session()->forget(['otp_authenticated', 'otp_authenticated_at', 'otp_phone']);
            return redirect()->route('otp.login')->withErrors([
                'otp' => 'Session expired. Please log in again.',
            ]);
        }

        return $next($request);
    }
}
