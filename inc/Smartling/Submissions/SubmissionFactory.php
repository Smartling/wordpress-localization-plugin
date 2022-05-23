<?php

namespace Smartling\Submissions;

class SubmissionFactory
{
    public function fromArray(array $array): SubmissionEntity
    {
        $result = new SubmissionEntity();
        foreach ($array as $key => $value) {
            $result->$key = $value;
        }
        $result->fixInitialValues();

        return $result;
    }
}
