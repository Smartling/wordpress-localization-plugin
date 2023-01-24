<?php

namespace Smartling\WP\Table;

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Tuner\ShortcodeManager;
use Smartling\WP\Controller\AdminPage;

class ShortcodeTableClass extends \WP_List_Table
{
    private array $_settings = [
        'singular' => 'shortcode',
        'plural'   => 'shortcodes',
        'ajax'     => false,
    ];

    private ShortcodeManager $manager;

    public function __construct(ShortcodeManager $manager)
    {
        $this->manager = $manager;
        parent::__construct($this->_settings);
    }

    public function column_default($item, $column_name): mixed
    {
        return $item[$column_name];
    }

    public function applyRowActions(array $item): string
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
                    'href' => sprintf(
                        '?page=%s&action=%s&type=shortcode&id=%s',
                        AdminPage::SLUG,
                        'delete',
                        $item['id'],
                    ),
                ]
            ),
        ];

        //Return the title contents
        return vsprintf('%s %s', [esc_html__($item['name']), $this->row_actions($actions)]);
    }

    public function get_columns(): array
    {
        return [
            //'id'   => 'Identifier',
            'name' => 'Name',
        ];

    }

    public function renderNewShortcodeButton(): string
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

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->manager->loadData();
        $data = $this->manager->listItems();
        $dataAsArray = [];

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
