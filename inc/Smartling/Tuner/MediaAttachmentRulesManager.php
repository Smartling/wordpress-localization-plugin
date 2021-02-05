<?php

namespace Smartling\Tuner;

use Smartling\Helpers\GutenbergReplacementRule;

class MediaAttachmentRulesManager extends CustomizationManagerAbstract
{
    const STORAGE_KEY = 'CUSTOM_MEDIA_RULES';
    private $preconfiguredRules;

    public function __construct(array $rules = [])
    {
        parent::__construct(static::STORAGE_KEY);
        $this->preconfiguredRules = $rules;
    }

    /**
     * @return array
     */
    public function getPreconfiguredRules() {
        $result = [];
        foreach ($this->preconfiguredRules as $rule) {
            $rule = explode('/', $rule);
            $result[] = [
                'block' => $rule[0],
                'path' => $rule[1],
            ];
        }

        return $result;
    }

    /**
     * @return GutenbergReplacementRule[]
     */
    public function getGutenbergReplacementRules()
    {
        $result = [];
        $this->loadData();
        foreach (array_merge($this->getPreconfiguredRules(), $this->listItems()) as $rule) {
            $result[] = new GutenbergReplacementRule($rule['block'], $rule['path']);
        }

        return $result;
    }
}
