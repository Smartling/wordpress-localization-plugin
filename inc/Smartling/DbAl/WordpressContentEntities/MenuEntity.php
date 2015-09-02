<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class MenuEntity
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class MenuEntity extends TaxonomyEntityAbstract {
	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
		parent::__construct( $logger );

		$this->setType( WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU );
		$this->setEntityFields( $this->fields );

		$this->setRelatedTypes( [ WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM ] );
	}

	/**
	 * @inheritdoc
	 */
	public function getTitle () {
		return $this->name;
	}
}