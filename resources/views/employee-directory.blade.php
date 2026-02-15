<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Employee Directory</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800">
        <div class="max-w-5xl mx-auto px-4 py-10">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">Employee Directory</h1>
                    <p class="mt-2 text-sm text-slate-600">Add and manage employees allowed to use OTP login.</p>
                </div>
                <form method="POST" action="/logout">
                    @csrf
                    <button type="submit" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-100 transition">
                        Logout
                    </button>
                </form>
            </div>

            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 mb-8">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Add Employee</h2>
                <form method="POST" action="{{ route('employees.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Name</label>
                        <input name="name" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Mobile Number</label>
                        <input name="mobile_number" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="is_admin" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Admin access
                        </label>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                            Add Employee
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left">ID</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Mobile</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Admin</th>
                            <th class="px-4 py-3 text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($employees as $employee)
                            <tr>
                                <td class="px-4 py-3">{{ $employee->id }}</td>
                                <td class="px-4 py-3">{{ $employee->name }}</td>
                                <td class="px-4 py-3">{{ $employee->mobile_number }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $employee->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $employee->is_admin ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $employee->is_admin ? 'Admin' : 'User' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-2">
                                        <form method="POST" action="{{ route('employees.toggle', $employee) }}">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-800">
                                                {{ $employee->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('employees.toggleAdmin', $employee) }}">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-800">
                                                {{ $employee->is_admin ? 'Remove Admin' : 'Make Admin' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-slate-500">No employees added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </body>
</html>
