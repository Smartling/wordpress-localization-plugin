<?php

namespace Smartling\Extensions\Acf;

use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Extensions\AcfOptionPages\ContentTypeAcfOption;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\MonologWrapper\MonologWrapper;
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

    private $filters = [];

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
     * Metadata filter filter handler
     *
     * @param array $filters
     *
     * @return array
     */
    public function filterSetup(array $filters)
    {
        return array_merge($filters, $this->filters);
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
        $blogs = $this->getBlogs();
        $profiles = $this->getActiveProfiles();

        $blogsToSearch = [];

        foreach ($profiles as $profile) {
            /**
             * @var ConfigurationProfileEntity $profile
             */
            if (
                ($profile instanceof ConfigurationProfileEntity)

                && in_array($profile->getOriginalBlogId()->getBlogId(), $blogs)
            ) {
                $blogsToSearch[] = $profile->getOriginalBlogId()->getBlogId();
            }
        }

        return $blogsToSearch;
    }

    public function collectDefinitions()
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
                            $fields = $this->getFieldsByGroup($blog, [$groupKey => $group]);
                            if (0 < count($fields) && false !== $fields) {
                                foreach ($fields as $fieldKey => $field) {
                                    $defs[$fieldKey] = [
                                        'global_type' => 'field',
                                        'type'        => $field['type'],
                                        'name'        => $field['name'],
                                        'parent'      => $field['parent'],
                                    ];
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
        $dbGroups = [];
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
            $configuration = unserialize($post->post_content);
            $fields[$post->post_name] = [
                'parent' => $parentKey,
                'name'   => $post->post_excerpt,
                'type'   => $configuration['type'],
            ];
            $subFields = $this->rawReadFields($post->ID, $post->post_name);
            if (0 < count($subFields)) {
                $fields = array_merge($fields, $subFields);
            }
        }

        return $fields;
    }

    protected function getFieldsByGroup($blogId, $group)
    {
        $dbFields = [];
        $needChange = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;
        try {
            if ($needChange) {
                $this->getSiteHelper()->switchBlogId($blogId);
            }
            $keys = array_keys($group);
            $key = reset($keys);
            $_group = reset($group);
            $id = $_group['post_id'];

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

    /**
     * Reads local (PHP and JSON) ACF Definitions
     * @return array
     */
    private function getLocalDefinitions()
    {
        $acf = null;
        $defs = [];
        try {
            $acf = (array)$this->getAcf();
        } catch (SmartlingConfigException $e) {
            $this->getLogger()->warning($e->getMessage());
            $this->getLogger()->warning('Unable to load local ACF definitions.');

            return $defs;
        }

        if (array_key_exists('local', $acf)) {
            if ($acf['local'] instanceof \acf_local) {
                /**
                 * @var \acf_local $local
                 */
                $local = $acf['local'];
                $groups = $local->groups;

                if (is_array($groups) && 0 < count($groups)) {
                    foreach ($groups as $group) {
                        $defs[$group['key']] = [
                            'global_type' => 'group',
                            'active'      => $group['active'],
                        ];
                    }
                }

                $fields = $local->fields;

                if (is_array($fields) && 0 < count($fields)) {
                    foreach ($fields as $field) {
                        $defs[$field['key']] = [
                            'global_type' => 'field',
                            'type'        => $field['type'],
                            'name'        => $field['name'],
                            'parent'      => $field['parent'],
                        ];
                    }
                }
            }
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
            } else {
                switch ($definition['global_type']) {
                    case 'field':
                        $local = &$localDefinitions[$key];
                        $dbdef = &$definition;
                        if ($local['type'] !== $dbdef['type'] || $local['name'] !== $dbdef['name'] ||
                            $local['parent'] !== $dbdef['parent']
                        ) {
                            // ACF Option Pages has internal issue in definition, so skip it:
                            if ('group_572b269b668a4' === $local['parent']) {
                                continue;
                            }

                            return false;
                        }
                        break;
                    case 'group':
                    default:
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

            if (1 === (int) SimpleStorageHelper::get(Bootstrap::DISABLE_ACF_DB_LOOKUP, 0)) {
                $definitions = $localDefinitions;
                $url = admin_url('edit.php?post_type=acf-field-group&page=acf-tools');
                $msg = [
                    'Automatic ACF support is disabled. Please ensure that you use relevant exported ACF configuration.',
                    vsprintf('To export your ACF configuration click <strong><a href="%s">here</a></strong>',[$url]),
                ];
                DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
                $this->getLogger()->notice('Automatic ACF support is disabled.');
            } else {
                $dbDefinitions = $this->collectDefinitions();

                if (false === $this->verifyDefinitions($localDefinitions, $dbDefinitions)) {
                    $url = admin_url('edit.php?post_type=acf-field-group&page=acf-tools');
                    $msg = [
                        'ACF Configuration has been changed.',
                        'Please update groups and fields definitions for all sites (As PHP generated code).',
                        vsprintf('Use <strong><a href="%s">this</a></strong> page to generate export code and add it to your theme or extra plugin.', [$url]),
                    ];
                    DiagnosticsHelper::addDiagnosticsMessage(implode('<br/>', $msg));
                }
                $definitions = array_merge($localDefinitions, $dbDefinitions);
            }

            $this->definitions = $definitions;
            $this->sortFields();
            $this->prepareFilters();
            add_filter(ExportedAPI::FILTER_SMARTLING_REGISTER_FIELD_FILTER, [$this, 'filterSetup'], 1);
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
                $rules[] = [
                    'pattern' => vsprintf('^meta\/%s$', [$this->buildFullFieldName($key)]),
                    'action'  => 'copy',
                ];

            }
        }

        if (0 < count($this->rules['skip'])) {
            foreach ($this->rules['skip'] as $key) {
                $rules[] = [
                    'pattern' => vsprintf('^meta\/%s$', [$this->buildFullFieldName($key)]),
                    'action'  => 'skip',
                ];

            }
        }

        if (0 < count($this->rules['localize'])) {
            foreach ($this->rules['localize'] as $key) {
                $rules[] = [
                    'pattern'       => vsprintf('^meta\/%s$', [$this->buildFullFieldName($key)]),
                    'action'        => 'localize',
                    'value'         => 'reference',
                    'serialization' => 'none',
                    'type'          => $this->getReferencedTypeByKey($key),
                ];

            }
        }

        $this->filters = $rules;
    }

    private function getFieldTypeByKey($key)
    {
        $def = &$this->definitions;

        return array_key_exists($key, $def) && array_key_exists('type', $def[$key]) ? $def[$key]['type'] : false;
    }

    private function getReferencedTypeByKey($key)
    {
        $type = $this->getFieldTypeByKey($key);

        switch ($type) {
            case 'image':
            case 'file':
            case 'gallery':
                return 'media';
                break;
            case 'post_object':
            case 'page_link':
            case 'relationship':
                return 'post';
                break;
            case 'taxonomy':
                return 'taxonomy';
                break;
            default:
                return 'none';
        }
    }

    private function buildFullFieldName($fieldId)
    {
        $definition = &$this->definitions[$fieldId];
        $pattern = '|field_[0-9A-F]{12,12}|ius';
        $prefix = '';
        if (array_key_exists('parent', $definition) && preg_match($pattern, $definition['parent'])) {
            $prefix = $this->buildFullFieldName($definition['parent']) . '_\d+_';
        }

        return $prefix . $definition['name'];
    }

    private function sortFields()
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

        if ((in_array('acf-field', $postTypes, true) && in_array('acf-field-group', $postTypes, true))) {
            return true;
        }

        return false;
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