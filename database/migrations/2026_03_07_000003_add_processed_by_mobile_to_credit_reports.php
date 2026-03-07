<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('credit_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_reports', 'processed_by_mobile')) {
                $table->string('processed_by_mobile', 20)->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_reports', function (Blueprint $table) {
            if (Schema::hasColumn('credit_reports', 'processed_by_mobile')) {
                $table->dropColumn('processed_by_mobile');
            }
        });
    }
};
