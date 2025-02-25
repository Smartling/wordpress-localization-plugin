<?php

namespace Smartling\Models;

use PHPUnit\Framework\TestCase;
use Smartling\Submissions\SubmissionEntity;

class UploadQueueItemTest extends TestCase {

    public function testRemoveSubmission()
    {
        $s1 = new SubmissionEntity();
        $s1->setId(1);
        $s2 = new SubmissionEntity();
        $s2->setId(2);
        $x = new UploadQueueItem([$s1, $s2], '', new IntStringPairCollection([new IntStringPair(1, 'a'), new IntStringPair(2, 'b')]));
        foreach ($x->submissions as $submission) {
            if ($submission->getId() === 1) {
                $x = $x->removeSubmission($submission);
            }
        }
        $this->assertCount(1, $x->submissions);
        $this->assertEquals(2, ($x->submissions)[0]->getId());
        $this->assertCount(1, $x->smartlingLocales->getArray());
        $intStringPair = $x->smartlingLocales->getArray()[0];
        $this->assertEquals('b', $intStringPair->value);
    }

    public function testSubmissionsAltered()
    {
        $s1 = new SubmissionEntity();
        $s1->setId(1);
        $s2 = new SubmissionEntity();
        $s2->setId(2);
        $x = new UploadQueueItem([$s1, $s2], '', new IntStringPairCollection([new IntStringPair(1, 'a'), new IntStringPair(2, 'b')]));
        foreach ($x->submissions as $submission) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
        }
        foreach ($x->submissions as $submission) {
            $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_COMPLETED, $submission->getStatus());
        }
    }
}
