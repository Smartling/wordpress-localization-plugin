<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class CategoryEntityAbstract
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class CategoryEntity extends TaxonomyEntityAbstract {

	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->setTaxonomyType( WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY );
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