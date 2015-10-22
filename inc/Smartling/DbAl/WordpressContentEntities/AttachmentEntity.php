<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class AttachmentEntity
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class AttachmentEntity extends PostEntity {
	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
		parent::__construct( $logger );

		$ownFields = [];

		$this->fields              = array_merge( $this->fields, $ownFields );
		$this->hashAffectingFields = array_merge( [ ], $ownFields );

		$this->setType( WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT );
	}
}