<?php

namespace Smartling\Settings;

use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingEntityAbstract;

/**
 * Class ConfigurationProfileEntity
 *
 * @package Smartling\Settings
 */
class ConfigurationProfileEntity extends SmartlingEntityAbstract
{

    const REGEX_PROJECT_ID = '([0-9a-f]){9}';

    const UPLOAD_ON_CHANGE_MANUAL = 0;
    
    const UPLOAD_ON_CHANGE_AUTO = 1;
    
    protected static function getInstance(LoggerInterface $logger)
    {
        return new self($logger);
    }

    public static function getRetrievalTypes()
    {
        return [
            'pseudo'    => __('Pseudo'),
            'published' => __('Published'),
            //'pending'   => __('Pending'),
        ];
    }

    /**
     * @return array
     */
    public static function getFieldLabels()
    {
        return [
            'id'                               => __('ID'),
            'profile_name'                     => __('Profile Name'),
            'project_id'                       => __('Project ID'),
            'user_identifier'                  => __('User Identifier'),
            'is_active'                        => __('Active'),
            'original_blog_id'                 => __('Main Locale'),
            'auto_authorize'                   => __('Auto Authorize'),
            'retrieval_type'                   => __('Retrieval Type'),
            'filter_skip'                      => __('Exclude fields by field name'),
            'filter_copy_by_field_name'        => __('Copy fields by field name'),
            'filter_copy_by_field_value_regex' => __('Copy by field value'),
            'filter_flag_seo'                  => __('SEO fields by field name'),
        ];
    }

    /**
     * @return array
     */
    public static function getFieldDefinitions()
    {

        return [
            'id'                               => self::DB_TYPE_U_BIGINT . ' ' .
                                                  self::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            'profile_name'                     => self::DB_TYPE_STRING_STANDARD,
            'project_id'                       => 'CHAR(9) NOT NULL',
            'user_identifier'                  => self::DB_TYPE_STRING_STANDARD,
            'secret_key'                       => self::DB_TYPE_STRING_STANDARD,
            'is_active'                        => self::DB_TYPE_UINT_SWITCH,
            'original_blog_id'                 => self::DB_TYPE_U_BIGINT,
            'auto_authorize'                   => self::DB_TYPE_UINT_SWITCH,
            'retrieval_type'                   => self::DB_TYPE_STRING_SMALL,
            'upload_on_update'                 => self::DB_TYPE_UINT_SWITCH,
            'publish_completed'                => self::DB_TYPE_UINT_SWITCH_ON,
            'download_on_change'               => self::DB_TYPE_UINT_SWITCH,
            'clean_metadata_on_download'       => self::DB_TYPE_UINT_SWITCH,
            'target_locales'                   => 'TEXT NULL',
            'filter_skip'                      => 'TEXT NULL',
            'filter_copy_by_field_name'        => 'TEXT NULL',
            'filter_copy_by_field_value_regex' => 'TEXT NULL',
            'filter_flag_seo'                  => 'TEXT NULL',
        ];
    }

    /**
     * @return array
     */
    public static function getSortableFields()
    {
        return [
            'profile_name',
            'project_id',
            'is_active',
            'original_blog_id',
            'auto_authorize',
            'retrieval_type',
            'user_identifier',
        ];
    }

