<?php

namespace Smartling\Replacers;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Submissions\SubmissionManager;

class ReplacerFactory
{
    public const RELATED_POSTBASED = 'related|postbased';
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
            [self::RELATED_POSTBASED => 'Related: Post based content'],
        ];
    }
}
