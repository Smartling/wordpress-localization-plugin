<?php

namespace Smartling\WP\Controller;

use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingNotSupportedContentException;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class TaxonomyWidgetController
 *
 * @package Smartling\WP\Controller
 */
class TaxonomyWidgetController extends WPAbstract implements WPHookInterface {

	const WIDGET_DATA_NAME = 'smartling_taxonomy_widget';

	/**
	 * @inheritdoc
	 */
	public function register ( array $diagnosticData = array () ) {
		if ( false === $diagnosticData['selfBlock'] ) {
			add_action( 'admin_init', array ( $this, 'init' ) );
		}

	}

	/**
	 * block initialization
	 */
	public function init () {
		$taxonomies = get_taxonomies( array (
			'public'   => true,
			'_builtin' => true
		), 'names', 'and' );

		if ( $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				add_action( "{$taxonomy}_edit_form", array ( $this, 'preView' ), 100, 1 );
				add_action( "edited_{$taxonomy}", array ( $this, 'save' ), 10, 1 );
			}
		}
	}

	/**
	 * @param string $wordpressType
	 *
	 * @return string
	 * @throws SmartlingNotSupportedContentException
	 */
	private function getInternalType ( $wordpressType ) {
		$reverseMap = WordpressContentTypeHelper::getReverseMap();

		if ( array_key_exists( $wordpressType, $reverseMap ) ) {
			return $reverseMap[ $wordpressType ];
		} else {
			$message = vsprintf( 'Tried to translate non supported taxonomy:%s', array ( $wordpressType ) );

			$this->getLogger()->warning( $message );

			throw new SmartlingNotSupportedContentException( $message );
		}
	}

	/**
	 * @param $term
	 */
	public function preView ( $term ) {

		$taxonomyType = $term->taxonomy;

		try {
			if ( current_user_can( 'publish_posts' ) && $this->getInternalType( $taxonomyType ) ) {

				$submissions = $this->getManager()->find( array (
					'sourceGUID'  => $term->term_id,
					'contentType' => $taxonomyType,
				) );

				$this->view( array (
						'submissions' => $submissions,
						'term'        => $term
					)
				);
			}
		} catch ( SmartlingNotSupportedContentException $e ) {
			// do not display if not supported yet
		}
	}

	function save ( $term_id ) {
		if ( ! array_key_exists( 'taxonomy', $_POST ) ) {
			return;
		}
		$termType = $_POST['taxonomy'];
		//reset(TaxonomyEntityAbstract::detectTermTaxonomyByTermId($term_id));

		if ( ! in_array( $termType, WordpressContentTypeHelper::getSupportedTaxonomyTypes() ) ) {
			return;
		}

		remove_action( "edited_{$termType}", array ( $this, 'save' ) );

		$data = $_POST[ self::WIDGET_DATA_NAME ];

		$locales = array ();

		if ( null !== $data && array_key_exists( 'locales', $data ) ) {

			foreach ( $data['locales'] as $blogId => $blogName ) {
				if ( array_key_exists( 'enabled', $blogName ) && 'on' === $blogName['enabled'] ) {
					$locales[ $blogId ] = $blogName['locale'];
				}
			}

			/**
			 * @var SmartlingCore $core
			 */
			$core = Bootstrap::getContainer()->get( 'entrypoint' );

			if ( count( $locales ) > 0 ) {
				switch ( $_POST['submit'] ) {
					case __( 'Send to Smartling' ):

						$sourceBlog = $this->getPluginInfo()->getSettingsManager()->getLocales()->getDefaultBlog();
						$originalId = (int) $this->getEntityHelper()->getOriginalContentId( $term_id, $termType );

						foreach ( $locales as $blogId => $blogName ) {

							$result = $core->sendForTranslation(
								$termType,
								$sourceBlog,
								$originalId,
								(int) $blogId,
								$this->getEntityHelper()->getTarget( $term_id, $blogId )
							);


						}
						break;
					case __( 'Download' ):
						$originalId = $this->getEntityHelper()->getOriginalContentId( $term_id, $termType );

						$submissions = $this->getManager()->find(
							array (
								'sourceGUID'  => $originalId,
								'contentType' => $termType
							)
						);

						foreach ( $submissions as $submission ) {
							$core->downloadTranslationBySubmission( $submission );
						}

						break;
				}
			}
		}
		add_action( "edited_{$termType}", array ( $this, 'save' ) );
	}
}