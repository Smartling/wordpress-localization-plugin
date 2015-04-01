<?php

namespace Smartling\WP\Controller;

use SebastianBergmann\Exporter\Exception;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class PostWidgetController
 *
 * @package Smartling\WP\Controller
 */
class PostWidgetController extends WPAbstract implements WPHookInterface {

	const WIDGET_NAME = 'smartling_connector_widget';

	const WIDGET_DATA_NAME = 'smartling_post_based_widget';

	const CONNECTOR_NONCE = 'smartling_connector_nonce';

	protected $servedContentType = WordpressContentTypeHelper::CONTENT_TYPE_POST;

	protected $needSave = 'Need to save the post';

	protected $noOriginalFound = 'No original post found';

	/**
	 * @inheritdoc
	 */
	public function register () {
		if ( ! DiagnosticsHelper::isBlocked() ) {
			add_action( 'add_meta_boxes', array ( $this, 'box' ) );
			add_action( 'save_post', array ( $this, 'save' ) );
		}
	}

	/**
	 * add_meta_boxes hook
	 *
	 * @param string $post_type
	 */
	public function box ( $post_type ) {
		$post_types = array ( $this->servedContentType );
		if ( in_array( $post_type, $post_types ) ) {
			add_meta_box(
				self::WIDGET_NAME,
				__( 'Smartling Post Widget' ),
				array ( $this, 'preView' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * @param $post
	 */
	public function preView ( $post ) {
		wp_nonce_field( self::WIDGET_NAME, self::CONNECTOR_NONCE );

		if ( $post->post_content && $post->post_title ) {

			try {
				$originalId = $this->getEntityHelper()->getOriginalContentId( $post->ID );

				$submissions = $this->getManager()->find( array (
					'source_id'  => $originalId,
					'content_type' => $this->servedContentType,
				) );

				$this->view( array (
						'submissions' => $submissions,
						'post'        => $post
					)
				);
			} catch ( SmartlingDbException $e ) {
				$message = 'Failed to search for the original post. No source post found for blog %s, post %s. Hiding widget';
				$this->getLogger()->warning(
					vsprintf( $message, array (
						$this->getEntityHelper()->getSiteHelper()->getCurrentBlogId(),
						$post->ID
					) )
				);
				echo '<p>' . __( $this->noOriginalFound ) . '</p>';
			}
		} else {
			echo '<p>' . __( $this->needSave ) . '</p>';
		}
	}

	/**
	 * @param $post_id
	 *
	 * @return bool
	 */
	private function runValidation ( $post_id ) {
		if ( ! array_key_exists( self::CONNECTOR_NONCE, $_POST ) ) {
			return false;
		}

		$nonce = $_POST[ self::CONNECTOR_NONCE ];

		if ( ! wp_verify_nonce( $nonce, self::WIDGET_NAME ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && true === DOING_AUTOSAVE ) {
			return false;
		}

		if ( $this->servedContentType !== $_POST['post_type'] ) {
			return false;
		}

		return $this->isAllowedToSave( $post_id );
	}

	/**
	 * @param $post_id
	 *
	 * @return bool
	 */
	protected function isAllowedToSave ( $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * @param $post_id
	 *
	 * @return mixed
	 */
	public function save ( $post_id ) {

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		remove_action( 'save_post', array ( $this, 'save' ) );

		if ( false === $this->runValidation( $post_id ) ) {
			return $post_id;
		}

		if (!array_key_exists(self::WIDGET_DATA_NAME, $_POST))
		{
			return;
		}

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
				switch ( $_POST['sub'] ) {
					case __( 'Send to Smartling' ):

						$sourceBlog = $this->getPluginInfo()->getSettingsManager()->getLocales()->getDefaultBlog();
						$originalId = (int) $this->getEntityHelper()->getOriginalContentId( $post_id );

						foreach ( $locales as $blogId => $blogName ) {

							$result = $core->sendForTranslation(
								$this->servedContentType,
								$sourceBlog,
								$originalId,
								(int) $blogId,
								$this->getEntityHelper()->getTarget( $post_id, $blogId )
							);

						}
						break;
					case __( 'Download' ):
						$originalId = $this->getEntityHelper()->getOriginalContentId( $post_id );

						$submissions = $this->getManager()->find(
							array (
								'source_id'  => $originalId,
								'content_type' => $this->servedContentType
							)
						);

						foreach ( $submissions as $submission ) {
							$core->downloadTranslationBySubmission( $submission );
						}

						break;
				}
			}
		}
		add_action( 'save_post', array ( $this, 'save' ) );
	}
}