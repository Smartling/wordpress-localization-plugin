<?php

namespace Smartling\ContentTypes;

use Smartling\Extensions\Pluggable;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentYoast extends ExternalContentAbstract implements ContentTypeModifyingInterface {
    use LoggerSafeTrait;

    public const handledFields = [
        '_yoast_wpseo_focuskeywords',
        '_yoast_wpseo_keywordsynonyms',
    ];

    public function __construct(
        private ContentTypeHelper $contentTypeHelper,
        private FieldsFilterHelper $fieldsFilterHelper,
        PluginHelper $pluginHelper,
        SubmissionManager $submissionManager,
        WordpressFunctionProxyHelper $wpProxy,
    ) {
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        $result = [];
        foreach (self::handledFields as $field) {
            $meta = $this->wpProxy->getPostMeta($submission->getSourceId(), $field, true);
            if (is_string($meta)) {
                try {
                    $decoded = json_decode($meta, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $result[$field] = $decoded;
                    }
                } catch (\JsonException) {
                    // Not json, skip processing
                }
            }
        }

        return $this->fieldsFilterHelper->flattenArray($result);
    }

    public function getMaxVersion(): string
    {
        return '24';
    }

    public function getMinVersion(): string
    {
        return '15';
    }

    public function getPluginId(): string
    {
        return 'yoast';
    }

    public function getPluginPaths(): array
    {
        return [
            'wordpress-seo/wp-seo.php',
            'wordpress-seo-premium/wp-seo-premium.php',
        ];
    }

    public function getSupportLevel(string $contentType, ?int $contentId = null): string
    {
        return $this->contentTypeHelper->isPost($contentType)
            ? parent::getSupportLevel($contentType, $contentId)
            : Pluggable::NOT_SUPPORTED;
    }

    public function removeUntranslatableFieldsForUpload(array $source, SubmissionEntity $submission): array
    {
        foreach (self::handledFields as $field) {
            $meta = $this->wpProxy->getPostMeta($submission->getSourceId(), $field, true);
            if (is_string($meta)) {
                try {
                    if (is_array(json_decode($meta, true, 512, JSON_THROW_ON_ERROR))) {
                        unset($source['meta'][$field]);
                    }
                } catch (\JsonException) {
                    // Not json, skip processing
                }
            }
        }

        return $source;
    }

    public function setContentFields(array $original, array $translation, SubmissionEntity $submission): ?array
    {
        if (!array_key_exists('meta', $original)
            || !array_key_exists($this->getPluginId(), $original)
            || !is_array($translation[$this->getPluginId()] ?? '')
            || count($translation[$this->getPluginId()]) === 0) {
            return null;
        }
        foreach ($translation[$this->getPluginId()] as $key => $value) {
            if (array_key_exists($key, $original['meta'])) {
                $translation['meta'][$key] = json_encode(
                    array_replace_recursive(
                        json_decode($original['meta'][$key], true, 512, JSON_THROW_ON_ERROR),
                        $value,
                    ),
                    JSON_THROW_ON_ERROR
                );
            }
        }

        unset($translation[$this->getPluginId()]);

        return $translation;
    }
}
