<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeDirectory;

class UiController extends Controller
{
    public function index(Request $request)
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $phone = (string) $request->session()->get('otp_phone', '');
        $isAdmin = false;
        if ($phone !== '') {
            $isAdmin = EmployeeDirectory::query()
                ->where('mobile_number', $phone)
                ->where('is_active', true)
                ->where('is_admin', true)
                ->exists();
        }
        return view('app', [
            'apiBaseUrl' => rtrim($baseUrl, '/'),
            'isAdmin' => $isAdmin,
        ]);
    }
}