    /**
     * @return array
     */
    public static function getIndexes()
    {
        return [
            [
                'type'    => 'primary',
                'columns' => ['id'],
            ],
            [
                'type'    => 'index',
                'columns' => ['original_blog_id', 'is_active'],
            ],
        ];
    }

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'smartling_configuration_profiles';
    }


    /**
     * @return int
     */
    public function getId()
    {
        return (int)$this->stateFields['id'];
    }

    /**
     * @param $id
     */
    public function setId($id)
    {
        $this->stateFields['id'] = (int)$id;
    }

    /**
     * @return mixed
     */
    public function getProfileName()
    {
        return $this->stateFields['profile_name'];
    }

    /**
     * @param $profileName
     */
    public function setProfileName($profileName)
    {
        $this->stateFields['profile_name'] = $profileName;
    }

    /**
     * @return mixed
     */
    public function getProjectId()
    {
        return $this->stateFields['project_id'];
    }

    /**
     * @param $projectId
     */
    public function setProjectId($projectId)
    {
        $this->stateFields['project_id'] = $projectId;

        if (!preg_match(vsprintf('/%s/ius', [self::REGEX_PROJECT_ID]), trim($projectId, '/'))) {
            $this->logger->warning(vsprintf('Got invalid project ID: %s', [$projectId]));
        }
    }

    public function getUserIdentifier()
    {
        return $this->stateFields['user_identifier'];
    }

    public function setUserIdentifier($user_identifier)
    {
        $this->stateFields['user_identifier'] = $user_identifier;
    }

    public function getSecretKey()
    {
        return $this->stateFields['secret_key'];
    }

    public function setSecretKey($secret_key)
    {
        $this->stateFields['secret_key'] = $secret_key;
    }

    /**
     * @return mixed
     */
    public function getIsActive()
    {
        return $this->stateFields['is_active'];
    }

    /**
     * @param $isActive
     */
    public function setIsActive($isActive)
    {
        $this->stateFields['is_active'] = (int)$isActive;
    }

    /**
     * @return Locale
     */
    public function getOriginalBlogId()
    {
        return $this->stateFields['original_blog_id'];
    }

    public function setOriginalBlogId($mainLocale)
    {
        $this->stateFields['original_blog_id'] = $mainLocale;
    }

    public function getAutoAuthorize()
    {
        return $this->stateFields['auto_authorize'];
    }

    public function setAutoAuthorize($autoAuthorize)
    {
        $this->stateFields['auto_authorize'] = (bool)$autoAuthorize;
    }

    public function getRetrievalType()
    {
        return $this->stateFields['retrieval_type'];
    }

    public function setRetrievalType($retrievalType)
    {
        if (array_key_exists($retrievalType, self::getRetrievalTypes())) {
            $this->stateFields['retrieval_type'] = $retrievalType;
        } else {
            $this->logger->warning(vsprintf('Got invalid retrievalType: %s, expected one of: %s',
                [$retrievalType, implode(', ', array_keys(self::getRetrievalTypes()))]));
        }
    }

    /**
     * @return TargetLocale[]
     */
    public function getTargetLocales()
    {
        if (!array_key_exists('target_locales', $this->stateFields)) {
            $this->setTargetLocales([]);
        }

        return $this->stateFields['target_locales'];
    }

    public function setTargetLocales($targetLocales)
    {
        $this->stateFields['target_locales'] = $targetLocales;
    }


    public function getFilterSkip()
    {
        return $this->stateFields['filter_skip'];
    }

    public function setFilterSkip($value)
    {
        $this->stateFields['filter_skip'] = $value;
    }

    public function getFilterCopyByFieldName()
    {
        return $this->stateFields['filter_copy_by_field_name'];
    }

    public function setFilterCopyByFieldName($value)
    {
        $this->stateFields['filter_copy_by_field_name'] = $value;
    }

    public function getFilterCopyByFieldValueRegex()
    {
        return $this->stateFields['filter_copy_by_field_value_regex'];
    }

    public function setFilterCopyByFieldValueRegex($value)
    {
        $this->stateFields['filter_copy_by_field_value_regex'] = $value;
    }

    public function getFilterFlagSeo()
    {
        return $this->stateFields['filter_flag_seo'];
    }

    public function setFilterFlagSeo($value)
    {
        $this->stateFields['filter_flag_seo'] = $value;
    }

    public function getUploadOnUpdate()
    {
        return (int)$this->stateFields['upload_on_update'];
    }

    public function setUploadOnUpdate($uploadOnUpdate)
    {
        $this->stateFields['upload_on_update'] = (int)$uploadOnUpdate;
    }

    public function setDownloadOnChange($downloadOnChange)
    {
        $this->stateFields['download_on_change'] = (int)$downloadOnChange;
    }

    public function getDownloadOnChange()
    {
        return (int) $this->stateFields['download_on_change'];
    }

    public function setCleanMetadataOnDownload($cleanMetadataOnDownload)
    {
        $this->stateFields['clean_metadata_on_download'] = (int)$cleanMetadataOnDownload;
    }

    public function getCleanMetadataOnDownload()
    {
        return (int) $this->stateFields['clean_metadata_on_download'];
    }

    public function getPublishCompleted()
    {
        return (int) $this->stateFields['publish_completed'];
    }

    public function setPublishCompleted($publishCompleted)
    {
        $this->stateFields['publish_completed'] = (int)$publishCompleted;
    }

    public function toArray($addVirtualColumns = true)
    {
        $state = parent::toArray(false);

        $state['original_blog_id'] = $this->getOriginalBlogId()->getBlogId();

        $state['auto_authorize'] = !$state['auto_authorize'] ? 0 : 1;
        $state['is_active'] = !$state['is_active'] ? 0 : 1;

        $serializedTargetLocales = [];
        if (0 < count($this->getTargetLocales())) {
            foreach ($this->getTargetLocales() as $targetLocale) {
                $serializedTargetLocales[] = $targetLocale->toArray();
            }
        }
        $state['target_locales'] = json_encode($serializedTargetLocales);

        return $state;
    }

    public static function fromArray(array $array, LoggerInterface $logger)
    {

        if (!array_key_exists('target_locales', $array)) {
            $array['target_locales'] = '';
        }

        /**
         * @var ConfigurationProfileEntity $obj
         */
        $obj = parent::fromArray($array, $logger);

        $locale = new Locale();
        $locale->setBlogId($obj->getOriginalBlogId());

        $obj->setOriginalBlogId($locale);

        $unserializedTargetLocales = [];

        $curLocales = $obj->getTargetLocales();

        if (is_string($curLocales)) {
            $decoded = json_decode($curLocales, true);

            if (is_array($decoded)) {
                foreach ($decoded as $targetLocaleArr) {
                    $unserializedTargetLocales[] = TargetLocale::fromArray($targetLocaleArr);
                }
                $obj->setTargetLocales($unserializedTargetLocales);

            } else {
                $obj->setTargetLocales([]);
            }
        }

        return $obj;
    }
}