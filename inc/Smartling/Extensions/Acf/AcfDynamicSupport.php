<?php

namespace Smartling\Extensions\Acf;

use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Extensions\AcfOptionPages\ContentTypeAcfOption;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class AcfDynamicSupport
{
    use LoggerSafeTrait;

    public const POST_TYPE_FIELD = 'acf-field';
    public const POST_TYPE_GROUP = 'acf-field-group';
    public const REFERENCED_TYPE_NONE = 'none';
    public const REFERENCED_TYPE_MEDIA = 'media';
    public const REFERENCED_TYPE_POST = 'post';
    public const REFERENCED_TYPE_TAXONOMY = 'taxonomy';

    public static array $acfReverseDefinitionAction = [];

    private ?array $definitions = null;

    private array $rules = [
        'skip'      => [],
        'copy'      => [],
        'localize'  => [],
        'translate' => [],
    ];

    public function getDefinitions(): array
    {
        return $this->definitions ?? [];
    }

    public function __construct(
        private ArrayHelper $arrayHelper,
        private SettingsManager $settingsManager,
        private SiteHelper $siteHelper,
        private SubmissionManager $submissionManager,
        private WordpressFunctionProxyHelper $wpProxy,
    )
    {}

    public function addCopyRules(array $rules): void
    {
        $this->rules['copy'] = $this->arrayHelper->add($this->rules['copy'], $rules);
    }

    protected function extractGroupsDefinitions(array $groups): array
    {
        $defs = [];
        foreach ($groups as $group) {
            $defs[$group['key']] = [
                'global_type' => 'group',
            ];
            if (array_key_exists('active', $group)) {
                $defs[$group['key']]['active'] = $group['active'];
            }
        }

        return $defs;
    }

    protected function extractFieldDefinitions(array $fields): array
    {
        $defs = [];

        foreach ($fields as $field) {
            $defs[$field['key']] = [
                'global_type' => 'field',
                'type'        => $field['type'],
                'name'        => $field['name'],
                'parent'      => $field['parent'],
            ];

            if ('clone' === $field['type']) {
                $defs[$field['key']]['clone'] = $field['clone'];
            }
        }

        return $defs;
    }

    protected function validateAcfStores(): bool
    {
        global $acf_stores;

        return is_array($acf_stores)
            && array_key_exists('local-groups', $acf_stores)
            && ($acf_stores['local-groups'] instanceof \ACF_Data)
            && array_key_exists('local-fields', $acf_stores)
            && ($acf_stores['local-fields'] instanceof \ACF_Data);
    }

    /**
     * Get local definitions for ACF Pro ver 5.7.12+
     */
    private function getLocalDefinitions(): array
    {
        $defs = [];

        if ($this->validateAcfStores()) {
            global $acf_stores;

            $defs = array_merge($defs, $this->extractGroupsDefinitions($acf_stores['local-groups']->get_data()));
            $defs = array_merge($defs, $this->extractFieldDefinitions($acf_stores['local-fields']->get_data()));

        } else {
            $this->getLogger()->warning('Unable to load new type local ACF definitions.');
        }

        return $defs;
    }

    private function tryRegisterACFOptions(): void
    {
        $this->getLogger()->debug('Checking if ACF Option Pages presents...');

        if (true === $this->checkOptionPages()) {
            $this->getLogger()->debug('ACF Option Pages detected.');
            ContentTypeAcfOption::register(Bootstrap::getContainer());
            add_filter(
                ExportedAPI::FILTER_SMARTLING_REGISTER_FIELD_FILTER,
                function (array $definitions) {
                    return array_merge($definitions, [['pattern' => 'menu_slug$', 'action' => 'copy']]);
                }
            );
        } else {
            $this->getLogger()->debug('ACF Option Pages not detected.');
        }
    }

    public function syncAcfData(SubmissionEntity $submission): void
    {
        if ($submission->isLocked()) {
            $this->logger->debug("Submission submissionId={$submission->getId()} is locked, skipping ACF sync");

            return;
        }

        $context = [
            'submissionId' => $submission->getId(),
            'contentType' => $submission->getContentType(),
            'sourceId' => $submission->getSourceId(),
        ];
        try {
            foreach ($context as $key => $value) {
                LogContextMixinHelper::addToContext($key, $value);
            }
            if (!in_array($submission->getContentType(), $this->getTypes(), true)) {
                $this->getLogger()->error("Trying to sync {$submission->getContentType()}, expected content types: " . implode(', ', $this->getTypes()));

                return;
            }

            $post = $this->wpProxy->get_post($submission->getSourceId(), ARRAY_A);
            if ($post === null) {
                $this->getLogger()->error("Trying to sync {$submission->getContentType()}, source post not found");

                return;
            }

            $array = $this->wpProxy->maybe_unserialize($post['post_content']);
            if (!is_array($array)) {
                $this->getLogger()->error("Trying to sync {$submission->getContentType()}, post content could not be unserialized, content=\"$post->post_content\"");

                return;
            }

            if (array_key_exists('location', $array) && is_array($array['location'])) { // Null coalesce doesn't work with references
                foreach ($array['location'] as &$rules) {
                    foreach ($rules as &$rule) {
                        if ($rule['param'] === 'page') {
                            $targetSubmission = $this->submissionManager->findOne([
                                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
                                SubmissionEntity::FIELD_SOURCE_ID => $rule['value'],
                                SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
                                SubmissionEntity::FIELD_CONTENT_TYPE => $this->wpProxy->get_post_types(),
                            ]);
                            if ($targetSubmission === null) {
                                $this->getLogger()->debug("Skip change location page {$rule['operator']} {$rule['value']}: no target submission exists");
                                continue;
                            }
                            $rule['value'] = (string)$targetSubmission->getTargetId();
                        }
                    }
                    unset($rule);
                }
                unset($rules);
            }

            $this->siteHelper->withBlog($submission->getTargetBlogId(), function () use ($array, $submission): void {
                $result = $this->wpProxy->wp_update_post([
                    'ID' => $submission->getTargetId(),
                    'post_content' => serialize($array),
                ], true);
                if ($result instanceof \WP_Error) {
                    $this->getLogger()->error("Sync of ACF field group failed to update post: " . implode(', ', $result->get_error_messages()));
                }
            });
        } finally {
            foreach (array_keys($context) as $key) {
                LogContextMixinHelper::removeFromContext($key);
            }
        }
    }

    private function tryRegisterACF(): void
    {
        $this->getLogger()->debug('Checking if ACF presents...');
        if ($this->isAcfActive()) {
            $this->getLogger()->debug('ACF detected.');
            $this->definitions = $this->getLocalDefinitions();
            $this->buildRules();
            $this->prepareFilters();
        } else {
            $this->getLogger()->debug('ACF not detected.');
        }
    }

    public function run(): void
    {
        $this->tryRegisterACFOptions();
        $this->tryRegisterACF();
    }

    private function prepareFilters(): void
    {
        $rules = [];

        if (0 < count($this->rules['copy'])) {
            foreach ($this->rules['copy'] as $key) {
                $rules[$key] = [
                    'action' => 'copy',
                ];
            }
        }

        if (0 < count($this->rules['skip'])) {
            foreach ($this->rules['skip'] as $key) {
                $rules[$key] = [
                    'action' => 'skip',
                ];
            }
        }

        if (0 < count($this->rules['localize'])) {
            foreach ($this->rules['localize'] as $key) {
                $rules[$key] = [
                    'action'        => 'localize',
                    'value'         => 'reference',
                    'serialization' => 'none',
                    'type'          => $this->getReferencedTypeByKey($key),
                ];
            }
        }

        static::$acfReverseDefinitionAction = $rules;
    }

    public function getReplacerIdForField(array $attributes, string $key): ?string
    {
        if ($this->definitions === null) {
            $this->run();
        }
        $parts = array_reverse(explode(FieldsFilterHelper::ARRAY_DIVIDER, $key));
        if (is_numeric($parts[0]) && count($parts) > 1) {
            array_shift($parts);
        }
        $parts[0] = "_$parts[0]";
        $key = implode(FieldsFilterHelper::ARRAY_DIVIDER, array_reverse($parts));
        if (array_key_exists($key, $attributes)) {
            $ruleId = $this->getRuleId($attributes[$key]);
            if (in_array($ruleId, $this->rules['localize'], true)) {
                return ReplacerFactory::REPLACER_RELATED;
            }
            if (in_array($ruleId, $this->rules['copy'], true)) {
                return ReplacerFactory::REPLACER_COPY;
            }
        }

        return null;
    }

    public function getReferencedTypeByKey($key): string
    {
        if ($this->definitions === null) {
            $this->run();
        }
        $type = $this->definitions[$this->getRuleId($key)]['type'] ?? '';

        return match ($type) {
            'image', 'image_aspect_ratio_crop', 'file', 'gallery' => self::REFERENCED_TYPE_MEDIA,
            'post_object', 'page_link', 'relationship' => self::REFERENCED_TYPE_POST,
            'taxonomy' => self::REFERENCED_TYPE_TAXONOMY,
            default => self::REFERENCED_TYPE_NONE,
        };
    }

    public function removePreTranslationFields(array $data): array
    {
        if (!array_key_exists('meta', $data)) {
            return $data;
        }
        if ($this->definitions === null) {
            $this->run();
        }

        foreach ($data['meta'] as $key => $value) {
            if (str_starts_with($key, '_') && in_array($value, $this->rules['copy'], true)) {
                $realKey = substr($key, 1);
                unset($data['meta'][$realKey], $data['meta'][$key]);
                $this->getLogger()->debug("Unset meta field $realKey");
            }
        }

        return $data;
    }

    private function buildRules(): void
    {
        foreach ($this->definitions as $id => $definition) {
            if ('group' === $definition['global_type']) {
                continue;
            }
            switch ($definition['type']) {
                case 'text':
                case 'textarea':
                case 'wysiwyg':
                    $this->rules['translate'][] = $id;
                    break;
                case 'number':
                case 'email':
                case 'url':
                case 'password':
                case 'oembed':
                case 'select':
                case 'checkbox':
                case 'radio':
                case 'choice':
                case 'true_false':
                case 'date_picker':
                case 'date_time_picker':
                case 'time_picker':
                case 'color_picker':
                case 'google_map':
                case 'flexible_content':
                    $this->rules['copy'][] = $id;
                    break;
                case 'user':
                    $this->rules['skip'][] = $id;
                    break;
                case 'image':
                case 'image_aspect_ratio_crop':
                case 'file':
                case 'post_object':
                case 'page_link':
                case 'relationship':
                case 'gallery':
                case 'taxonomy': // look into taxonomy
                    $this->rules['localize'][] = $id;
                    break;
                case 'repeater':
                case 'message':
                case 'tab':
                case 'clone':
                case 'group':
                case 'link':
                    break;
                default:
                    $this->getLogger()->debug(vsprintf('Got unknown type: %s', [$definition['type']]));
            }
        }
    }

    private function getPostTypes(): array
    {
        return array_keys($this->wpProxy->get_post_types());
    }

    public function isAcfActive(): bool
    {
        $postTypes = $this->getPostTypes();

        return in_array(self::POST_TYPE_FIELD, $postTypes, true)
            && in_array(self::POST_TYPE_GROUP, $postTypes, true);
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return [self::POST_TYPE_GROUP];
    }

    /**
     * Checks if acf_option_page exists
     */
    private function checkOptionPages(): bool
    {
        return in_array('acf_option_page', $this->getPostTypes(), true);
    }

    public function getRuleId(string $key): string
    {
        $matches = [];
        preg_match_all(AcfTypeDetector::ACF_FIELD_GROUP_REGEX, $key, $matches);

        return array_pop($matches[0]) ?? $key;
    }
}
