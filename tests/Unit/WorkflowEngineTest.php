<?php

namespace Tests\Unit;

use App\Services\WorkflowEngine;
use Exception;
use PHPUnit\Framework\TestCase;

class WorkflowEngineTest extends TestCase
{
    public function test_it_can_topologically_sort_a_valid_dag()
    {
        $engine = new WorkflowEngine();

        $steps = [
            ['id' => 'step_2', 'type' => 'SCRIPT', 'depends_on' => ['step_1']],
            ['id' => 'step_1', 'type' => 'HTTP', 'depends_on' => []],
            ['id' => 'step_3', 'type' => 'DELAY', 'depends_on' => ['step_1']],
        ];

        $sorted = $engine->validateAndSort($steps);

        // Memastikan step_1 berada di urutan pertama karena tidak punya dependensi
        $this->assertEquals('step_1', $sorted[0]['id']);
    }

    public function test_it_throws_exception_on_circular_dependency()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Circular dependency detected!");

        $engine = new WorkflowEngine();

        // A butuh B, B butuh A (Muter)
        $steps = [
            ['id' => 'step_A', 'type' => 'HTTP', 'depends_on' => ['step_B']],
            ['id' => 'step_B', 'type' => 'SCRIPT', 'depends_on' => ['step_A']],
        ];

        $engine->validateAndSort($steps);
    }
}
