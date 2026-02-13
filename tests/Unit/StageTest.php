<?php

declare(strict_types=1);

namespace CrmStages\Tests\Unit;

use CrmStages\Domain\Stage;
use PHPUnit\Framework\TestCase;

/**
 * Тесты enum Stage — порядок, переходы, метки.
 */
final class StageTest extends TestCase
{
    public function testAllStagesHaveUniqueOrder(): void
    {
        $orders = [];
        foreach (Stage::cases() as $stage) {
            if ($stage === Stage::Null) {
                continue; // Null — особый случай (-1)
            }
            $this->assertNotContains($stage->order(), $orders, "Duplicate order for {$stage->value}");
            $orders[] = $stage->order();
        }
    }

    public function testNextStageSequence(): void
    {
        // C0 → C1 → C2 → W1 → W2 → W3 → H1 → H2 → A1 → null
        $expected = [
            [Stage::Ice,         Stage::Touched],
            [Stage::Touched,     Stage::Aware],
            [Stage::Aware,       Stage::Interested],
            [Stage::Interested,  Stage::DemoPlanned],
            [Stage::DemoPlanned, Stage::DemoDone],
            [Stage::DemoDone,    Stage::Committed],
            [Stage::Committed,   Stage::Customer],
            [Stage::Customer,    Stage::Activated],
            [Stage::Activated,   null],
        ];

        foreach ($expected as [$stage, $next]) {
            $this->assertSame($next, $stage->next(), "Wrong next for {$stage->value}");
        }
    }

    public function testFromString(): void
    {
        $this->assertSame(Stage::Ice, Stage::from('C0'));
        $this->assertSame(Stage::DemoPlanned, Stage::from('W2'));
        $this->assertSame(Stage::Activated, Stage::from('A1'));
    }

    public function testLabelsAreNotEmpty(): void
    {
        foreach (Stage::cases() as $stage) {
            $this->assertNotEmpty($stage->label(), "Empty label for {$stage->value}");
        }
    }
}
