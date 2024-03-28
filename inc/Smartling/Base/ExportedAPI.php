<?php

namespace Smartling\Base;

use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\EventParameters\BeforeSerializeContentEventParameters;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\EventParameters\SmartlingFileUriFilterParamater;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\Models\NotificationParameters;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Interface ExportedAPI
 * Contains hooks list that are exported for use
 * @package Smartling\Base
 */
interface ExportedAPI
{
    /**
     * An action that is executed just after DI initialization
     * @param ContainerBuilder
     */
    public const ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT = 'smartling_before_init';

    /**
     * Is raised just before encoding to XML
     * @param BeforeSerializeContentEventParameters
     * attributes:
     *  & array Fields from entity and its metadata as they are (may be serialized / combined / encoded )
     *  SubmissionEntity instance of SubmissionEntity
     *  EntityAbstract successor instance (Original Entity)
     *  Original Entity Metadata array
     *  Note! The only prepared array which is going to be serialized into XML is to be received by reference.
     *  You should not change / add / remove array keys.
     *  Only update of values is allowed.
     *  Will be changed to ArrayAccess implementation.
     */
    public const EVENT_SMARTLING_BEFORE_SERIALIZE_CONTENT = 'smartling_before_serialize_content';

    /**
     * Is raised just after decoding from XML
     * @param AfterDeserializeContentEventParameters
     * attributes:
     *  & array of translated fields
     *  SubmissionEntity instance of SubmissionEntity
     *  EntityAbstract successor instance (Target Entity)
     *  Target Entity Metadata array
     *  Note! The only translation fields array is to be received by reference.
     *  You should not change / add / remove array keys.
     *  Only update of values is allowed.
     *  Will be changed to ArrayAccess implementation.
     */
    public const EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT = 'smartling_after_deserialize_content';

    /**
     * Action that prepares submission for upload and creates target placeholders
     * @param SubmissionEntity
     */
    public const ACTION_SMARTLING_PREPARE_SUBMISSION_UPLOAD = 'smartling_prepare_submission_upload';

    /**
     * Action that sends given SubmissionEntity to smartling for translation
     * @param SubmissionEntity
     */
    public const ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION = 'smartling_send_for_translation';

    /**
     * Action that clones content of given SubmissionEntity without translation
     * @param SubmissionEntity
     */
    public const ACTION_SMARTLING_CLONE_CONTENT = 'smartling_clone_content';

    /**
     * Action that downloads translation for given SubmissionEntity
     * @param SubmissionEntity
     */
    public const ACTION_SMARTLING_DOWNLOAD_TRANSLATION = 'smartling_download_translation';

    /**
     * Action regenerates thumbnails for translation by submission
     * @param SubmissionEntity
     */
    public const ACTION_SMARTLING_REGENERATE_THUMBNAILS = 'smartling_regenerate_thumbnails';

    /**
     * Action for registration a content-type. Only one param is given:
     * @param ContainerBuilder
     */
    public const ACTION_SMARTLING_REGISTER_CONTENT_TYPE = 'smartling_register_content_type';

    /**
     * Action for processing terms related to term / post-based content
     * @param ProcessRelatedContentParams
     */
    public const ACTION_SMARTLING_PROCESSOR_RELATED_CONTENT = 'smartling_processor_related_content';

    /**
     * Action that syncs attachment by submission
     * @param SubmissionEntity
     */
    public const ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT = 'smartling_sync_media_attachment';

    /**
     * Filter to modify FileURI.
     * @param SmartlingFileUriFilterParamater
     * @return SmartlingFileUriFilterParamater
     * Filter should return instance of Smartling\Helpers\EventParameters\SmartlingFileUriFilterParameter
     * otherwise generated fileURI is taken
     * Filter should return fileUri with length > 0
     * otherwise generated fileURI is taken
     */
    public const FILTER_SMARTLING_FILE_URI = 'smartling_file_uri';

    /**
     * Filter to modify the XML node that is going to be sent to smartling.
     * @param TranslationStringFilterParameters
     * @return TranslationStringFilterParameters
     */
    public const FILTER_SMARTLING_TRANSLATION_STRING = 'smartling_translation_string_before_send';

    /**
     * Filter to modify the translated XML node that is received from smartling.
     * @param TranslationStringFilterParameters
     * @return TranslationStringFilterParameters
     */
    public const FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED = 'smartling_translation_string_received';

    /**
     * Filter to modify meta value on translation
     * @param mixed metadata field name
     * @param mixed metadata field value
     * @param SubmissionEntity
     */
    public const FILTER_SMARTLING_METADATA_FIELD_PROCESS = 'smartling_metadata_string_process';

    /**
     * Filter to modify meta value on translation
     * @param SubmissionEntity
     * @param mixed field name
     * @param mixed field value
     * @param array collected values
     */
    public const FILTER_SMARTLING_METADATA_PROCESS_BEFORE_TRANSLATION = 'smartling_metadata_process_before_translation';

    /**
     * Filter processes given SubmissionEntity and creates corresponding target entity if it does not exist.
     * Filter doesn't work for cloning
     * @param SubmissionEntity
     * @return SubmissionEntity
     */
    public const FILTER_SMARTLING_PREPARE_TARGET_CONTENT = 'smartling_prepare_target_content';

    /**
     * Filter allows to let smartling-connector know about shortcodes that are not registered
     * when content is going to be sent for translation
     * @param array
     * @return array
     */
    public const FILTER_SMARTLING_INJECT_SHORTCODE = 'smartling_inject_shortcode';

    /**
     * Handler should add an array that defines a post type.
     * @param array
     * @return array
     */
    public const FILTER_SMARTLING_REGISTER_CUSTOM_POST_TYPE = 'smartling_register_custom_type';

    /**
     * Handler should add an array that defines a taxonomy type.
     * @param array
     * @return array
     */
    public const FILTER_SMARTLING_REGISTER_CUSTOM_TAXONOMY = 'smartling_register_custom_taxonomy';

    /**
     * Handler should add an array that defines a filter.
     * @param array
     * @return array
     */
    public const FILTER_SMARTLING_REGISTER_FIELD_FILTER = 'smartling_register_field_filter';

    /**
     * Filter fires for notifications
     * @param NotificationParameters
     */
    public const ACTION_SMARTLING_PUSH_LIVE_NOTIFICATION = 'smartling_push_notification';

    /**
     * Filter prepares contentId to later use with push live notification (Firebase specific)
     * @param SubmissionEntity
     */
    public const ACTION_SMARTLING_PLACE_RECORD_ID = 'smartling_place_record_id';

    /**
     * @param SubmissionEntity
     */
    public const ACTION_AFTER_TARGET_CONTENT_WRITTEN = 'smartling_action_after_target_content_written';

    public const ACTION_AFTER_TARGET_METADATA_WRITTEN = 'smartling_action_after_target_metadata_written';

    /**
     * @param SubmissionEntity
     */
    public const ACTION_AFTER_TRANSLATION_APPLIED = 'smartling_action_after_translation_applied';

    /**
     * @param array content
     * @param SubmissionEntity
     * @return array content
     */
    public const FILTER_BEFORE_CLONE_CONTENT_WRITTEN = 'smartling_filter_before_clone_content_written';
    /**
     * @param array translation
     * @param array lockedData
     * @param SubmissionEntity
     * @return array translation
     */
    public const FILTER_BEFORE_TRANSLATION_APPLIED = 'smartling_filter_before_translation_applied';

    /**
     * @param array items
     */
    public const FILTER_BULK_SUBMIT_PREPARE_ITEMS = 'smartling_filter_bulk_submit_prepare_items';
}
