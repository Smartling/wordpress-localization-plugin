<?php

namespace Smartling\Replacers;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Submissions\SubmissionManager;

class ReplacerFactory
{
    private SubmissionManager $submissionManager;

    public function __construct(SubmissionManager $submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function getReplacer(string $id): ReplacerInterface
    {
        $parts = explode('|', $id);
        if ($parts[0] === 'related') {
            return new ContentIdReplacer($this->submissionManager);
        }

        throw new EntityNotFoundException("Unable to get replacer for $id");
    }

    public function getListForUi(): array
    {
        return ['Related' =>
            ['related|postbased' => 'Related: Post based content'],
        ];
    }
}
