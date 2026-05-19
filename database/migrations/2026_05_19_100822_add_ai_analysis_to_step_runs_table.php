<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('step_runs', function (Blueprint $table) {
            $table->text('ai_analysis')->nullable()->after('logs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('step_runs', function (Blueprint $table) {
            $table->dropColumn('ai_analysis');
        });
    }
};
