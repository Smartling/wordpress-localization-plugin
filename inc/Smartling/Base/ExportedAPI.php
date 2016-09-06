<?php

namespace Smartling\Base;

/**
 * Interface ExportedAPI
 * Contains hooks list that are exported for use
 * @package Smartling\Base
 */
interface ExportedAPI
{
    /**
     * An action that is executed just after DI initialization
     * @argument reference to instance of Symfony\Component\DependencyInjection\ContainerInterface
     */
    const ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT = 'smartling_before_init';

    /**
     * Is raised just before encoding to XML
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
    const EVENT_SMARTLING_BEFORE_SERIALIZE_CONTENT = 'smartling_before_serialize_content';

    /**
     * Is raised just after decoding from XML
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
    const EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT = 'smartling_after_deserialize_content';

    /**
     * Action that sends given SubmissionEntity to smartling for translation
     */
    const ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION = 'smartling_send_for_translation';

    /**
     * Action that clones content of given SubmissionEntity without translation
     */
    const ACTION_SMARTLING_CLONE_CONTENT = 'smartling_clone_content';

    /**
     * Action that downloads translation for given SubmissionEntity
     */
    const ACTION_SMARTLING_DOWNLOAD_TRANSLATION = 'smartling_download_translation';

    /**
     * Action regenerates thumbnails for translation by submission
     */
    const ACTION_SMARTLING_REGENERATE_THUMBNAILS = 'smartling_regenerate_thumbnails';

    /**
     * Filter to modify FileURI.
     * Receives 1 parameter  Smartling\Helpers\EventParameters\SmartlingFileUriFilterParamater
     * Filter should return instance of Smartling\Helpers\EventParameters\SmartlingFileUriFilterParamater
     * otherwise generated fileURI is taken
     * Filter should return fileUri with length > 0
     * otherwise generated fileURI is taken
     */
    const FILTER_SMARTLING_FILE_URI = 'smartling_file_uri';

    /**
     * Filter to modify the XML node that is going to be sent to smartling.
     * Receives 1 parameter TranslationStringFilterParameters
     * Should return TranslationStringFilterParameters
     */
    const FILTER_SMARTLING_TRANSLATION_STRING = 'smartling_translation_string_before_send';

    /**
     * Filter to modify the translated XML node that is received from smartling.
     * Receives 1 parameter TranslationStringFilterParameters
     * Should return TranslationStringFilterParameters
     */
    const FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED = 'smartling_translation_string_received';

    /**
     * Filter to modify meta value on translation
     * receives 3 params:
     *  metadata Field Name
     *  metadata Field Value
     *  SubmissionEntity instance
     */
    const FILTER_SMARTLING_METADATA_FIELD_PROCESS = 'smartling_metadata_string_process';

    /**
     * Filter to modify meta value on translation
     * receives 3 params:
     *  metadata Field Name
     *  metadata Field Value
     *  SubmissionEntity instance
     */
    const FILTER_SMARTLING_METADATA_PROCESS_BEFORE_TRANSLATION = 'smartling_metadata_process_before_translation';
}
