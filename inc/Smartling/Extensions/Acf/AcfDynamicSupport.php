<?php

namespace Smartling\Extensions\Acf;

use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Extensions\AcfOptionPages\ContentTypeAcfOption;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\ConfigurationProfileEntity;
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

    private array $filterConfigurations = [];

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

    /**
     * @throws SmartlingDirectRunRuntimeException
     */
    private function getBlogs(): array
    {
        return $this->siteHelper->listBlogs();
    }

    /**
     * @throws SmartlingDirectRunRuntimeException
     */
    private function getBlogListForSearch(): array
    {
        $blogs    = $this->getBlogs();
        $profiles = $this->settingsManager->getActiveProfiles();

        $blogsToSearch = [];

        foreach ($profiles as $profile) {
            if (
                ($profile instanceof ConfigurationProfileEntity)
                && in_array($profile->getSourceLocale()->getBlogId(), $blogs, true)
            ) {
                $blogsToSearch[] = $profile->getSourceLocale()->getBlogId();
            }
        }

        return $blogsToSearch;
    }

    /**
     * @throws SmartlingDirectRunRuntimeException
     */
    private function loadDefinitions(): array
    {
        $defs = [];
        $this->getLogger()->debug('Looking for ACF definitions via ACF API');
        foreach ($this->getBlogListForSearch() as $blog) {
            try {
                if (0 === count($this->settingsManager->findEntityByMainLocale($blog))) {
                    $this->getLogger()->debug("No suitable profile found for blog $blog");
                    continue;
                }
                $defs = array_merge(
                    $defs,
                    $this->siteHelper->withBlog($blog, fn(): array => $this->collectAcfDefinitions()),
                );
            } catch (\Exception $e) {
                $this->getLogger()->warning("ACF Filter generation failed: {$e->getMessage()}");
            }
        }

        return $defs;
    }

    public function getFilterConfiguration(string $key): ?array
    {
        return $this->filterConfigurations[$key] ?? null;
    }

    private function collectAcfDefinitions(): array
    {
        $defs = [];
        foreach (acf_get_field_groups() as $group) {
            if (!is_array($group) || !isset($group['key'])) {
                continue;
            }
            $defs[$group['key']] = [
                'global_type' => 'group',
                'active' => $group['active'] ?? 1,
            ];
            $groupId = (int)($group['ID'] ?? 0);
            if ($groupId <= 0) {
                // Local-only field group (no DB post) — children live only in the local store,
                // which we intentionally don't read from. Skip child enumeration for this group.
                continue;
            }
            foreach (acf_get_fields($group) as $field) {
                $this->addAcfFieldToDefs($field, $defs);
            }
        }

        return $defs;
    }

    private const MAX_ACF_FIELD_DEPTH = 16;

    protected function addAcfFieldToDefs(array $field, array &$defs, int $depth = 0): void
    {
        if (!isset($field['key'], $field['type'])) {
            return;
        }
        if ($depth > self::MAX_ACF_FIELD_DEPTH) {
            $this->getLogger()->error(sprintf(
                'ACF field tree exceeded depth limit %d at field key "%s"; aborting recursion.',
                self::MAX_ACF_FIELD_DEPTH,
                $field['key'],
            ));
            return;
        }
        $defs[$field['key']] = ['global_type' => 'field', 'type' => $field['type']];
        if ('clone' === $field['type']) {
            if (array_key_exists('clone', $field)) {
                $defs[$field['key']]['clone'] = $field['clone'];
            } else {
                $this->getLogger()->debug('ACF field fieldType="clone" has no target. ' . json_encode($field));
            }
        }
        if (!in_array($field['type'], ['repeater', 'group', 'flexible_content'], true)) {
            return;
        }
        if (isset($field['ID']) && (int)$field['ID'] > 0) {
            foreach (acf_get_fields($field) as $child) {
                $this->addAcfFieldToDefs($child, $defs, $depth + 1);
            }
        }
        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            foreach ($field['sub_fields'] as $child) {
                $this->addAcfFieldToDefs($child, $defs, $depth + 1);
            }
        }
        if (isset($field['layouts']) && is_array($field['layouts'])) {
            foreach ($field['layouts'] as $layout) {
                if (is_array($layout) && isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                    foreach ($layout['sub_fields'] as $child) {
                        $this->addAcfFieldToDefs($child, $defs, $depth + 1);
                    }
                }
            }
        }
    }

    public function getReferencedType(string $type): string
    {
        return match ($type) {
            'image', 'image_aspect_ratio_crop', 'file', 'gallery' => self::REFERENCED_TYPE_MEDIA,
            'post_object', 'page_link', 'relationship' => self::REFERENCED_TYPE_POST,
            'taxonomy' => self::REFERENCED_TYPE_TAXONOMY,
            default => self::REFERENCED_TYPE_NONE,
        };
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
            try {
                $this->definitions = $this->loadDefinitions();
            } catch (SmartlingDirectRunRuntimeException $e) {
                $this->definitions = [];
                DiagnosticsHelper::addDiagnosticsMessage(
                    'Failed to get ACF definitions. ' .
                    'Please ensure that WordPress network is set up properly.<br>' .
                    "Exception message: {$e->getMessage()}"
                );
            }
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

    public function runIfRequired(): void
    {
        if ($this->definitions === null) {
            $this->run();
        }
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

        $this->filterConfigurations = $rules;
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

    public function getReferencedTypeByKey(string $key): string
    {
        if ($this->definitions === null) {
            $this->run();
        }
        return $this->getReferencedType($this->definitions[$this->getRuleId($key)]['type'] ?? '');
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
