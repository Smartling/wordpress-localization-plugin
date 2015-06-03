<?php

namespace Smartling\WP;

use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\Cache;
use Smartling\Helpers\EntityHelper;
use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionManager;

abstract class WPAbstract {

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

	private $viewData;

	/**
	 * @return mixed
	 */
	public function getViewData () {
		return $this->viewData;
	}

	/**
	 * @param mixed $viewData
	 */
	public function setViewData ( $viewData ) {
		$this->viewData = $viewData;
	}



	/**
	 * @return string
	 */
	public function getWidgetHeader () {
		return $this->widgetHeader;
	}

	/**
	 * @param string $widgetHeader
	 */
	public function setWidgetHeader ( $widgetHeader ) {
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
	public function __construct (
		LoggerInterface $logger,
		LocalizationPluginProxyInterface $connector,
		PluginInfo $pluginInfo,
		EntityHelper $entityHelper,
		SubmissionManager $manager,
		Cache $cache
	) {
		$this->logger       = $logger;
		$this->connector    = $connector;
		$this->pluginInfo   = $pluginInfo;
		$this->entityHelper = $entityHelper;
		$this->manager      = $manager;
		$this->cache        = $cache;
	}

	/**
	 * @return Cache
	 */
	public function getCache () {
		return $this->cache;
	}

	/**
	 * @return SubmissionManager
	 */
	public function getManager () {
		return $this->manager;
	}

	/**
	 * @return EntityHelper
	 */
	public function getEntityHelper () {
		return $this->entityHelper;
	}

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}

	/**
	 * @return PluginInfo
	 */
	public function getPluginInfo () {
		return $this->pluginInfo;
	}

	/**
	 * @return LocalizationPluginProxyInterface
	 */
	public function getConnector () {
		return $this->connector;
	}

	/**
	 * @param null $data
	 */
	public function view ( $data = null ) {
		$this->setViewData($data);
		$class = get_called_class();
		$class = str_replace( 'Smartling\\WP\\Controller\\', '', $class );

		$class = str_replace( 'Controller', '', $class );

		$this->renderViewScript( $class . '.php' );
	}

	public function renderViewScript ( $script ) {
		require_once plugin_dir_path( __FILE__ ) . 'View/' . $script;
	}

	public static function sendButton ( $id = 'submit', $name = 'submit' ) {
		return HtmlTagGeneratorHelper::tag( 'input', '', array (
			'type'  => 'submit',
			'value' => __( 'Send to Smartling' ),
			'class' => 'button button-primary',
			'id'    => $id,
			'name'  => $name,
		) );
	}

	public static function submitBlock () {
		$sendButton = self::sendButton( '', 'sub' );

		$downloadButton = HtmlTagGeneratorHelper::tag( 'input', '', array (
			'type'  => 'submit',
			'value' => __( 'Download' ),
			'class' => 'button button-primary',
			'id'    => '',
			'name'  => 'sub',
		) );

		$container = HtmlTagGeneratorHelper::tag( 'div', $sendButton . '&nbsp;' . $downloadButton,
			array ( 'class' => 'bottom' ) );

		return $container;
	}

	/**
	 * @param $submissionId
	 *
	 * @return string
	 */
	public static function inputHidden ( $submissionId ) {
		$hiddenId = HtmlTagGeneratorHelper::tag( 'input', '', array (
			'type'  => 'hidden',
			'value' => $submissionId,
			'class' => 'submission-id',
			'id'    => 'submission-id-' . $submissionId
		) );

		return $hiddenId;
	}

