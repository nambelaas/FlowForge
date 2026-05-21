<?php

namespace App\Jobs;

use App\Models\StepRun;
use App\Models\WorkflowRun;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExecuteWorkflowStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $tries = 3;

    protected $step;
    protected $workflowRunId;

    protected $tenantId;

    public function __construct(array $step, $workflowRunId, string $tenantId)
    {
        $this->step = $step;
        $this->workflowRunId = $workflowRunId;
        $this->tenantId = $tenantId;
        $this->tries = $step['retry_logic']['max_retries'] ?? 3;
    }

    /**
     * Tentukan Exponential Backoff (Jeda waktu antar percobaan yang semakin lama)
     */
    public function backoff()
    {
        return [1, 1, 2];
    }

    public function handle()
    {
        $log = Log::channel('workflow');

        $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Just in to handle');

        $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Start updating step run');

        $updated = StepRun::updateOrCreate(
            ['workflow_run_id' => $this->workflowRunId, 'step_id' => $this->step['id']],
            ['status' => 'RUNNING', 'started_at' => now()]
        );

        event(new \App\Events\WorkflowStepUpdated($updated, $this->tenantId));

        $stepRun = StepRun::where('workflow_run_id', $this->workflowRunId)
            ->where('step_id', $this->step['id'])
            ->first();

        $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Sending event for step run update');

        $startTime = microtime(true);

        try {
            switch ($this->step['type']) {
                case 'HTTP':
                    $response = Http::timeout(30)->get($this->step['action']);
                    if ($response->failed()) {
                        throw new Exception("HTTP Request failed with status: " . $response->status());
                    }
                    $stepRun->update(['logs' => $response->body()]);
                    break;

                case 'SCRIPT':
                    $output = "Script " . $this->step['action'] . " executed successfully.";
                    $stepRun->update(['logs' => $output]);
                    break;

                case 'DELAY':
                    $duration = $this->step['duration'] ?? 5;
                    sleep($duration);
                    $stepRun->update(['logs' => "Delayed for {$duration} seconds."]);
                    break;

                case 'CONDITIONAL':
                    $stepRun->update(['logs' => "Condition evaluated."]);
                    break;
            }

            $endTime = microtime(true);
            $stepRun->update([
                'status' => 'SUCCESS',
                'completed_at' => now(),
                'duration_ms' => round(($endTime - $startTime) * 1000)
            ]);
            event(new \App\Events\WorkflowStepUpdated($stepRun, $this->tenantId));

            $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Checking for next steps to trigger...');
            $workflowRun = WorkflowRun::with('workflowVersion')->find($this->workflowRunId);
            if (!$workflowRun || !$workflowRun->workflowVersion) {
                throw new Exception("Gagal memicu langkah berikutnya: Data run atau versi alur kerja tidak ditemukan.");
            }

            $allSteps = $workflowRun->workflowVersion->dag_definition['steps'];

            foreach ($allSteps as $nextStep) {
                $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Evaluating next step: ' . $nextStep['id'] . ' with step id: ' . $this->step['id'] . ', data: ' . json_encode($nextStep));
                if (in_array($this->step['id'], $nextStep['depends_on'] ?? [])) {
                    $dependencies = $nextStep['depends_on'] ?? [];
                    $allDependenciesMet = true;

                    if (!empty($dependencies)) {
                        $successfulDependenciesCount = StepRun::where('workflow_run_id', $this->workflowRunId)
                            ->whereIn('step_id', $dependencies)
                            ->where('status', 'SUCCESS')
                            ->count();

                        if ($successfulDependenciesCount !== count($dependencies)) {
                            $allDependenciesMet = false;
                        }
                    }

                    $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Semua dependensi sudah terpenuhi');

                    if ($allDependenciesMet) {
                        $affectedRows = StepRun::where('workflow_run_id', $this->workflowRunId)
                            ->where('step_id', $nextStep['id'])
                            ->where('status', 'PENDING')
                            ->update([
                                'status' => 'RUNNING',
                                'started_at' => now(),
                                'logs' => 'Memulai eksekusi langkah berdependensi secara aman...'
                            ]);

                        if ($affectedRows > 0) {
                            $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Berhasil merebut hak eksekusi untuk step: ' . $nextStep['id']);

                            $nextStepRun = StepRun::where('workflow_run_id', $this->workflowRunId)
                                ->where('step_id', $nextStep['id'])
                                ->first();

                            event(new \App\Events\WorkflowStepUpdated($nextStepRun, $this->tenantId));

                            $cleanNextStep = json_decode(json_encode($nextStep), true);
                            dispatch(new ExecuteWorkflowStep($cleanNextStep, $this->workflowRunId, $this->tenantId));
                        } else {
                            $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Step ' . $nextStep['id'] . ' sudah diambil alih oleh worker lain.');
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $currentAttempt = $this->attempts();
            $logMessage = "Attempt #{$currentAttempt} Gagal. Error: " . $e->getMessage();

            $existingLogs = $stepRun->logs ? $stepRun->logs . "\n" : "";
            $stepRun->update(['logs' => $existingLogs . $logMessage]);

            if ($currentAttempt >= $this->tries) {
                try {
                    $aiService = app()->make(\App\Services\WorkflowAIService::class);
                    $analysis = $aiService->analyzeFailure($this->step['type'], $logMessage);
                } catch (\Exception $aiException) {
                    $analysis = "Gagal memicu analisis AI.";
                }

                $stepRun->update([
                    'status' => 'FAILED',
                    'ai_analysis' => $analysis,
                    'completed_at' => now()
                ]);
                event(new \App\Events\WorkflowStepUpdated($stepRun, $this->tenantId));

                WorkflowRun::where('id', $this->workflowRunId)->update([
                    'status' => 'FAILED',
                    'completed_at' => now()
                ]);
            }

            $log->info(pathinfo(__FILE__, PATHINFO_FILENAME) . ', Line: ' . __LINE__ . ' Error occurred: ' . $e->getMessage());

            throw $e;
        }
    }
}
