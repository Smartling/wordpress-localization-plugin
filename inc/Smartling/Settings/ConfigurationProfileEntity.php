<?php

namespace Smartling\Settings;

use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingEntityAbstract;
use Smartling\WP\Controller\ConfigurationProfileFormController as Form;

/**
 * @property int|Locale original_blog_id
 */
class ConfigurationProfileEntity extends SmartlingEntityAbstract
{
    private const REGEX_PROJECT_ID = '([0-9a-f]){9}';

    public const UPLOAD_ON_CHANGE_MANUAL = 0;

    public const UPLOAD_ON_CHANGE_AUTO = 1;

    public const TRANSLATION_PUBLISHING_MODE_NO_CHANGE = 0;
    public const TRANSLATION_PUBLISHING_MODE_PUBLISH = 1;
    public const TRANSLATION_PUBLISHING_MODE_DRAFT = 2;

    protected static function getInstance(): ConfigurationProfileEntity
    {
        return new static();
    }

    public static function getRetrievalTypes(): array
    {
        return [
            'pseudo'    => __('Pseudo'),
            'published' => __('Published'),
        ];
    }

    public static function getFieldLabels(): array
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

    public static function getFieldDefinitions(): array
    {
        return [
            'id'                               => static::DB_TYPE_U_BIGINT . ' ' .
                                                  static::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            'profile_name'                     => static::DB_TYPE_STRING_STANDARD,
            'project_id'                       => 'CHAR(9) NOT NULL',
            'user_identifier'                  => static::DB_TYPE_STRING_STANDARD,
            'secret_key'                       => static::DB_TYPE_STRING_STANDARD,
            'is_active'                        => static::DB_TYPE_UINT_SWITCH,
            'original_blog_id'                 => static::DB_TYPE_U_BIGINT,
            'auto_authorize'                   => static::DB_TYPE_UINT_SWITCH,
            'retrieval_type'                   => static::DB_TYPE_STRING_SMALL,
            'upload_on_update'                 => static::DB_TYPE_UINT_SWITCH,
            'publish_completed'                => static::DB_TYPE_UINT_SWITCH_ON,
            'download_on_change'               => static::DB_TYPE_UINT_SWITCH,
            'clean_metadata_on_download'       => static::DB_TYPE_UINT_SWITCH,
            'always_sync_images_on_upload'     => static::DB_TYPE_UINT_SWITCH,
            'target_locales'                   => 'TEXT NULL',
            'filter_skip'                      => 'TEXT NULL',
            'filter_copy_by_field_name'        => 'TEXT NULL',
            'filter_copy_by_field_value_regex' => 'TEXT NULL',
            'filter_flag_seo'                  => 'TEXT NULL',
            'clone_attachment'                 => static::DB_TYPE_UINT_SWITCH . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'enable_notifications'             => static::DB_TYPE_UINT_SWITCH . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            Form::FILTER_FIELD_NAME_REGEXP     => static::DB_TYPE_UINT_SWITCH . ' ' . static::DB_TYPE_DEFAULT_ZERO,
        ];
    }

    public static function getSortableFields(): array
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

    public static function getIndexes(): array
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

    public static function getTableName(): string
    {
        return 'smartling_configuration_profiles';
    }


    public function getId(): int
    {
        return (int)$this->stateFields['id'];
    }

    public function setId(int $id): void
    {
        $this->stateFields['id'] = $id;
    }

    public function getProfileName(): string
    {
        return (string)$this->stateFields['profile_name'];
    }

    public function setProfileName(string $profileName): void
    {
        $this->stateFields['profile_name'] = $profileName;
    }

    public function getProjectId(): string
    {
        return (string)$this->stateFields['project_id'];
    }

    public function setProjectId(string $projectId): void
    {
        $this->stateFields['project_id'] = $projectId;

        if (!preg_match(vsprintf('/%s/ius', [static::REGEX_PROJECT_ID]), trim($projectId, '/'))) {
            $this->logger->warning(vsprintf('Got invalid project ID: %s', [$projectId]));
        }
    }

    public function getUserIdentifier(): string
    {
        return (string)$this->stateFields['user_identifier'];
    }

    public function setUserIdentifier(string $user_identifier): void
    {
        $this->stateFields['user_identifier'] = $user_identifier;
    }

    public function getSecretKey(): string
    {
        return (string)$this->stateFields['secret_key'];
    }

    public function setSecretKey(string $secret_key): void
    {
        $this->stateFields['secret_key'] = $secret_key;
    }

    public function getIsActive(): int
    {
        return (int)$this->stateFields['is_active'];
    }

    public function setIsActive(int $isActive): void
    {
        $this->stateFields['is_active'] = $isActive;
    }

    public function getOriginalBlogId(): Locale
    {
        return $this->stateFields['original_blog_id'] ?? new Locale();
    }

    public function setLocale(Locale $mainLocale): void
    {
        $this->stateFields['original_blog_id'] = $mainLocale;
    }

    /**
     * Required for parent::fromArray
     * @noinspection PhpUnused
     * @noinspection UnknownInspectionInspection
     */
    public function setOriginalBlogId(int $blogId): void
    {
        $locale = new Locale();
        $locale->setBlogId($blogId);
        $this->setLocale($locale);
    }

    public function getAutoAuthorize(): bool
    {
        return $this->stateFields['auto_authorize'];
    }

    public function setAutoAuthorize(bool $autoAuthorize): void
    {
        $this->stateFields['auto_authorize'] = $autoAuthorize;
    }

    public function getRetrievalType(): string
    {
        return $this->stateFields['retrieval_type'];
    }

