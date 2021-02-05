<?php

namespace Smartling\WP\Table;

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Tuner\MediaAttachmentRulesManager;

class MediaAttachmentTableWidget extends \WP_List_Table
{
    private $manager;

    private $_settings = [
        'singular' => 'media',
        'plural' => 'media',
        'ajax' => false,
    ];

    public function __construct(MediaAttachmentRulesManager $manager)
    {
        parent::__construct($this->_settings);
        $this->manager = $manager;
    }

    /**
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    /**
     * @param string $item
     * @param string $id
     *
     * @return string
     */
    public function applyRowActions($item, $id)
    {
        $actions = [
            'edit'   => HtmlTagGeneratorHelper::tag(
                'a',
                __('Edit'),
                [
                    'href' => "?page=smartling_customization_tuning_media_form&action=edit&id={$id}",
                ]
            ),
            'delete' => HtmlTagGeneratorHelper::tag(
                'a',
                __('Delete'),
                [
                    'href' => "?page=smartling_customization_tuning&action=delete&type=media&id={$id}"
                ]
            ),
        ];

        return implode(' ', [esc_html__($item), $this->row_actions($actions)]);
    }

    public function get_columns()
    {
        return [
            'block' => 'Gutenberg Block Name',
            'path' => 'JSON Path',
        ];

    }

    public function renderNewButton()
    {
        $options = [
            'id'    => $this->buildHtmlTagName('createNew'),
            'name'  => '',
            'class' => 'button action',
            'type'  => 'submit',
            'value' => __('Add Media Rule'),
        ];

        return HtmlTagGeneratorHelper::tag('input', '', $options);
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->manager->loadData();
        $data = [];

        foreach ($this->manager->getPreconfiguredRules() as $rule) {
            $data[] = [
                'id' => null,
                'block' => HtmlTagGeneratorHelper::tag('span', $rule['block'], [
                    'class' => 'nonmanaged',
                    'title' => 'That path is automatically discovered and processed by smartling-connector',
                ]),
                'path' => $rule['path'],
            ];
        }

        foreach ($this->manager->listItems() as $id => $element) {
            $row = $element;
            $row['id'] = $id;
            $row['block'] = $this->applyRowActions($row['block'], $id);
            $data[] = $row;
        }

        $this->items = $data;
    }
}
