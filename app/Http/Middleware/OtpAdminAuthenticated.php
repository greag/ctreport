<?php

namespace App\Http\Middleware;

use App\Models\EmployeeDirectory;
use Closure;
use Illuminate\Http\Request;

class OtpAdminAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        $isAuthenticated = (bool) $request->session()->get('otp_authenticated', false);
        $phone = (string) $request->session()->get('otp_phone', '');

        if (!$isAuthenticated || $phone === '') {
            return $this->deny($request);
        }

        $isAdmin = EmployeeDirectory::query()
            ->where('mobile_number', $phone)
            ->where('is_active', true)
            ->where('is_admin', true)
            ->exists();

        if (!$isAdmin) {
            return $this->deny($request);
        }

        return $next($request);
    }

    private function deny(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        return redirect()->route('otp.login')->withErrors([
            'otp' => 'Admin access required.',
        ]);
    }
}
