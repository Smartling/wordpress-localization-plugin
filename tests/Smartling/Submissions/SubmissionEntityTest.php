<?php

namespace Smartling\Submissions;

use PHPUnit\Framework\TestCase;

class SubmissionEntityTest extends TestCase {
    /**
     * @dataProvider completionPercentageDataProvider
     */
    public function testCompletionPercentage(int $totalStringCount, int $approvedStringCount, int $completedStringCount, int $excludedStringCount, int $expectedPercentage, string $description = ''): void
    {
        $x = new SubmissionEntity();
        $x->setApprovedStringCount($approvedStringCount);
        $x->setCompletedStringCount($completedStringCount);
        $x->setExcludedStringCount($excludedStringCount);
        $x->setTotalStringCount($totalStringCount);

        $this->assertEquals($expectedPercentage, $x->getCompletionPercentage(), $description);
    }

    public function completionPercentageDataProvider(): array
    {
        return [
            [10, 4, 0, 1, 0, "4 strings are ready for translation but translator didn't start yet"],
            [10, 4, 5, 1, 55],
            [10, 2, 5, 1, 55, '2 strings are waiting for translation and 2 more for authorization'],
            [10, 0, 5, 1, 55, '4 strings are waiting for decision (authorize\exclude)'],
            [10, 0, 9, 0, 90, 'File was uploaded without authorization. User will do this later or will add file to job'],
            [10, 0, 0, 0, 0, 'File was uploaded without authorization. User will do this later or will add file to job'],
            [10, 0, 0, 10, 100, 'Nothing to translate, user excluded all content'],
            [1000000, 0, 999999, 0, 99, 'Must return 99% even if 99.9999% translated'],
        ];
    }
}
