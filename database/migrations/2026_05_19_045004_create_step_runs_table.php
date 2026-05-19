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
        Schema::create('step_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->onDelete('cascade');
            $table->string('step_id');
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->longText('logs')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamp('started_at', 0)->nullable();
            $table->timestamp('completed_at', 0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('step_runs');
    }
};
