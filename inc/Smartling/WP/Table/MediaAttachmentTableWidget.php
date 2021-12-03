<?php

namespace Smartling\WP\Table;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\GutenbergReplacementRule;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Tuner\MediaAttachmentRulesManager;

class MediaAttachmentTableWidget extends \WP_List_Table
{
    private MediaAttachmentRulesManager $manager;
    private ReplacerFactory $factory;

    private array $_settings = [
        'singular' => 'media',
        'plural' => 'media',
        'ajax' => false,
    ];

    public function __construct(MediaAttachmentRulesManager $manager, ReplacerFactory $replacerFactory)
    {
        parent::__construct($this->_settings);
        $this->factory = $replacerFactory;
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

    public function applyRowActions(string $item, string $id): string
    {
        $actions = [
            'edit'   => HtmlTagGeneratorHelper::tag(
                'a',
                __('Edit'),
                [
                    'href' => "?page=smartling_customization_tuning_media_form&action=edit&id=$id",
                ]
            ),
            'delete' => HtmlTagGeneratorHelper::tag(
                'a',
                __('Delete'),
                [
                    'href' => "?page=smartling_customization_tuning&action=delete&type=media&id=$id"
                ]
            ),
        ];

        return implode(' ', [esc_html__($item), $this->row_actions($actions)]);
    }

    public function get_columns(): array
    {
        return [
            'block' => 'Gutenberg Block Name regex (delimiter is #)',
            'path' => 'JSON Path regex (delimiter is #)',
            'replacerId' => 'Rule',
        ];

    }

    public function renderNewButton(): string
    {
        $options = [
            'class' => 'button action',
            'type' => 'submit',
            'value' => __('Add block rule'),
        ];

        return HtmlTagGeneratorHelper::tag('input', '', $options);
    }

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->manager->loadData();
        $data = [];

        foreach ($this->manager->getPreconfiguredRules() as $rule) {
            $data[] = $this->toArray($rule);
        }

        foreach ($this->manager->listItems() as $id => $element) {
            $data[] = $this->toArray($element, $id, true);
        }

        $this->items = $data;
    }

    private function toArray(GutenbergReplacementRule $rule, ?string $id = null, bool $managed = false): array
    {
        try {
            $replacerId = $this->factory->getReplacer($rule->getReplacerId())->getLabel();
        } catch (EntityNotFoundException $e) {
            $replacerId = "<b>Invalid</b>: {$rule->getReplacerId()}";
        }
        return [
            'id' => $id,
            'block' => $managed ?
                $this->applyRowActions($rule->getBlockType(), $id) :
                HtmlTagGeneratorHelper::tag('span', $rule->getBlockType(), [
                    'class' => 'nonmanaged',
                    'title' => 'That path is automatically discovered and processed by smartling-connector',
                ]),
            'path' => htmlspecialchars($rule->getPropertyPath()),
            'replacerId' => $replacerId,
        ];
    }
}
