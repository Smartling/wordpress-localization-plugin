<?php

namespace Smartling\Processors\ContentTypeMapper;

/**
 * Class NoTypeMapper
 *
 * @package Smartling\Processors\ContentTypeMapper
 */
class NoTypeMapper extends MapperAbstract {

	/**
	 * @inheritdoc
	 */
	public function addFields ( array $fields ) {
		parent::addField( $fields );
	}

}