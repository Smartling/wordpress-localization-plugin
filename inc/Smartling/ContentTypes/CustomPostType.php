<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\StringHelper;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class CustomPostType
 * @package Smartling\ContentTypes
 */
class CustomPostType extends PostBasedContentTypeAbstract
{
    /**
     * @var string
     */
    private $systemName = '';

    /**
     * @var array
     */
    private $config = [];

    /**
     * @var string
     */
    private $label = '';

    /**
     * @var PostTypeConfigParser
     */
    private $typeValidator;

    /**
     * @return string
     */
    public function getSystemName()
    {
        return $this->systemName;
    }

    /**
     * @param string $systemName
     */
    public function setSystemName($systemName)
    {
        $this->systemName = $systemName;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     *
     * @return CustomPostType
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Display name of content type, e.g.: Post
     *
     * @param string $default
     *
     * @return string
     */
    public function getLabel($default = 'unknown')
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return PostTypeConfigParser
     */
    public function getTypeValidator()
    {
        return $this->typeValidator;
    }

    /**
     * @param PostTypeConfigParser $typeValidator
     */
    public function setTypeValidator($typeValidator)
    {
        $this->typeValidator = $typeValidator;
    }


    /**
     * @param ContainerBuilder $di
     * @param array            $config
     */
    public static function registerCustomType(ContainerBuilder $di, array $config)
    {
        $manager = 'content-type-descriptor-manager';

        $descriptor = new static($di);
        $descriptor->setConfig($config);
        $descriptor->validateConfig();

        if ($descriptor->isValidType()) {

            $descriptor->registerIOWrapper();
            $descriptor->registerWidgetHandler();

            $mgr = $di->get($manager);
            /**
             * @var \Smartling\ContentTypes\ContentTypeManager $mgr
             */
            $mgr->addDescriptor($descriptor);
        }

        $descriptor->registerFilters();
    }

    /**
     * @return bool
     */
    public function isValidType()
    {
        if (!$this->getTypeValidator()->isValidType()){
            return false;
        }

        /**
         * Check if identifier already registered
         */

        $mgr = $this->getContainerBuilder()->get('content-type-descriptor-manager');
        /**
         * @var ContentTypeManager $mgr
         */
        $registered = null;

        try {
            $mgr->getDescriptorByType($this->getSystemName());

            return false;
        } catch (SmartlingInvalidFactoryArgumentException $e) {
        }

        return true;
    }

    public function validateConfig()
    {
        $config = $this->getConfig();

        if (array_key_exists('type', $config)) {
            $this->validateType();
        }

        $this->setConfig($config);
    }

    private function validateType()
    {
        $validator = new PostTypeConfigParser($this->getConfig());

        if (!StringHelper::isNullOrEmpty($validator->getIdentifier())){
            $this->setSystemName($validator->getIdentifier());

            $label = 'unknown' === parent::getLabel() ? $this->getSystemName() : parent::getLabel();

            $this->setLabel($label);

        }

        $this->setTypeValidator($validator);
    }

    private function validateFilters($config)
    {
        /**
         * [
         * "filed_pattern" => "<field name or regex>",  // required
         * "behavior"      => [ // required
         * // What to do with field value:
         * // * "copy" - simple copy value from source to target
         * // * "skip" - don't copy value. Target will have empty value unless it will be populated somehow else
         * // * "localize" - send value for translation or transform it. Use this action for references also
         * "action"        => "copy|skip|localize",  // required
         * // Preprocess value before localize it
         * // * "none" - no extra preprocessing for value
         * // * "delimited" - value should be converted to array. An example "1, 4, 10"
         * // * "array" - value is serialized php array
         * // * "json" - value is json
         * "serialization" => "none|delimited|array|json",
         * // optional, default none
         * // How to handle value
         * // * "raw" - use value as is
         * // * "reference" - value is reference to another entity (post\tag\attachment\etc)
         * // * "url" - value is url to attachment (file\image)
         * "value"         => "raw|reference|url", // required
         * // Type of reference\url
         * // * "attachment" - id or url to file
         * // * "image" - id or url to image
         * // * "post" - reference to post\page\post based custom type
         * // * "<taxonomy name>" - actual taxonomy name (tag, category for standard taxonomy)
         * "type"          => "attachment|image|post|<taxonomy name>",
         * // if action != skip ==> required
         * ],
         * ],
         */
    }

    /**
     * Handler to register IO Wrapper
     * @return void
     */
    public function registerIOWrapper()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, 'Smartling\DbAl\WordpressContentEntities\PostEntityStd');
        $definition
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($this->getSystemName())
            ->addArgument($this->getRelatedTaxonomies());
        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));
    }

    /**
     * Handler to register Widget (Edit Screen)
     * @return void
     */
    public function registerWidgetHandler()
    {

    }

    public function registerTaxonomyRelations(ProcessRelatedContentParams $params)
    {
        if ($this->getSystemName() === $params->getSubmission()->getContentType()) {
            /**
             * @var CustomMenuContentTypeHelper $helper
             */
            $helper = $this->getContainerBuilder()->get('helper.customMenu');
            $terms = $helper->getTerms($params->getSubmission(), $params->getContentType());
            if (0 < count($terms)) {
                foreach ($terms as $element) {
                    $this->getContainerBuilder()->get('logger')
                        ->debug(vsprintf('Sending for translation term = \'%s\' id = \'%s\' related to submission = \'%s\'.', [
                            $element->taxonomy,
                            $element->term_id,
                            $params->getSubmission()->getId(),
                        ]));

                    /**
                     * @var TranslationHelper $translationHelper
                     */
                    $translationHelper = $this->getContainerBuilder()->get('translation.helper');

                    $relatedSubmission = $translationHelper
                        ->tryPrepareRelatedContent(
                            $element->taxonomy,
                            $params->getSubmission()->getSourceBlogId(),
                            $element->term_id,
                            $params->getSubmission()->getTargetBlogId()
                        );
                    $params->getAccumulator()[$params->getContentType()][] = $relatedSubmission->getTargetId();
                    $this->getContainerBuilder()
                        ->get('logger')
                        ->debug(
                            vsprintf(
                                'Received id=%s for submission id=%s',
                                [
                                    $relatedSubmission->getTargetId(),
                                    $relatedSubmission->getId(),
                                ]
                            )
                        );
                }
            }
        }
    }

    /**
     * @return void
     */
    public function registerFilters()
    {
        if (0 < count($this->getRelatedTaxonomies())) {
            add_action(ExportedAPI::ACTION_SMARTLING_PROCESSOR_RELATED_CONTENT, [$this, 'registerTaxonomyRelations']);
        }
    }

    public function getVisibility()
    {
        $config = $this->getConfig();

        return $config['type']['visibility'];
    }
}