<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 06.02.2015
 * Time: 9:35
 */

namespace Smartling\Processors;


class PostMapper extends MapperAbstract {

	function __construct () {
		$this->setFields(array(
			"post_title",
			"post_content"
		));
	}
}