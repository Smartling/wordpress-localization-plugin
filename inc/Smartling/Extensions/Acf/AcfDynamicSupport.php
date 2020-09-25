<?php

namespace Smartling\Extensions\Acf;

use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Extensions\AcfOptionPages\ContentTypeAcfOption;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Settings\ConfigurationProfileEntity;

/**
 * Class AcfAutoSetup
 * @package Smartling\ACF
 */
class AcfDynamicSupport
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public static $acfReverseDefinitionAction = [];

    /**
     * @var EntityHelper
     */
    private $entityHelper;

    private $definitions = [];

    private $rules = [
        'skip'      => [],
        'copy'      => [],
        'localize'  => [],
        'translate' => [],
    ];

    /**
     * @return array
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * @return string[]
     */
    public function getCopyRules()
    {
        return $this->rules['copy'];
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return EntityHelper
     */
    public function getEntityHelper()
    {
        return $this->entityHelper;
    }

    /**
     * @param EntityHelper $entityHelper
     */
    public function setEntityHelper($entityHelper)
    {
        $this->entityHelper = $entityHelper;
    }

    /**
     * @return SiteHelper
     */
    public function getSiteHelper()
    {
        return $this->getEntityHelper()->getSiteHelper();
    }

    /**
     * @return mixed
     * @throws SmartlingConfigException
     */
    private function getAcf()
    {
        global $acf;

        if (!isset($acf)) {
            throw new SmartlingConfigException('ACF plugin is not installed or activated.');
        } else {
            return $acf;
        }
    }

    /**
     * AcfDynamicSupport constructor.
     *
     * @param EntityHelper $entityHelper
     */
    public function __construct(EntityHelper $entityHelper)
    {
        $this->logger = MonologWrapper::getLogger(get_class($this));
        $this->setEntityHelper($entityHelper);
    }

    /**
     * @return array
     */
    private function getBlogs()
    {
        return $this->getSiteHelper()->listBlogs();
    }

    /**
     * @return ConfigurationProfileEntity[]
     */
    private function getActiveProfiles()
    {
        return $this->getEntityHelper()->getSettingsManager()->getActiveProfiles();
    }

    /**
     * @return array
     */
    private function getBlogListForSearch()
    {
        $blogs    = $this->getBlogs();
        $profiles = $this->getActiveProfiles();

        $blogsToSearch = [];

        foreach ($profiles as $profile) {
            /**
             * @var ConfigurationProfileEntity $profile
             */
            if (
                ($profile instanceof ConfigurationProfileEntity)

                && in_array($profile->getOriginalBlogId()->getBlogId(), $blogs, true)
            ) {
                $blogsToSearch[] = $profile->getOriginalBlogId()->getBlogId();
            }
        }

        return $blogsToSearch;
    }

    private function getDatabaseDefinitions()
    {
        $defs = [];
        $this->getLogger()->debug('Looking for ACF definitions in the database');
        $blogsToSearch = $this->getBlogListForSearch();
        foreach ($blogsToSearch as $blog) {
            $this->getLogger()->debug(vsprintf('Collecting ACF definitions for blog = \'%s\'...', [$blog]));
            try {
                $this->getLogger()->debug(vsprintf('Looking for profiles for blog %s', [$blog]));
                $applicableProfiles = $this->getEntityHelper()->getSettingsManager()->findEntityByMainLocale($blog);
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
                            if (0 < count($fields) && false !== $fields) {
                                foreach ($fields as $fieldKey => $field) {
                                    $defs[$fieldKey] = [
                                        'global_type' => 'field',
                                        'type'        => $field['type'],
                                        'name'        => $field['name'],
                                        'parent'      => $field['parent'],
                                    ];

                                    if ('clone' === $field['type']) {
                                        $defs[$fieldKey]['clone'] = $field['clone'];
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

    protected function getGroups($blogId)
    {
        $dbGroups   = [];
        $needChange = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;
        try {
            if ($needChange) {
                $this->getSiteHelper()->switchBlogId($blogId);
            }
            $dbGroups = $this->rawReadGroups();
        } catch (\Exception $e) {
            $this->getLogger()->warning(
                vsprintf('Error occurred while reading ACF data from blog %s. Message: %s', [$blogId, $e->getMessage()])
            );
        } finally {
            if ($needChange) {
                $this->getSiteHelper()->restoreBlogId();
            }
        }

        return $dbGroups;
    }

    /**
     * Reads the list of groups from database
     * @return array
     */
    private function rawReadGroups()
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

    private function rawReadFields($parentId, $parentKey)
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

    protected function getFieldsByGroup($blogId, $group)
    {
        $dbFields   = [];
        $needChange = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;
        try {
            if ($needChange) {
                $this->getSiteHelper()->switchBlogId($blogId);
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
                $this->getSiteHelper()->restoreBlogId();
            }
        }

        return $dbFields;
    }

    protected function extractGroupsDefinitions(array $groups)
    {
        $defs = [];
        foreach ($groups as $group) {
            $defs[$group['key']] = [
                'global_type' => 'group',
                'active'      => $group['active'],
            ];
        }


        return $defs;
    }

    protected function extractFieldDefinitions(array $fields)
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
     * @return array
     */
    private function getLocalDefinitionsOld()
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
                /**
                 * @var \acf_local $local
                 */
                $local = $acf['local'];

                $defs = array_merge($defs, $this->extractGroupsDefinitions($local->groups));
                $defs = array_merge($defs, $this->extractFieldDefinitions($local->fields));

            }
        }

        return $defs;
    }

    protected function validateAcfStores()
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
     * @return array
     */
    private function getLocalDefinitionsNew()
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
     * @return array
     */
    private function getLocalDefinitions()
    {
        $defs = $this->getLocalDefinitionsOld();

        if (empty($defs)) {
            $defs = $this->getLocalDefinitionsNew();
        }

        return $defs;
    }

    /**
     * @param array $localDefinitions
     * @param array $dbDefinitions
     *
     * @return bool
     */
    private function verifyDefinitions(array $localDefinitions, array $dbDefinitions)
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

    private function tryRegisterACFOptions()
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

    private function tryRegisterACF()
    {
        $this->getLogger()->debug('Checking if ACF presents...');
        if (true === $this->checkAcfTypes()) {
            $this->getLogger()->debug('ACF detected.');
            $localDefinitions = $this->getLocalDefinitions();

            if (1 === (int)GlobalSettingsManager::getDisableAcfDbLookup()) {
                $definitions = $localDefinitions;
                $url         = admin_url('edit.php?post_type=acf-field-group&page=acf-tools');
                $msg         = [
                    'Automatic ACF support is disabled. Please ensure that you use relevant exported ACF configuration.',
                    vsprintf('To export your ACF configuration click <strong><a href="%s">here</a></strong>', [$url]),
                ];
                DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
                $this->getLogger()->notice('Automatic ACF support is disabled.');
            } else {
                $dbDefinitions = $this->getDatabaseDefinitions();

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
                $definitions = array_merge($localDefinitions, $dbDefinitions);
            }

            $this->definitions = $definitions;
            $this->buildRules();
            $this->prepareFilters();
        } else {
            $this->getLogger()->debug('ACF not detected.');
        }
    }

    public function run()
    {
        $this->tryRegisterACFOptions();
        $this->tryRegisterACF();
    }

    private function prepareFilters()
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

    private function getFieldTypeByKey($key)
    {
        $def = &$this->definitions;

        return array_key_exists($key, $def) && array_key_exists('type', $def[$key]) ? $def[$key]['type'] : false;
    }

    private function getReferencedTypeByKey($key)
    {
        $type = $this->getFieldTypeByKey($key);

        $value = 'none';

        switch ($type) {
            case 'image':
            case 'file':
            case 'gallery':
                $value = 'media';
                break;
            case 'post_object':
            case 'page_link':
            case 'relationship':
                $value = 'post';
                break;
            case 'taxonomy':
                $value = 'taxonomy';
                break;
            default:
        }

        return $value;
    }

    /**
     * @param array $data
     * @return array
     */
    public function removePreTranslationFields(array $data)
    {
        if (!array_key_exists('meta', $data)) {
            return $data;
        }
        if (count($this->rules['copy']) === 0) {
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

    private function buildRules()
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

    /**
     * @return array
     */
    private function getPostTypes()
    {
        return array_keys(get_post_types());
    }

    /**
     * @return bool
     */
    private function checkAcfTypes()
    {
        $postTypes = $this->getPostTypes();

        return in_array('acf-field', $postTypes, true) && in_array('acf-field-group', $postTypes, true);
    }

    /**
     * Checks if acf_option_page exists
     * @return bool
     */
    private function checkOptionPages()
    {
        return in_array('acf_option_page', $this->getPostTypes(), true);
    }
}
