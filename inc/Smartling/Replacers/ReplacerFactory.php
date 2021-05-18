<?php

namespace Smartling\Replacers;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Submissions\SubmissionManager;

class ReplacerFactory
{
    private LocalizationPluginProxyInterface $localizationProxy;
    private SubmissionManager $submissionManager;

    public function __construct(LocalizationPluginProxyInterface $localizationProxy, SubmissionManager $submissionManager)
    {
        $this->localizationProxy = $localizationProxy;
        $this->submissionManager = $submissionManager;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function getReplacer(string $id): ReplacerInterface
    {
        $parts = explode('|', $id);
        if ($parts[0] === 'related') {
            return new ContentIdReplacer($this->localizationProxy, $this->submissionManager, $parts[1]);
        }

        throw new EntityNotFoundException("Unable to get replacer for $id");
    }

    public function getListForUi(): array
    {
        $result = [];
        $postTypes = get_post_types();

        $result['Related'] = array_combine(
            array_map(static function ($item) {return "related|$item";}, $postTypes),
            array_map(static function ($item) {return "Related: $item";}, $postTypes),
        );

        return $result;
    }
}