    public function setRetrievalType(string $retrievalType): void
    {
        if (array_key_exists($retrievalType, static::getRetrievalTypes())) {
            $this->stateFields['retrieval_type'] = $retrievalType;
        } else {
            $this->logger->warning(vsprintf('Got invalid retrievalType: %s, expected one of: %s',
                                            [$retrievalType, implode(', ', array_keys(static::getRetrievalTypes()))]));
        }
    }

    /**
     * @return TargetLocale[]
     */
    public function getTargetLocales(): array
    {
        if (!array_key_exists('target_locales', $this->stateFields)) {
            $this->setTargetLocales([]);
        }

        return $this->stateFields['target_locales'];
    }

    public function setTargetLocales(array $targetLocales): void
    {
        $this->stateFields['target_locales'] = $targetLocales;
    }

    public function getFilterFieldNameRegExp(): bool
    {
        return $this->stateFields[Form::FILTER_FIELD_NAME_REGEXP] === '1';
    }

    public function setFilterFieldNameRegexp(?bool $value): void
    {
        $this->stateFields[Form::FILTER_FIELD_NAME_REGEXP] = $value ? '1' : '0';
    }

    public function getFilterSkip(): string
    {
        return $this->stateFields['filter_skip'];
    }

    public function getFilterSkipArray(): array
    {
        return array_map('trim', explode(PHP_EOL, $this->getFilterSkip()));
    }

    public function setFilterSkip(string $value): void
    {
        $this->stateFields['filter_skip'] = $value;
    }

    public function getFilterCopyByFieldName(): string
    {
        return $this->stateFields['filter_copy_by_field_name'];
    }

    public function setFilterCopyByFieldName(string $value): void
    {
        $this->stateFields['filter_copy_by_field_name'] = $value;
    }

    public function getFilterCopyByFieldValueRegex(): string
    {
        return $this->stateFields['filter_copy_by_field_value_regex'];
    }

    public function setFilterCopyByFieldValueRegex(string $value): void
    {
        $this->stateFields['filter_copy_by_field_value_regex'] = $value;
    }

    public function getFilterFlagSeo(): string
    {
        return $this->stateFields['filter_flag_seo'];
    }

    public function setFilterFlagSeo(string $value): void
    {
        $this->stateFields['filter_flag_seo'] = $value;
    }

    public function getUploadOnUpdate(): int
    {
        return $this->stateFields['upload_on_update'];
    }

    public function setUploadOnUpdate(int $uploadOnUpdate): void
    {
        $this->stateFields['upload_on_update'] = $uploadOnUpdate;
    }

    public function setDownloadOnChange(int $downloadOnChange): void
    {
        $this->stateFields['download_on_change'] = $downloadOnChange;
    }

    public function getDownloadOnChange(): int
    {
        return (int)$this->stateFields['download_on_change'];
    }

    public function setCleanMetadataOnDownload(int $cleanMetadataOnDownload): void
    {
        $this->stateFields['clean_metadata_on_download'] = $cleanMetadataOnDownload;
    }

    public function getCleanMetadataOnDownload(): int
    {
        return (int)$this->stateFields['clean_metadata_on_download'];
    }

    public function getTranslationPublishingMode(): int
    {
        return (int)$this->stateFields['publish_completed'];
    }

    public function setChangeAssetStatusOnCompletedTranslation(int $status): void
    {
        $this->stateFields['publish_completed'] = $status;
    }

    /**
     * Alias for $this->setChangeAssetStatusOnCompletedTranslation, required for EntityAbstract::fromArray();
     * @noinspection PhpUnused
     * @noinspection UnknownInspectionInspection
     */
    public function setPublishCompleted(int $publishCompleted): void
    {
        $this->stateFields['publish_completed'] = $publishCompleted;
    }

    public function setCloneAttachment(?int $cloneAttachment): void
    {
        $this->stateFields['clone_attachment'] = (int)$cloneAttachment;
    }

    public function getCloneAttachment(): int
    {
        return (int)$this->stateFields['clone_attachment'];
    }

    public function setAlwaysSyncImagesOnUpload(int $alwaysSyncImagesOnUpload): void
    {
        $this->stateFields['always_sync_images_on_upload'] = $alwaysSyncImagesOnUpload;
    }

    public function getAlwaysSyncImagesOnUpload(): int
    {
        return (int)$this->stateFields['always_sync_images_on_upload'];
    }

    public function getEnableNotifications(): int
    {
        return (int)$this->stateFields['enable_notifications'];
    }

    public function setEnableNotifications(?int $enableNotifications): void
    {
        $this->stateFields['enable_notifications'] = (int)$enableNotifications;
    }

    public function toArray($addVirtualColumns = true): array
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

    public static function fromArray(array $array, LoggerInterface $logger): ConfigurationProfileEntity
    {
        if (!array_key_exists('target_locales', $array)) {
            $array['target_locales'] = [];
        }
        if (is_string($array['target_locales'])) {
            $decoded = json_decode($array['target_locales'], true, 512);
            $array['target_locales'] = [];

            if (is_array($decoded)) {
                foreach ($decoded as $targetLocaleArr) {
                    $array['target_locales'][] = TargetLocale::fromArray($targetLocaleArr);
                }
            }
        }

        if (array_key_exists('original_blog_id', $array) && $array['original_blog_id'] instanceof Locale) {
            $array['original_blog_id'] = $array['original_blog_id']->getBlogId();
        }

        return parent::fromArray($array, $logger);
    }

    public function toArraySafe(): array
    {
        $struct = $this->toArray(false);
        unset($struct['secret_key']);
        return $struct;
    }
}
