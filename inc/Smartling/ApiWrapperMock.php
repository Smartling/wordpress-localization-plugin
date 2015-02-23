<?php

namespace Smartling;

/**
 * Class ApiWrapperMock
 *
 * @package Smartling
 */
class ApiWrapperMock extends ApiWrapper {

	/**
	 * @inheritdoc
	 */
	public function setApi () {
		$this->api = new SmartlingApiMock();
	}
}