<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class CategoryEntityAbstract
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class TagEntity extends TaxonomyEntityAbstract {
	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
		parent::__construct( $logger );

		$this->setType( WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG );
		$this->setEntityFields( $this->fields );
	}

	/**
	 * @inheritdoc
	 */
	public function getTitle () {
		return $this->name;
	}
}