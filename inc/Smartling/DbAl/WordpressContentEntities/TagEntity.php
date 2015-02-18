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
		$this->setTaxonomyType( WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG );
		parent::__construct( $logger );
		$this->setEntityFields( $this->fields );
	}

	/**
	 * @inheritdoc
	 */
	public function getTitle () {
		return $this->name;
	}


}