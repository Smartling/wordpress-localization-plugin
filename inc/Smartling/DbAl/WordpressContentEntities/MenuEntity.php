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
	}

	/**
	 * @inheritdoc
	 */
	public function getTitle () {
		return $this->name;
	}

	/**
	 *
	 */
	public function getMetadata () {
		// getting ids of menu_items
		$items = get_objects_in_term( $this->term_id, $this->getType() );

		$objects = [ ];
		foreach ( $items as $item ) {
			$item      = (int) $item;
			$entity    = new MenuItemEntity( $this->getLogger() );
			$entity    = $entity->get( $item );
			$arr       = [
				'entity' => $entity->toArray(),
				'meta'   => $entity->getMetadata(),
			];
			$objects[] = $arr;
		}

		return $objects;
	}

	public function setMetaTag ( $tagName, $tagValue, $unique = true ) {

	}
}