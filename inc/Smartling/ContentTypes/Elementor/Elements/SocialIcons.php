<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class SocialIcons extends Unknown {
    private string $settingsKey = 'social_icon_list';

    public function getType(): string
    {
        return 'social-icons';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        foreach ($this->settings[$this->settingsKey] ?? [] as $index => $listItem) {
            $key = "$this->settingsKey/$index/social_icon/value/id";
            $id = $this->getIntSettingByKey($key, $this->settings);
            if ($id !== null) {
                $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
            }
        }

        return $return;
    }
}
