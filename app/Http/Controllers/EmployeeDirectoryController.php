<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDirectory;
use Illuminate\Http\Request;

class EmployeeDirectoryController extends Controller
{
    public function index()
    {
        $employees = EmployeeDirectory::query()->orderBy('name')->get();
        return view('employee-directory', [
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:20', 'unique:employee_directory,mobile_number'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        EmployeeDirectory::create([
            'name' => $validated['name'],
            'mobile_number' => preg_replace('/\D+/', '', $validated['mobile_number']),
            'is_active' => true,
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ]);

        return redirect()->route('employees.index')->with('status', 'Employee added.');
    }

    public function toggle(EmployeeDirectory $employee)
    {
        $employee->is_active = !$employee->is_active;
        $employee->save();

        return redirect()->route('employees.index')->with('status', 'Employee status updated.');
    }

    public function toggleAdmin(EmployeeDirectory $employee)
    {
        $employee->is_admin = !$employee->is_admin;
        $employee->save();

        return redirect()->route('employees.index')->with('status', 'Employee admin access updated.');
    }
}
