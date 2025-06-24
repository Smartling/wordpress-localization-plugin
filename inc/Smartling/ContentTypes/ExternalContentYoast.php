<?php

namespace Smartling\ContentTypes;

use Smartling\Extensions\Pluggable;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PlaceholderHelper;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ExternalContentYoast extends ExternalContentAbstract implements ContentTypeModifyingInterface {
    use LoggerSafeTrait;

    public const decodedFields = [
        '_yoast_wpseo_focuskeywords',
        '_yoast_wpseo_keywordsynonyms',
    ];

    public const placeholderFields = [
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_title',
    ];

    public function __construct(
        private ContentTypeHelper $contentTypeHelper,
        private FieldsFilterHelper $fieldsFilterHelper,
        private PlaceholderHelper $placeholderHelper,
        PluginHelper $pluginHelper,
        SubmissionManager $submissionManager,
        WordpressFunctionProxyHelper $wpProxy,
    ) {
        parent::__construct($pluginHelper, $submissionManager, $wpProxy);
    }

    public function getContentFields(SubmissionEntity $submission, bool $raw): array
    {
        $result = [];
        foreach (self::decodedFields as $field) {
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
        foreach (self::placeholderFields as $field) {
            $meta = $this->wpProxy->getPostMeta($submission->getSourceId(), $field, true);
            if (is_string($meta)) {
                $result[$field] = $this->replaceContentPlaceholdersWithSmartlingPlaceholders($meta, "~(%%.+?%%)~", $this->placeholderHelper);
            }
        }

        return $this->fieldsFilterHelper->flattenArray($result);
    }

    public function getMaxVersion(): string
    {
        return '25';
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
        foreach (self::decodedFields as $field) {
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
        foreach (self::placeholderFields as $field) {
            unset($source['meta'][$field]);
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
        foreach (self::decodedFields as $key) {
            if (array_key_exists($key, $original['meta']) && array_key_exists($key, $translation[$this->getPluginId()])) {
                $translation['meta'][$key] = json_encode(
                    array_replace_recursive(
                        json_decode($original['meta'][$key], true, 512, JSON_THROW_ON_ERROR),
                        $translation[$this->getPluginId()][$key],
                    ),
                    JSON_THROW_ON_ERROR
                );
            }
        }
        foreach (self::placeholderFields as $field) {
            if (array_key_exists($field, $original['meta']) && array_key_exists($field, $translation[$this->getPluginId()])) {
                $translation['meta'][$field] = $this->placeholderHelper->removePlaceholders($translation[$this->getPluginId()][$field]);
            }
        }

        unset($translation[$this->getPluginId()]);

        return $translation;
    }
}