	public static function localeSelectionCheckboxBlock ( $namePrefix, $blog_id, $blog_name, $enabled = false ) {
		$parts = array ();

		$parts[] = HtmlTagGeneratorHelper::tag( 'input', '', array (
			'type'  => 'hidden',
			'name'  => vsprintf( '%s[locales][%s][blog]', array ( $namePrefix, $blog_id ) ),
			'value' => $blog_id
		) );


		$parts[] = HtmlTagGeneratorHelper::tag( 'input', '', array (
			'type'  => 'hidden',
			'name'  => vsprintf( '%s[locales][%s][locale]', array ( $namePrefix, $blog_id ) ),
			'value' => $blog_name
		) );

		$checkboxAttributes = array (
			'name'  => vsprintf( '%s[locales][%s][enabled]', array ( $namePrefix, $blog_id ) ),
			'class' => 'mcheck',
			'type'  => 'checkbox'
		);

		if ( true === $enabled ) {
			$checkboxAttributes['checked'] = 'checked';
		}

		$parts[] = HtmlTagGeneratorHelper::tag( 'input', '', $checkboxAttributes );

		$parts[] = HtmlTagGeneratorHelper::tag( 'span', $blog_name, array () );


		$container = HtmlTagGeneratorHelper::tag( 'label', implode( '', $parts ), array () );

		return $container;
	}

	public static function localeSelectionTranslationStatusBlock ( $statusText, $statusColor, $percentage ) {
		return HtmlTagGeneratorHelper::tag(
			'span',
			HtmlTagGeneratorHelper::tag(
				'span',
				vsprintf( '%s%%', array ( $percentage ) ),
				array ()
			),
			array (
				'title' => $statusText,
				'class' => vsprintf( 'widget-btn %s', array ( $statusColor ) )
			)
		);
	}

	public static function checkUncheckBlock () {
		$output = "";

		$check = HtmlTagGeneratorHelper::tag( 'a', __( 'Check All' ), array (
			'href'    => '#',
			'onclick' => 'bulkCheck(\'mcheck\',\'check\');return false;'
		) );

		$unCheck = HtmlTagGeneratorHelper::tag( 'a', __( 'Uncheck All' ), array (
			'href'    => '#',
			'onclick' => 'bulkCheck(\'mcheck\',\'uncheck\');return false;'
		) );

		return $output . HtmlTagGeneratorHelper::tag( 'span', vsprintf( '%s / %s', array ( $check, $unCheck ) ) );
	}

	public static function settingsPageTsargetLocaleCheckbox (
		ConfigurationProfileEntity $profile,
		$displayName,
		$blogId,
		$smartlingName = '',
		$enabled = false
	) {
		$parts = array ();

		$checkboxProperties = array (
			'type'  => 'checkbox',
			'class' => 'mcheck',
			'name'  => vsprintf( 'smartling_settings[targetLocales][%s][enabled]', array ( $blogId ) )
		);

		if ( true === $enabled ) {
			$checkboxProperties['checked'] = 'checked';
		}

		$parts[] = HtmlTagGeneratorHelper::tag( 'input', '', $checkboxProperties );

		$parts[] = HtmlTagGeneratorHelper::tag( 'span', htmlspecialchars( $displayName ), array () );

		$parts = array (
			HtmlTagGeneratorHelper::tag( 'label', implode( '', $parts ), array ( 'class' => 'radio-label' ) )
		);

		/**
		 * @var SmartlingCore $ep
		 */
		$ep = Bootstrap::getContainer()->get( 'entrypoint' );

		$locales = $ep->getProjectLocales( $profile );

		if ( 0 === count( $locales ) ) {
			$sLocale = HtmlTagGeneratorHelper::tag(
				'input',
				'',
				array (
					'name' => vsprintf( 'smartling_settings[targetLocales][%s][target]', array ( $blogId ) ),
					'type' => 'text'
				) );
		} else {
			$sLocale = HtmlTagGeneratorHelper::tag(
				'select',
				HtmlTagGeneratorHelper::renderSelectOptions(
					$smartlingName,
					$locales
				),
				array (
					'name' => vsprintf( 'smartling_settings[targetLocales][%s][target]', array ( $blogId ) )
				) );
		}

		$parts   = array (
			HtmlTagGeneratorHelper::tag( 'td', implode( '', $parts ), array ( 'style' => 'display:table-cell;' ) )
		);
		$parts[] = HtmlTagGeneratorHelper::tag( 'td', $sLocale, array ( 'style' => 'display:table-cell;' ) );


		return implode( '', $parts );
	}
}