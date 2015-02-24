<?php

namespace Smartling\WP;

use Smartling\Helpers\Cache;
use Smartling\Helpers\EntityHelper;
use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
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
		$class = get_called_class();
		$class = str_replace( 'Smartling\\WP\\Controller\\', '', $class );

		$class = str_replace( 'Controller', '', $class );

		require_once plugin_dir_path( __FILE__ ) . 'View/' . $class . '.php';
	}

	public static function submitBlock () {
		$sendButton = HtmlTagGeneratorHelper::tag( 'input', '', array (
			'type'  => 'submit',
			'value' => __( 'Send to Smartling' ),
			'class' => 'button button-primary',
			'id'    => 'submit',
			'name'  => 'submit',
		) );

		$downloadButton = HtmlTagGeneratorHelper::tag( 'input', '', array (
			'type'  => 'submit',
			'value' => __( 'Download' ),
			'class' => 'button button-primary',
			'id'    => 'submit',
			'name'  => 'submit',
		) );

		$container = HtmlTagGeneratorHelper::tag( 'div', $sendButton . '&nbsp;' . $downloadButton,
			array ( 'class' => 'bottom' ) );

		return $container;
	}
}