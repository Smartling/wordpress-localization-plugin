<?php

namespace Smartling\WP\Table;

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\StringHelper;
use Smartling\WP\Controller\SmartlingListTable;

class TranslationLockTableWidget extends SmartlingListTable
{

    /**
     * @var array
     */
    private $data = [];

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    public function get_columns()
    {
        return [
            'name'   => __('Field Name'),
            'value'  => __('Field Value'),
            'locked' => __('Is Locked'),
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            default:
                return $item[$column_name];
        }
    }


    public function prepare_items()
    {
        foreach ($this->data as & $datum) {

            $options = [
                'type'  => 'checkbox',
                'name'  => vsprintf('lockField[%s]', [$datum['name']]),
                'class' => 'field_lock_element',
            ];

            if (true === $datum['locked']) {
                $options['checked'] = 'checked';
            }

            $datum['locked'] = HtmlTagGeneratorHelper::tag('input', '', $options);

            $datum['name'] = StringHelper::safeHtmlStringShrink($datum['name'], 50);
            $datum['value'] = StringHelper::safeHtmlStringShrink($datum['value'], 50);


        }

        $columns = $this->get_columns();
        $this->_column_headers = array($columns, [], []);
        $this->items = $this->data;
    }

}