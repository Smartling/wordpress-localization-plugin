<?php

namespace Smartling\Helpers;

use DOMDocument;
use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\WP\WPHookInterface;

/**
 * Class RelativeLinkedAttachmentCoreHelper
 *
 * @package inc\Smartling\Helpers
 */
class RelativeLinkedAttachmentCoreHelper implements WPHookInterface {

	/**
	 * RegEx to catch images from the string
	 */
	const PATTERN_IMAGE_GENERAL = '<img[^>]+>';

	/**
	 * @var LoggerInterface
	 */
	private $logger = null;

	/**
	 * @var SmartlingCore
	 */
	private $ep = null;

	/**
	 * @var AfterDeserializeContentEventParameters
	 */
	private $params = null;

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}

	/**
	 * @param LoggerInterface $logger
	 */
	private function setLogger ( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return SmartlingCore
	 */
	public function getCore () {
		return $this->ep;
	}

	/**
	 * @param SmartlingCore $ep
	 */
	private function setCore ( SmartlingCore $ep ) {
		$this->ep = $ep;
	}

	/**
	 * @return AfterDeserializeContentEventParameters
	 */
	public function getParams () {
		return $this->params;
	}

	/**
	 * @param AfterDeserializeContentEventParameters $params
	 */
	private function setParams ( $params ) {
		$this->params = $params;
	}


	public function __construct ( LoggerInterface $logger, SmartlingCore $ep ) {
		$this->setLogger( $logger );
		$this->setCore( $ep );
	}

	/**
	 * @inheritdoc
	 */
	public function register () {
		add_action( XmlEncoder::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT, [ $this, 'processor' ] );
	}

	/**
	 * A XmlEncoder::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT event handler
	 *
	 * @param AfterDeserializeContentEventParameters $params
	 */
	public function processor ( AfterDeserializeContentEventParameters $params ) {
		$this->setParams( $params );

		$fields = &$params->getTranslatedFields();

		foreach ( $fields as $name => & $value ) {
			$this->processString( $value );
		}
	}

	/**
	 * Recursively processes all found strings
	 *
	 * @param $stringValue
	 */
	private function processString ( & $stringValue ) {
		$replacer = new PairReplacerHelper();

		if ( is_array( $stringValue ) ) {
			foreach ( $stringValue as $item => & $value ) {
				$this->processString( $value );
			}
		} else {
			$matches = [ ];
			if ( 0 < preg_match_all( StringHelper::buildPattern( self::PATTERN_IMAGE_GENERAL ), $stringValue, $matches ) ) {
				foreach ( $matches[0] as $match ) {
					$path = $this->getSourcePathFromImgTag( $match );
					if ( ( false !== $path ) && ( $this->testIfUrlIsRelative( $path ) ) ) {
						$attachmentId = $this->getAttachmentId( $path );
						if ( false !== $attachmentId ) {
							$attachmentSubmission = $this->getCore()->sendAttachmentForTranslation(
								$this->getParams()->getSubmission()->getSourceBlogId(),
								$this->getParams()->getSubmission()->getTargetBlogId(),
								$attachmentId
							);
							$replacer->addReplacementPair(
								$path,
								$this->getCore()->getAttachmentRelativePathBySubmission( $attachmentSubmission )
							);
						}
					}
				}
			}
		}
		$stringValue = $replacer->processString( $stringValue );
	}

	/**
	 * Extracts src attribute from <img /> tag if possible, otherwise returns false.
	 *
	 * @param $imgTagString
	 *
	 * @return bool
	 */
	private function getSourcePathFromImgTag ( $imgTagString ) {
		$dom = new DOMDocument();
		$dom->loadHTML( $imgTagString );
		$images = $dom->getElementsByTagName( 'img' );

		if ( 1 === $images->length ) {
			/** @var \DOMNode $node */
			$node = $images->item( 0 );
			if ( $node->hasAttribute( 'src' ) ) {
				$src = $node->getAttribute( 'src' );

				return $src;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Checks if given URL is relative
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	private function testIfUrlIsRelative ( $url ) {
		$parts = parse_url( $url );

		return $url === $parts['path'];
	}

	/**
	 * @param $relativePath
	 *
	 * @return false|int
	 */
	private function getAttachmentId ( $relativePath ) {

		$a = $this->getCore()->getFullyRelateAttachmentPath( $this->getParams()->getSubmission(), $relativePath );

		$query = vsprintf(
			'SELECT `post_id` as `id` FROM `%s` WHERE `meta_key` = \'_wp_attached_file\' AND `meta_value`=\'%s\' LIMIT 1;',
			[
				RawDbQueryHelper::getTableName( 'postmeta' ),
				$a,
			]
		);

		$data = RawDbQueryHelper::query( $query );

		$result = false;

		if ( is_array( $data ) && 1 === count( $data ) ) {
			$resultRow = reset( $data );

			if ( is_array( $resultRow ) && array_key_exists( 'id', $resultRow ) ) {
				$result = (int) $resultRow['id'];
			}
		}

		return $result;
	}
}