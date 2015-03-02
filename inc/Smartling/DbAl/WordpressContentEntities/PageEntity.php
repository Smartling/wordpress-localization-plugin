<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PageEntity
 *
 * @property string page_template
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class PageEntity extends PostEntity {

	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
		parent::__construct( $logger );

		$ownFields = array (
			'page_template'
		);

		$this->fields              = array_merge( $this->fields, $ownFields );
		$this->hashAffectingFields = array_merge( array (), $ownFields );

		$this->setType( WordpressContentTypeHelper::CONTENT_TYPE_PAGE );
	}

}