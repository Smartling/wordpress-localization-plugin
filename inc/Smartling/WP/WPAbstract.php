<?php

namespace Smartling\WP;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingIOException;
use Smartling\Helpers\Cache;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\StringHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Psr\Log\LoggerInterface;

class WPAbstract
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PluginInfo
     */
    private $pluginInfo;

    protected LocalizationPluginProxyInterface $localizationPluginProxy;
    protected SettingsManager $settingsManager;
    protected SiteHelper $siteHelper;

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
     * @var mixed
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

    public function __construct(
        protected ApiWrapperInterface $api,
        LocalizationPluginProxyInterface $connector,
        PluginInfo $pluginInfo,
        SettingsManager $settingsManager,
        SiteHelper $siteHelper,
        SubmissionManager $manager,
        Cache $cache
    )
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
        $this->localizationPluginProxy = $connector;
        $this->pluginInfo = $pluginInfo;
        $this->settingsManager = $settingsManager;
        $this->siteHelper = $siteHelper;
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
     * @return ConfigurationProfileEntity[]
     */
    public function getProfiles(): array
    {
        return $this->settingsManager->findEntityByMainLocale($this->siteHelper->getCurrentBlogId());
    }

    /**
     * @param mixed $data
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
            throw new SmartlingIOException(vsprintf('Requested view file (%s) not found.', [$filename]));
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

    public static function bulkSubmitCloneButton()
    {
        return HtmlTagGeneratorHelper::tag(
            'input',
            '',
            [
                'type'  => 'submit',
                'value' => 'Clone',
                'class' => 'button button-primary',
                'id'    => 'clone-locally',
                'name'  => 'submit',
            ]);
    }

    public static function submitBlock()
    {
        $downloadButton = HtmlTagGeneratorHelper::tag(
            'input', '', [
            'type'  => 'button',
            'value' => 'Download',
            'title' => __('Add to Download queue and trigger download process'),
            'class' => 'button button-primary',
            'id'    => 'smartling-download',
            'name'  => 'sub',
        ]);

        $contents = $downloadButton;

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
     * @param string $editLink
     * @param array  $extraAttributes
     * @param null   $submissionId
     * @return string
     */

    public static function localeSelectionCheckboxBlock($namePrefix, $blog_id, $blog_name, $checked = false, $enabled = true, $editLink = '', array $extraAttributes = [], $submissionId = null): string
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
            'name'         => vsprintf('%s[locales][%s][enabled]', [$namePrefix, $blog_id]),
            'class'        => "mcheck $namePrefix",
            'type'         => 'checkbox',
            'data-blog-id' => $blog_id,
        ];

        if (true === $checked) {
            $checkboxAttributes['checked'] = 'checked';
        }

        if (false === $enabled) {
            $checkboxAttributes = array_merge($checkboxAttributes, [
                'disabled' => 'disabled',
                'title'    => 'Content cannot be downloaded',
                'class'    => 'nomcheck',
            ]);
        }

        if (null !== $submissionId) {
            $checkboxAttributes = array_merge($checkboxAttributes, [
                'data-submission-id' => $submissionId
            ]);
        }

        $checkboxAttributes = array_merge($checkboxAttributes, $extraAttributes);

        $parts[] = HtmlTagGeneratorHelper::tag('input', '', $checkboxAttributes);

        if (!StringHelper::isNullOrEmpty($editLink)) {
            $parts[] = HtmlTagGeneratorHelper::tag(
                'a',
                StringHelper::safeStringShrink($blog_name, 22),
                [
                    'title' => __(vsprintf('%s. Click to open translation', [$blog_name])),
                    'href'  => $editLink, 'target' => '_blank',
                    'class' => 'link-blog-name-limited'
                ]
            );
            $container = HtmlTagGeneratorHelper::tag('span', implode('', $parts), []);
        } else {
            $parts[] = HtmlTagGeneratorHelper::tag('span', $blog_name, ['title' => $blog_name]);
            $container = HtmlTagGeneratorHelper::tag('label', implode('', $parts), []);
        }

        return $container;
    }

    /**
     * @param string $statusText
     * @param string $statusColor
     * @param int $percentage
     * @param array $statusFlags
     * @param string $lastError
     * @return string
     */
    public static function localeSelectionTranslationStatusBlock(
        $statusText,
        $statusColor,
        $percentage,
        $statusFlags = [],
        $lastError = ''
    )
    {
        $percentageSpanBlock = 100 === $percentage
            ? HtmlTagGeneratorHelper::tag('span', '', [])
            : HtmlTagGeneratorHelper::tag('span', vsprintf('%s%%', [$percentage]), [ 'class' => 'percent-completed']);

        $flagBlockParts = [];

        if (is_array($statusFlags) && 0 < count($statusFlags)) {
            foreach ($statusFlags as $k => $v) {
                $flagBlockParts[] = HtmlTagGeneratorHelper::tag(
                    'span',
                    '',
                    [
                        'class' => vsprintf('status-flag-%s %s', [$k, $v]),
                        'title' => ucfirst($k),
                    ]
                );
            }
        }

        return HtmlTagGeneratorHelper::tag(
            'span',
            vsprintf('%s%s', [implode('', $flagBlockParts), $percentageSpanBlock]),
            [
                'title' => $statusText . ($statusText === SubmissionEntity::SUBMISSION_STATUS_FAILED ? ": $lastError" : ''),
                'class' => vsprintf('widget-btn %s', [$statusColor]),
            ]
        );
    }

    public static function checkUncheckBlock(string $widgetName): string
    {
        $className = 'mcheck';
        if ($widgetName !== '') {
            $className .= ".$widgetName";
        }
        $output = '';

        $check = HtmlTagGeneratorHelper::tag('a', __('Check All'), [
            'href' => '#',
            'onclick' => "bulkCheck('$className','check');return false;",
        ]);

        $unCheck = HtmlTagGeneratorHelper::tag('a', __('Uncheck All'), [
            'href' => '#',
            'onclick' => "bulkCheck('$className','uncheck');return false;",
        ]);

        return $output . HtmlTagGeneratorHelper::tag('span', vsprintf('%s / %s', [$check, $unCheck]));
    }

    protected function getQueryParam($paramName, $defaultValue = null)
    {
        $result = $defaultValue;

        if (array_key_exists($paramName, $_GET)) {
            $result = $_GET[$paramName];
        }

        return $result;
    }
}
