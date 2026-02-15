<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDirectory extends Model
{
    protected $table = 'employee_directory';

    protected $fillable = [
        'name',
        'mobile_number',
        'is_active',
        'is_admin',
    ];
}
