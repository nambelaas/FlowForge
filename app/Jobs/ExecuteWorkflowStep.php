<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use Exception;

class ExecuteWorkflowStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    // Tentukan nilai max retries dari requirement (misal diambil dari konfigurasi step)
    public $tries = 3;

    protected $step;
    protected $workflowRunId;

    public function __construct(array $step, $workflowRunId)
    {
        $this->step = $step;
        $this->workflowRunId = $workflowRunId;

        // Jika di dalam definisi DAG JSON ada custom max_retries, gunakan itu
        $this->tries = $step['retry_logic']['max_retries'] ?? 3;
    }

    /**
     * Tentukan Exponential Backoff (Jeda waktu antar percobaan yang semakin lama)
     */
    public function backoff()
    {
        // Contoh: Percobaan 1 = wait 2s, Percobaan 2 = wait 4s, Percobaan 3 = wait 8s
        return [2, 4, 8, 16];
    }

    public function handle()
    {
        // Keluar jika user membatalkan seluruh batch (atau terkena Global Timeout)
        if ($this->batch()->cancelled()) {
            return;
        }

        // 1. Catat status ke database: RUNNING
        $stepRun = StepRun::updateOrCreate(
            ['workflow_run_id' => $this->workflowRunId, 'step_id' => $this->step['id']],
            ['status' => 'RUNNING', 'started_at' => now()]
        );

        $startTime = microtime(true);

        try {
            // 2. Eksekusi berdasarkan Tipe Tugas (Task Types)
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

            // 3. Catat status Sukses
            $endTime = microtime(true);
            $stepRun->update([
                'status' => 'SUCCESS',
                'completed_at' => now(),
                'duration_ms' => round(($endTime - $startTime) * 1000)
            ]);

            $batch = $this->batch();
            if ($batch) {
                // Ambil data run induk untuk melihat struktur lengkap alur kerja
                $workflowRun = WorkflowRun::with('workflowVersion')->find($this->workflowRunId);
                if (!$workflowRun || !$workflowRun->workflowVersion) {
                    throw new Exception("Gagal memicu langkah berikutnya: Data run atau versi alur kerja tidak ditemukan.");
                }

                $allSteps = $workflowRun->workflowVersion->dag_definition['steps'];

                // Cari step apa saja yang bergantung pada step yang BARU SELESAI ini
                foreach ($allSteps as $nextStep) {
                    if (in_array($this->step['id'], $nextStep['depends_on'] ?? [])) {

                        // Cek apakah SEMUA dependensi dari nextStep ini sudah berstatus SUCCESS di database
                        $dependencies = $nextStep['depends_on'];
                        $completedDependenciesCount = StepRun::where('workflow_run_id', $this->workflowRunId)
                            ->whereIn('step_id', $dependencies)
                            ->where('status', 'SUCCESS')
                            ->count();

                        // Jika jumlah yang sukses SAMA DENGAN jumlah total yang dibutuhkan, berarti dia siap jalan!
                        if ($completedDependenciesCount === count($dependencies)) {
                            // Cek agar tidak menduplikasi eksekusi
                            $alreadyRun = StepRun::where('workflow_run_id', $this->workflowRunId)
                                ->where('step_id', $nextStep['id'])
                                ->exists();

                            if (!$alreadyRun) {
                                // Masukkan job baru ini ke dalam Batch yang sedang berjalan saat ini
                                $batch->add(new ExecuteWorkflowStep($nextStep, $this->workflowRunId));
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Log kesalahan ke database
            $stepRun->update(['logs' => "Error: " . $e->getMessage()]);

            // Jika ini adalah percobaan terakhir dan masih gagal, tandai status FAILED
            if ($this->attempts() >= $this->tries) {
                $stepRun->update(['status' => 'FAILED', 'completed_at' => now()]);

                // Batalkan seluruh rangkaian alur kerja jika ada satu step krusial yang gagal
                $this->batch()->cancel();
            }

            // Lempar kembali error agar Laravel Queue tahu bahwa Job ini gagal dan perlu di-retry
            throw $e;
        }
    }
}
