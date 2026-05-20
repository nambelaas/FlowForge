<?php

namespace App\Console\Commands;

use App\Models\StepRun;
use App\Models\WorkflowRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class CheckWorkflowTimeout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:check-timeout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa dan membatalkan alur kerja yang melewati batas waktu (Global Timeout)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeoutMinutes = 15;
        $threshold = now()->subMinutes($timeoutMinutes);

        $expiredRuns = WorkflowRun::where('status', 'RUNNING')
            ->where('started_at', '<', $threshold)
            ->get();

        if ($expiredRuns->isEmpty()) {
            $this->info('Aman! Tidak ada alur kerja yang menggantung.');
            return 0;
        }

        foreach ($expiredRuns as $run) {
            $run->update([
                'status' => 'FAILED',
                'completed_at' => now()
            ]);

            StepRun::where('workflow_run_id', $run->id)
                ->whereIn('status', ['PENDING', 'RUNNING'])
                ->update([
                    'status' => 'FAILED',
                    'logs' => 'Execution terminated automatically by System Timeout Guard.'
                ]);

            $latestStep = StepRun::where('workflow_run_id', $run->id)->orderBy('started_at', 'desc')->first();
            if ($latestStep) {
                event(new \App\Events\WorkflowStepUpdated($latestStep, $run->tenant_id));
            }

            $this->warn("Workflow Run ID #{$run->id} berhasil dimatikan karena timeout.");
        }

        return 0;
    }
}
