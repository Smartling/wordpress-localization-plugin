<?php

namespace Smartling\WP\Table;

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Tuner\ShortcodeManager;

class ShortcodeTableClass extends \WP_List_Table
{
    /**
     * the source array with request data
     * @var array
     */
    private $source;

    private $_settings = [
        'singular' => 'shortcode',
        'plural'   => 'shortcodes',
        'ajax'     => false,
    ];

    /**
     * @var ShortcodeManager $manager
     */
    private $manager;

    /**
     * @param ShortcodeManager $manager
     */
    public function __construct(ShortcodeManager $manager)
    {
        $this->manager = $manager;
        $this->source = &$_REQUEST;
        parent::__construct($this->_settings);
    }

    /**
     * @param $item
     * @param $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    /**
     * @param $item
     *
     * @return string
     */
    public function applyRowActions($item)
    {
        $actions = [
            'edit'   => HtmlTagGeneratorHelper::tag(
                'a',
                __('Edit'),
                [
                    'href' => vsprintf(
                        '?page=%s&action=%s&id=%s',
                        [
                            'smartling_customization_tuning_shortcode_form',
                            'edit',
                            $item['id'],
                        ]
                    ),
                ]
            ),
            'delete' => HtmlTagGeneratorHelper::tag(
                'a',
                __('Delete'),
                [
                    'href' => vsprintf(
                        '?page=%s&action=%s&type=shortcode&id=%s',
                        [
                            'smartling_customization_tuning',
                            'delete',
                            $item['id'],
                        ]
                    ),
                ]
            ),
        ];

        //Return the title contents
        return vsprintf('%s %s', [esc_html__($item['name']), $this->row_actions($actions)]);
    }

    /**
     * @inheritdoc
     */
    public function get_columns()
    {
        return [
            //'id'   => 'Identifier',
            'name' => 'Name',
        ];

    }

    public function renderNewProfileButton()
    {

        $options = [
            'id'    => $this->buildHtmlTagName('createNew'),
            'name'  => '',
            'class' => 'button action',
            'type'  => 'submit',
            'value' => __('Add Shortcode'),

        ];

        return HtmlTagGeneratorHelper::tag('input', '', $options);
    }

    /**
     * @inheritdoc
     */
    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->manager->loadData();
        $data = $this->manager->listItems();

        foreach ($data as $id => $element) {
            $row = $element;
            $row['id'] = $id;
            $row['name'] = $this->applyRowActions($row);
            $dataAsArray[] = $row;
        }

        global $shortcode_tags;

        foreach (array_keys($shortcode_tags) as $capturedShortcode) {
            $dataAsArray[] = [
                'id'   => null,
                'name' => HtmlTagGeneratorHelper::tag('span', $capturedShortcode, [
                    'class' => 'nonmanaged',
                    'title' => 'That shortcode is automatically discovered and processed by smartling-connector',
                ]),
            ];
        }

        $this->items = $dataAsArray;
    }
}
