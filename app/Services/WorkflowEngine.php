<?php

namespace App\Services;

use App\Jobs\ExecuteWorkflowStep;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use Exception;
use Illuminate\Support\Facades\Bus;
use Throwable;

class WorkflowEngine
{
    /**
     * Parse, validasi, dan urutkan langkah-langkah berdasarkan ketergantungan (Topological Sort)
     */
    public function validateAndSort(array $steps): array
    {
        // 1. Membuat Data List & Menghitung In-Degree (jumlah ketergantungan)
        $dataList = [];
        $inDegree = [];
        $stepMap = [];

        foreach ($steps as $step) {
            $id = $step['id'];
            $stepMap[$id] = $step;
            $dataList[$id] = [];
            $inDegree[$id] = 0;
        }

        foreach ($steps as $step) {
            $id = $step['id'];

            foreach ($step['depends_on'] ?? [] as $dependency) {
                $dataList[$dependency][] = $id;
                $inDegree[$id]++;
            }
        }

        // 2. Gunakan Kahn's Algorithm (BFS) untuk Topological Sort
        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        $sortedOrder = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sortedOrder[] = $current;

            foreach ($dataList[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // 3. Validasi: Jika hasil sort tidak sama dengan total step, berarti ada Circular Dependency!
        if (count($sortedOrder) !== count($steps)) {
            throw new Exception("Circular dependency detected! Alur kerja Anda memiliki perulangan/looping yang dilarang.");
        }

        // Kembalikan array step yang sudah berurutan
        return array_map(fn($id) => $stepMap[$id], $sortedOrder);
    }

    /**
     * Memicu eksekusi alur kerja
     */
    public function execute($workflowVersion, $tenantId)
    {
        $steps = $workflowVersion->dag_definition['steps'];
        $sortedSteps = $this->validateAndSort($steps);

        // 1. Buat record awal di Database
        $workflowRun = WorkflowRun::create([
            'workflow_version_id' => $workflowVersion->id,
            'tenant_id' => $tenantId,
            'status' => 'RUNNING',
            'started_at' => now()
        ]);

        // 2. Inisialisasi SEMUA step_runs ke database dengan status 'PENDING' Ini memastikan tabel step_runs terisi sejak awal alur kerja dipicu!
        foreach ($sortedSteps as $step) {
            StepRun::create([
                'workflow_run_id' => $workflowRun->id,
                'step_id' => $step['id'],
                'type' => $step['type'],
                'status' => 'PENDING',
                'logs' => 'Waiting for dependencies to complete...',
            ]);
        }

        // 3. Ambil semua step yang tidak punya dependensi (In-Degree = 0)
        $initialJobs = [];
        foreach ($sortedSteps as $step) {
            if (empty($step['depends_on'])) {
                $initialJobs[] = new ExecuteWorkflowStep($step, $workflowRun->id, $tenantId);
            }
        }

        // 4. Jika tidak ada step awal yang valid, gagalkan langsung
        if (empty($initialJobs)) {
            $workflowRun->update(['status' => 'FAILED', 'completed_at' => now()]);
            throw new Exception("Format DAG Salah: Tidak ditemukan langkah awal tanpa dependensi.");
        }

        // 5. Bungkus ke dalam Laravel Batch
        $batch = Bus::batch($initialJobs)
            ->then(function ($batch) use ($workflowRun) {
                // Semua step sukses berjalan tanpa interupsi
                $workflowRun->update(['status' => 'SUCCESS', 'completed_at' => now()]);
            })
            ->catch(function ($batch, Throwable $e) use ($workflowRun) {
                // Terjadi kegagalan di salah satu step atau dibatalkan karena timeout
                $workflowRun->update(['status' => 'FAILED', 'completed_at' => now()]);
            })
            ->dispatch();

        // 4. SIMPAN batch_id ke database untuk pelacakan Global Timeout nanti
        $workflowRun->update(['batch_id' => $batch->id]);
    }
}
