<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cir_phone_info MODIFY number VARCHAR(20) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cir_phone_info MODIFY number VARCHAR(12) NOT NULL');
    }
};
