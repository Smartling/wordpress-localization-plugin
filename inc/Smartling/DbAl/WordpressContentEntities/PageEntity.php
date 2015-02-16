<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;

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

		$ownFields = array (
			'page_template'
		);

		$this->fields              = array_merge( $this->fields, $ownFields );
		$this->hashAffectingFields = array_merge( array (), $ownFields );

		parent::__construct( $logger );
	}

}