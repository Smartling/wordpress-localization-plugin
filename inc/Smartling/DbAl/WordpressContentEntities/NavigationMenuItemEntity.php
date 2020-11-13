<?php

namespace Smartling\DbAl\WordpressContentEntities;

class NavigationMenuItemEntity extends PostEntityStd
{
    /**
     * @return array
     */
    public function toBulkSubmitScreenRow()
    {
        $result = parent::toBulkSubmitScreenRow();
        if ($result['title'] === '') {
            $result['title'] = $this->getMenuItemTitle();
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getMenuItemTitle()
    {
        $meta = get_post_meta($this->getPK());
        $objectIdKey = '_menu_item_object_id';
        if (array_key_exists($objectIdKey, $meta) && is_array($meta[$objectIdKey]) && count($meta[$objectIdKey])) {
            return get_term((int)$meta[$objectIdKey][0])->name;
        }
        return '';
    }
}
