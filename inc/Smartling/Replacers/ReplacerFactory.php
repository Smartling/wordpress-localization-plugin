<?php

namespace Smartling\Replacers;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Submissions\SubmissionManager;

class ReplacerFactory
{
    public const string REPLACER_COPY = 'copy';
    private const string REPLACER_EXCLUDE = 'exclude';
    public const string REPLACER_RELATED = 'related';
    private const string REPLACER_WP_CORE_IMAGE_INNER_HTML = 'coreImage';

    /**
     * @var ReplacerInterface[] $replacers
     */
    private array $replacers;

    public function __construct(SubmissionManager $submissionManager)
    {
        $this->replacers = [
            self::REPLACER_COPY => new CopyReplacer(),
            self::REPLACER_EXCLUDE => new ExcludeReplacer(),
            self::REPLACER_RELATED => new ContentIdReplacer($submissionManager),
            self::REPLACER_WP_CORE_IMAGE_INNER_HTML => new ImageInnerHtmlReplacer($submissionManager),
        ];
    }

    /**
     * @throws EntityNotFoundException
     */
    public function getReplacer(string $id): ReplacerInterface
    {
        $backCompatibleId = explode('|', $id)[0];
        if (array_key_exists($backCompatibleId, $this->replacers)) {
            return $this->replacers[$backCompatibleId];
        }

        throw new EntityNotFoundException("Unable to get replacer for $id");
    }

    public function getListForUi(): array
    {
        $result = [];
        foreach ($this->replacers as $id => $replacer) {
            $result[$id] = $replacer->getLabel();
        }

        return $result;
    }
}
