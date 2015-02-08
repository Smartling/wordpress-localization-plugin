<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 06.02.2015
 * Time: 9:33
 */

namespace Smartling\Processors;


class MapperAbstract {

	/**
	 * @var array
	 */
	private $fields;

	/**
	 * return array
	 */
	public function getFields() {
		return $this->fields;
	}

	/**
	 * @param array
	 */
	protected function setFields($fields) {
		$this->fields = $fields;
	}
}