<?php

namespace Tests\Unit;

use App\Contracts\PlanStepHandler;
use PHPUnit\Framework\TestCase;

class PlanConfigTest extends TestCase
{
    private function steps(): array
    {
        return (require __DIR__.'/../../config/seo_plan.php')['steps'];
    }

    public function test_step_keys_are_unique(): void
    {
        $keys = array_column($this->steps(), 'key');
        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    public function test_every_handler_implements_the_contract(): void
    {
        foreach ($this->steps() as $step) {
            $this->assertTrue(
                is_subclass_of($step['handler'], PlanStepHandler::class),
                "{$step['key']} handler must implement PlanStepHandler",
            );
        }
    }

    public function test_plan_starts_with_competitor_analysis(): void
    {
        $this->assertSame('competitor_analysis', $this->steps()[0]['key']);
    }
}
