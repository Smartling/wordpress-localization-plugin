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
    const ACTION_SMARTLING_BEFORE_INITIALIZE_EVENT = 'smartling-event.before-initialize';

    /**
     * Is raised just before encoding to XML
     * attributes:
     *  & array Fields from entity and its metadata as they are (may be serialized / combined / encoded )
     *  SubmissionEntity instance of SubmissionEntity
     *  EntityAbstract successor instance (Original Entity)
     *  Original Entity Metadata array
     *
     *
     *  Note! The only prepared array which is going to be serialized into XML is to be received by reference.
     *  You should not change / add / remove array keys.
     *  Only update of values is allowed.
     *  Will be changed to ArrayAccess implementation.
     */
    const EVENT_SMARTLING_BEFORE_SERIALIZE_CONNENT = 'EVENT_SMARTLING_BEFORE_SERIALIZE_CONNENT';

    /**
     * Is raised just after decoding from XML
     * attributes:
     *  & array of translated fields
     *  SubmissionEntity instance of SubmissionEntity
     *  EntityAbstract successor instance (Target Entity)
     *  Target Entity Metadata array
     *
     *
     *
     *  Note! The only translation fields array is to be received by reference.
     *  You should not change / add / remove array keys.
     *  Only update of values is allowed.
     *  Will be changed to ArrayAccess implementation.
     */
    const EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT = 'EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT';

    /**
     * Action that sends given SubmissionEntity to smartling for translation
     */
    const ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION = 'smartling-event.send-for-translation';

    /**
     * Action that downloads translation for given SubmissionEntity
     */
    const ACTION_SMARTLING_DOWNLOAD_TRANSLATION = 'smartling-event.download-translation';
}