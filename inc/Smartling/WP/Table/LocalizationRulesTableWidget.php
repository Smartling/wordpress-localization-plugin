<?php

namespace Smartling\WP\Table;

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\MetaFieldProcessor\CustomFieldFilterHandler;
use Smartling\Tuner\FilterManager;

class LocalizationRulesTableWidget extends \WP_List_Table
{
    /**
     * the source array with request data
     * @var array
     */
    private $source;

    private $_settings = [
        'singular' => 'filter',
        'plural'   => 'filters',
        'ajax'     => false,
    ];

    /**
     * @var FilterManager $manager
     */
    private $manager;

    /**
     * @param FilterManager $manager
     */
    public function __construct(FilterManager $manager)
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
                            'smartling_customization_tuning_filter_form',
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
                        '?page=%s&action=%s&type=filter&id=%s',
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
        return vsprintf('%s %s', [esc_html__($item['pattern']), $this->row_actions($actions)]);
    }

    /**
     * @inheritdoc
     */
    public function get_columns()
    {
        return [
            //'id'      => 'Identifier',
            'pattern' => 'Pattern',
            'action'  => 'Action',
            'type'    => 'Type',
        ];

    }

    public function renderNewFilterButton()
    {

        $options = [
            'id'    => $this->buildHtmlTagName('createNew'),
            'name'  => '',
            'class' => 'button action',
            'type'  => 'submit',
            'value' => __('Add Filter'),

        ];

        return HtmlTagGeneratorHelper::tag('input', '', $options);
    }

    /**
     * @inheritdoc
     */
    public function prepare_items()
    {
        $autoDiscoveredFilters = CustomFieldFilterHandler::$filters;
        $this->manager->loadData();
        $managedFilters = [];
        foreach ($this->manager as $id => $filter) {
            if (array_key_exists('pattern', $filter)) {
                $managedFilters[$filter['pattern']] = [
                    'id' => $id,
                    'pattern' => $filter['pattern'],
                    'action' => $filter['action'],
                    'type' => $filter['type'],
                ];
            }
        }

        $this->_column_headers = [$this->get_columns(), [], []];

        foreach ($autoDiscoveredFilters as $i => $filter) {

            $row = [
                'id'      => $i,
                'pattern' => $filter['pattern'],
                'action'  => $filter['action'],
                'type'    => $filter['type'] ?? '',
            ];

            if (array_key_exists($filter['pattern'], $managedFilters)) {
                $row['id'] = $managedFilters[$filter['pattern']]['id'];
                $row['pattern'] = $this->applyRowActions($row);

            } else {
                $row['pattern'] = HtmlTagGeneratorHelper::tag('span', $row['pattern'], [
                    'class' => 'nonmanaged',
                    'title' => 'That filter is automatically discovered and processed by smartling-connector',
                ]);
            }

            if (in_array($row['action'], ['copy', 'skip'])) {
                $row['type']='';
            }

            $dataAsArray[] = $row;
        }

        $this->items = $dataAsArray;
    }
}
