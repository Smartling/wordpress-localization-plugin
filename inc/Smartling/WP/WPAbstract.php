<?php

namespace Smartling\WP;

use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\Cache;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\StringHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionManager;

abstract class WPAbstract
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PluginInfo
     */
    private $pluginInfo;

    /**
     * @var LocalizationPluginProxyInterface
     */
    private $connector;

    /**
     * @var EntityHelper
     */
    private $entityHelper;

    /**
     * @var SubmissionManager
     */
    private $manager;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var string
     */
    private $widgetHeader = '';

    /**
     * @var array
     */
    private $viewData;

    /**
     * @return mixed
     */
    public function getViewData()
    {
        return $this->viewData;
    }

    /**
     * @param mixed $viewData
     */
    public function setViewData($viewData)
    {
        $this->viewData = $viewData;
    }


    /**
     * @return string
     */
    public function getWidgetHeader()
    {
        return $this->widgetHeader;
    }

    /**
     * @param string $widgetHeader
     */
    public function setWidgetHeader($widgetHeader)
    {
        $this->widgetHeader = $widgetHeader;
    }

    /**
     * Constructor
     *
     * @param LoggerInterface                  $logger
     * @param LocalizationPluginProxyInterface $connector
     * @param PluginInfo                       $pluginInfo
     * @param EntityHelper                     $entityHelper
     * @param SubmissionManager                $manager
     * @param Cache                            $cache
     */
    public function __construct(
        LoggerInterface $logger,
        LocalizationPluginProxyInterface $connector,
        PluginInfo $pluginInfo,
        EntityHelper $entityHelper,
        SubmissionManager $manager,
        Cache $cache
    )
    {
        $this->logger = $logger;
        $this->connector = $connector;
        $this->pluginInfo = $pluginInfo;
        $this->entityHelper = $entityHelper;
        $this->manager = $manager;
        $this->cache = $cache;
    }

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @return SubmissionManager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * @return EntityHelper
     */
    public function getEntityHelper()
    {
        return $this->entityHelper;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return PluginInfo
     */
    public function getPluginInfo()
    {
        return $this->pluginInfo;
    }

    /**
     * @return LocalizationPluginProxyInterface
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * @param null $data
     */
    public function view($data = null)
    {
        $this->setViewData($data);
        $class = get_called_class();
        $class = str_replace('Smartling\\WP\\Controller\\', '', $class);

        $class = str_replace('Controller', '', $class);

        $this->renderViewScript($class . '.php');
    }

    public function renderViewScript($script)
    {
        $filename = plugin_dir_path(__FILE__) . 'View/' . $script;

        if (!file_exists($filename) || !is_file($filename) || !is_readable($filename)) {
            throw new \Exception(vsprintf('Requested view file (%s) not found.', [$filename]));
        } else {
            /** @noinspection PhpIncludeInspection */
            require_once $filename;
        }
    }

    public static function bulkSubmitSendButton($id = 'submit', $name = 'submit', $text = 'Add to Upload Queue', $title = 'Add selected submissions to Upload queue')
    {
        return HtmlTagGeneratorHelper::tag(
            'button',
            HtmlTagGeneratorHelper::tag('span', __($text), []),
            [
                'type'  => 'submit',
                'value' => $name,
                'title' => __($title),
                'class' => 'button button-primary',
                'id'    => $id,
                'name'  => $name,
            ]);
    }

    public static function sendButton($id = 'submit', $name = 'submit')
    {
        return HtmlTagGeneratorHelper::tag(
            'input', '', [
            'type'  => 'submit',
            'value' => 'Upload',
            'title' => __('Add to Upload queue and trigger upload process'),
            'class' => 'button button-primary',
            //'id'    => $id,
            'name'  => $name,
        ]);
    }

    public static function submitBlock()
    {
        $sendButton = self::sendButton('', 'sub');

        $downloadButton = HtmlTagGeneratorHelper::tag(
            'button',
            HtmlTagGeneratorHelper::tag('span', __('Download'), []),
            [
                'type'  => 'submit',
                'value' => 'download',
                'title' => __('Add to Download queue and trigger download process'),
                'class' => 'button button-primary',
                'id'    => '',
                'name'  => 'sub',
            ]);

        $downloadButton = HtmlTagGeneratorHelper::tag(
            'input', '', [
            'type'  => 'submit',
            'value' => 'Download',
            'title' => __('Add to Download queue and trigger upload process'),
            'class' => 'button button-primary',
            //'id'    => $id,
            'name'  => 'sub',
        ]);

        $contents = $sendButton . '&nbsp;' . $downloadButton;

        $container = HtmlTagGeneratorHelper::tag('div', $contents, ['class' => 'bottom']);

        return $container;
    }

    /**
     * @param $submissionId
     *
     * @return string
     */
    public static function inputHidden($submissionId)
    {
        $hiddenId = HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'value' => $submissionId,
            'class' => 'submission-id',
            'id'    => 'submission-id-' . $submissionId,
        ]);

        return $hiddenId;
    }

    /**
     * @param string $namePrefix
     * @param int    $blog_id
     * @param string $blog_name
     * @param bool   $checked
     * @param bool   $enabled
     *
     * @return string
     */
    public static function localeSelectionCheckboxBlock($namePrefix, $blog_id, $blog_name, $checked = false, $enabled = true, $editLink = '')
    {
        $parts = [];

        $parts[] = HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => vsprintf('%s[locales][%s][blog]', [$namePrefix, $blog_id]),
            'value' => $blog_id,
        ]);


        $parts[] = HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => vsprintf('%s[locales][%s][locale]', [$namePrefix, $blog_id]),
            'value' => $blog_name,
        ]);

        $checkboxAttributes = [
            'name'  => vsprintf('%s[locales][%s][enabled]', [$namePrefix, $blog_id]),
            'class' => 'mcheck',
            'type'  => 'checkbox',
        ];

        if (true === $checked) {
            $checkboxAttributes['checked'] = 'checked';
        }

        if (false === $enabled) {
            $checkboxAttributes = array_merge($checkboxAttributes, [
                'disabled' => 'disabled',
                'title'    => 'Content is cloned',
                'class'    => 'nomcheck',
            ]);
        }

        $parts[] = HtmlTagGeneratorHelper::tag('input', '', $checkboxAttributes);

        if (!StringHelper::isNullOrEmpty($editLink)) {
            $parts[] = HtmlTagGeneratorHelper::tag('a', $blog_name, ['title' => __('Open translation'),'href'=>$editLink, 'target'=>'_blank']);
            $container = HtmlTagGeneratorHelper::tag('span', implode('', $parts), []);
        } else {
            $parts[] = HtmlTagGeneratorHelper::tag('span', $blog_name, ['title' => $blog_name]);
            $container = HtmlTagGeneratorHelper::tag('label', implode('', $parts), []);
        }

        return $container;
    }

    public static function localeSelectionTranslationStatusBlock($statusText, $statusColor, $percentage)
    {
        $percentageSpanBlock = 100 === $percentage
            ? HtmlTagGeneratorHelper::tag('span', '', [])
            : HtmlTagGeneratorHelper::tag('span', vsprintf('%s%%', [$percentage]), []);


        return HtmlTagGeneratorHelper::tag(
            'span',
            $percentageSpanBlock,
            [
                'title' => $statusText,
                'class' => vsprintf('widget-btn %s', [$statusColor]),
            ]
        );
    }

    public static function checkUncheckBlock()
    {
        $output = '';

        $check = HtmlTagGeneratorHelper::tag('a', __('Check All'), [
            'href'    => '#',
            'onclick' => 'bulkCheck(\'mcheck\',\'check\');return false;',
        ]);

        $unCheck = HtmlTagGeneratorHelper::tag('a', __('Uncheck All'), [
            'href'    => '#',
            'onclick' => 'bulkCheck(\'mcheck\',\'uncheck\');return false;',
        ]);

        return $output . HtmlTagGeneratorHelper::tag('span', vsprintf('%s / %s', [$check, $unCheck]));
    }

    public static function settingsPageTsargetLocaleCheckbox(
        ConfigurationProfileEntity $profile,
        $displayName,
        $blogId,
        $smartlingName = '',
        $enabled = false
    )
    {
        $parts = [];

        $checkboxProperties = [
            'type'  => 'checkbox',
            'class' => 'mcheck',
            'name'  => vsprintf('smartling_settings[targetLocales][%s][enabled]', [$blogId]),
        ];

        if (true === $enabled) {
            $checkboxProperties['checked'] = 'checked';
        }

        $parts[] = HtmlTagGeneratorHelper::tag('input', '', $checkboxProperties);

        $parts[] = HtmlTagGeneratorHelper::tag('span', htmlspecialchars($displayName), []);

        $parts = [
            HtmlTagGeneratorHelper::tag('label', implode('', $parts), ['class' => 'radio-label']),
        ];

        /**
         * @var SmartlingCore $ep
         */
        $ep = Bootstrap::getContainer()->get('entrypoint');

        $locales = $ep->getProjectLocales($profile);

        if (0 === count($locales)) {
            $sLocale = HtmlTagGeneratorHelper::tag(
                'input',
                '',
                [
                    'name' => vsprintf('smartling_settings[targetLocales][%s][target]', [$blogId]),
                    'type' => 'text',
                ]);
        } else {
            $sLocale = HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions(
                    $smartlingName,
                    $locales
                ),
                [
                    'name' => vsprintf('smartling_settings[targetLocales][%s][target]', [$blogId]),
                ]);
        }

        $parts = [
            HtmlTagGeneratorHelper::tag('td', implode('', $parts), ['style' => 'display:table-cell;']),
        ];
        $parts[] = HtmlTagGeneratorHelper::tag('td', $sLocale, ['style' => 'display:table-cell;']);


        return implode('', $parts);
    }
}
