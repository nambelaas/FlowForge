<?php

namespace App\Console\Commands;

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
        $timeoutMinutes = 30;
        $threshold = now()->subMinutes($timeoutMinutes);

        $expiredRuns = WorkflowRun::where('status', 'RUNNING')
            ->where('started_at', '<', $threshold)
            ->get();

        foreach ($expiredRuns as $run) {
            if ($run->batch_id) {
                $batch = Bus::findBatch($run->batch_id);
                if ($batch) {
                    $batch->cancel();
                }
            }

            DB::table('workflow_run')->where('id', $run->id)->update([
                'status' => 'FAILED',
                'completed_at' => now()
            ]);

            $this->info("Workflow Run ID {$run->id} berhasil dihentikan karena Global Timeout.");
        }

        return Command::SUCCESS;
    }
}
