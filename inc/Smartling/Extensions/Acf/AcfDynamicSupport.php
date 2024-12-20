<?php

namespace Smartling\Extensions\Acf;

use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Extensions\AcfOptionPages\ContentTypeAcfOption;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;

class AcfDynamicSupport
{
    use LoggerSafeTrait;

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

    /**
     * @throws SmartlingConfigException
     */
    private function getAcf(): mixed
    {
        global $acf;

        if (!isset($acf)) {
            throw new SmartlingConfigException('ACF plugin is not installed or activated.');
        }

        return $acf;
    }

    public function __construct(
        private ArrayHelper $arrayHelper,
        private SettingsManager $settingsManager,
        private SiteHelper $siteHelper,
        private WordpressFunctionProxyHelper $wpProxy,
    )
    {}

    public function addCopyRules(array $rules) {
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
                && in_array($profile->getOriginalBlogId()->getBlogId(), $blogs, true)
            ) {
                $blogsToSearch[] = $profile->getOriginalBlogId()->getBlogId();
            }
        }

        return $blogsToSearch;
    }

    /**
     * @throws SmartlingDirectRunRuntimeException
     */
    private function getDatabaseDefinitions(): array
    {
        $defs = [];
        $this->getLogger()->debug('Looking for ACF definitions in the database');
        $blogsToSearch = $this->getBlogListForSearch();
        foreach ($blogsToSearch as $blog) {
            $this->getLogger()->debug(vsprintf('Collecting ACF definitions for blog = \'%s\'...', [$blog]));
            try {
                $this->getLogger()->debug(vsprintf('Looking for profiles for blog %s', [$blog]));
                $applicableProfiles = $this->settingsManager->findEntityByMainLocale($blog);
                if (0 === count($applicableProfiles)) {
                    $this->getLogger()->debug(vsprintf('No suitable profile found for this blog %s', [$blog]));
                } else {
                    $groups = $this->getGroups($blog);
                    if (0 < count($groups)) {
                        foreach ($groups as $groupKey => $group) {
                            $defs[$groupKey] = [
                                'global_type' => 'group',
                                'active'      => 1,
                            ];
                            $fields          = $this->getFieldsByGroup($blog, [$groupKey => $group]);
                            if (0 < count($fields)) {
                                foreach ($fields as $fieldKey => $field) {
                                    $defs[$fieldKey] = [
                                        'global_type' => 'field',
                                        'type'        => $field['type'],
                                        'name'        => $field['name'],
                                        'parent'      => $field['parent'],
                                    ];

                                    if ('clone' === $field['type']) {
                                        if (array_key_exists('clone', $field)) {
                                            $defs[$fieldKey]['clone'] = $field['clone'];
                                        } else {
                                            $this->getLogger()->debug('ACF field fieldType="clone" has no target. ' . json_encode($field));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->getLogger()->warning(vsprintf('ACF Filter generation failed: %s', [$e->getMessage()]));
            }
        }

        return $defs;
    }

    protected function getGroups($blogId): array
    {
        $dbGroups   = [];
        $needChange = $this->siteHelper->getCurrentBlogId() !== $blogId;
        try {
            if ($needChange) {
                $this->siteHelper->switchBlogId($blogId);
            }
            $dbGroups = $this->rawReadGroups();
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                vsprintf('Error occurred while reading ACF data from blog %s. Message: %s', [$blogId, $e->getMessage()])
            );
        } finally {
            if ($needChange) {
                $this->siteHelper->restoreBlogId();
            }
        }

        return $dbGroups;
    }

    /**
     * Reads the list of groups from database
     */
    private function rawReadGroups(): array
    {
        $posts = (new \WP_Query(
            [
                'post_type'        => 'acf-field-group',
                'suppress_filters' => true,
                'posts_per_page'   => -1,
                'post_status'      => 'publish',
            ]
        ))->get_posts();

        $groups = [];
        foreach ($posts as $post) {
            $groups[$post->post_name] = [
                'title'   => $post->post_title,
                'post_id' => $post->ID,
            ];
        }

        return $groups;
    }

    private function rawReadFields($parentId, $parentKey): array
    {
        $posts = (new \WP_Query(
            [
                'post_type'        => 'acf-field',
                'suppress_filters' => true,
                'posts_per_page'   => -1,
                'post_status'      => 'publish',
                'post_parent'      => $parentId,
            ]
        ))->get_posts();

        $fields = [];
        foreach ($posts as $post) {
            $configuration            = unserialize($post->post_content);
            $fields[$post->post_name] = [
                'parent' => $parentKey,
                'name'   => $post->post_excerpt,
                'type'   => $configuration['type'],
            ];
            $subFields                = $this->rawReadFields($post->ID, $post->post_name);
            if (0 < count($subFields)) {
                $fields = array_merge($fields, $subFields);
            }
        }

        return $fields;
    }

    protected function getFieldsByGroup($blogId, $group): array
    {
        $dbFields   = [];
        $needChange = $this->siteHelper->getCurrentBlogId() !== $blogId;
        try {
            if ($needChange) {
                $this->siteHelper->switchBlogId($blogId);
            }
            $keys   = array_keys($group);
            $key    = reset($keys);
            $_group = reset($group);
            $id     = $_group['post_id'];

            $dbFields = $this->rawReadFields($id, $key);

        } catch (\Exception $e) {
            $this->getLogger()->warning(
                vsprintf('Error occurred while reading ACF data from blog %s. Message: %s', [$blogId, $e->getMessage()])
            );
        } finally {
            if ($needChange) {
                $this->siteHelper->restoreBlogId();
            }
        }

        return $dbFields;
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

    /**
     * Get local definitions for ACF Pro ver < 5.7.12
     */
    private function getLocalDefinitionsOld(): array
    {
        $acf  = null;
        $defs = [];
        try {
            $acf = (array)$this->getAcf();
        } catch (SmartlingConfigException $e) {
            $this->getLogger()->warning($e->getMessage());
            $this->getLogger()->warning('Unable to load old type local ACF definitions.');

            return $defs;
        }

        if (array_key_exists('local', $acf)) {
            if ($acf['local'] instanceof \acf_local) {
                $local = $acf['local'];

                $defs = array_merge($defs, $this->extractGroupsDefinitions($local->groups));
                $defs = array_merge($defs, $this->extractFieldDefinitions($local->fields));

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
    private function getLocalDefinitionsNew(): array
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

    /**
     * Reads local (PHP and JSON) ACF Definitions
     */
    private function getLocalDefinitions(): array
    {
        $defs = $this->getLocalDefinitionsOld();

        if (empty($defs)) {
            $defs = $this->getLocalDefinitionsNew();
        }

        return $defs;
    }

    private function verifyDefinitions(array $localDefinitions, array $dbDefinitions): bool
    {
        foreach ($dbDefinitions as $key => $definition) {
            if (!array_key_exists($key, $localDefinitions)) {
                return false;
            }

            if ($definition['global_type'] === 'field') {
                $local = $localDefinitions[$key];
                if ($local['type'] !== $definition['type'] || $local['name'] !== $definition['name'] ||
                    $local['parent'] !== $definition['parent']
                ) {
                    // ACF Option Pages has internal issue in definition, so skip it:
                    if ('group_572b269b668a4' !== $local['parent']) {
                        return false;
                    }
                }
            }
        }

        return true;
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

    private function tryRegisterACF(): void
    {
        $this->getLogger()->debug('Checking if ACF presents...');
        if (true === $this->checkAcfTypes()) {
            $this->getLogger()->debug('ACF detected.');
            $localDefinitions = $this->getLocalDefinitions();

            try {
                $dbDefinitions = $this->getDatabaseDefinitions();
            } catch (SmartlingDirectRunRuntimeException $e) {
                $dbDefinitions = [];
                DiagnosticsHelper::addDiagnosticsMessage(
                    'Failed to get ACF definitions from database.' .
                    'Please ensure that WordPress network is set up properly.<br>' .
                    "Exception message: {$e->getMessage()}"
                );
            }

            if (false === $this->verifyDefinitions($localDefinitions, $dbDefinitions)) {
                $url = admin_url('edit.php?post_type=acf-field-group&page=acf-tools');
                $msg = [
                    'ACF Configuration has been changed.',
                    'Please update groups and fields definitions for all sites (As PHP generated code).',
                    vsprintf('Use <strong><a href="%s">this</a></strong> page to generate export code and add it to your theme or extra plugin.',
                        [$url]),
                ];
                DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
            }

            $this->definitions = array_merge($localDefinitions, $dbDefinitions);
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
            $matches = [];
            preg_match_all(AcfTypeDetector::ACF_FIELD_GROUP_REGEX, $attributes[$key], $matches);
            $ruleId = array_pop($matches[0]) ?? $attributes[$key];
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
        $type = $this->definitions[$key]['type'] ?? '';

        switch ($type) {
            case 'image':
            case 'image_aspect_ratio_crop':
            case 'file':
            case 'gallery':
                return self::REFERENCED_TYPE_MEDIA;
            case 'post_object':
            case 'page_link':
            case 'relationship':
                return self::REFERENCED_TYPE_POST;
            case 'taxonomy':
                return self::REFERENCED_TYPE_TAXONOMY;
        }

        return self::REFERENCED_TYPE_NONE;
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
            if (strpos($key, '_') === 0 && in_array($value, $this->rules['copy'], true)) {
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

    private function checkAcfTypes(): bool
    {
        $postTypes = $this->getPostTypes();

        return in_array('acf-field', $postTypes, true) && in_array('acf-field-group', $postTypes, true);
    }

    /**
     * Checks if acf_option_page exists
     */
    private function checkOptionPages(): bool
    {
        return in_array('acf_option_page', $this->getPostTypes(), true);
    }
}
