<?php

namespace Smartling\Helpers;

class ContentTypeHelper extends HelperAbstract
{
    const CONTENT_TYPE_POST         =   'post';

    const CONTENT_TYPE_PAGE         =   'page';

    const CONTENT_TYPE_CATEGORY     =   'category';

    private $_reverse_map = array(
        'post'      => self::CONTENT_TYPE_POST,
        'page'      => self::CONTENT_TYPE_PAGE,
        'category'  => self::CONTENT_TYPE_CATEGORY
    );

    /**
     * @return array
     */
    public function getReverseMap()
    {
        return $this->_reverse_map;
    }
}