<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_reports', function (Blueprint $table) {
            $table->longText('warnings_json')->nullable()->after('json_response');
        });
    }

    public function down(): void
    {
        Schema::table('credit_reports', function (Blueprint $table) {
            $table->dropColumn('warnings_json');
        });
    }
};
