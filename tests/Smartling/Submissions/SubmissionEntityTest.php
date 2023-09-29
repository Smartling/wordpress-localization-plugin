<?php

namespace Smartling\Submissions;

use PHPUnit\Framework\TestCase;

class SubmissionEntityTest extends TestCase {
    /**
     * @dataProvider completionPercentageDataProvider
     */
    public function testCompletionPercentage(int $approvedStringCount, int $completedStringCount, int $excludedStringCount, int $expectedPercentage): void
    {
        $x = new SubmissionEntity();
        $x->setApprovedStringCount($approvedStringCount);
        $x->setCompletedStringCount($completedStringCount);
        $x->setExcludedStringCount($excludedStringCount);
        $x->setTotalStringCount($approvedStringCount + $excludedStringCount);

        $this->assertEquals($expectedPercentage, $x->getCompletionPercentage());
    }

    public function completionPercentageDataProvider(): array
    {
        return [
            [9, 0, 1, 0],
            [10, 0, 0, 0],
            [10, 5, 0, 50],
            [10, 5, 1, 50],
            [9, 9, 0, 100],
            [9, 9, 1, 100],
        ];
    }
}
