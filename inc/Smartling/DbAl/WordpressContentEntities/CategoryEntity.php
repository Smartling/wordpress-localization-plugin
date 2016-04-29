<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class CategoryEntityAbstract
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class CategoryEntity extends TaxonomyEntityAbstract
{
    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setType(WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY);
        $this->setRelatedTypes(
            [
                WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY,
            ]
        );

        $this->setEntityFields($this->fields);
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return $this->name;
    }


}