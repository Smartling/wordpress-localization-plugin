<?php

namespace Smartling\WP\Controller;

use SebastianBergmann\Exporter\Exception;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class PostWidgetController
 *
 * @package Smartling\WP\Controller
 */
class PostWidgetController extends WPAbstract implements WPHookInterface {

	const WIDGET_NAME = 'smartling_connector_post_widget';

	const WIDGET_DATA_NAME = 'smartling_post_widget_data';

	const CONNECTOR_NONCE = 'smartling_connector_nonce';

	/**
	 * @inheritdoc
	 */
	public function register () {
		add_action( 'add_meta_boxes', array ( $this, 'box' ) );
		add_action( 'save_post', array ( $this, 'save' ) );
	}

	/**
	 * add_meta_boxes hook
	 * @param string $post_type
	 */
	public function box ( $post_type ) {
		$post_types = array ( WordpressContentTypeHelper::CONTENT_TYPE_POST );
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

			$originalId = $this->getEntityHelper()->getOriginalContentId($post->ID);

			$submissions = $this->getManager()->find(array(
				'sourceGUID' => $originalId,
				'contentType' => WordpressContentTypeHelper::CONTENT_TYPE_POST
			));

			$this->view( array (
					'submissions' => $submissions,
					'post'        => $post
				)
			);
		} else {
			echo __( '<p>Need to save the post</p>' );
		}
	}

	/**
	 * @param $post_id
	 *
	 * @return mixed
	 */
	public function save ( $post_id ) {
		remove_action( 'save_post', array ( $this, 'save' ) );
		if ( ! isset( $_POST['smartling_connector_nonce'] ) ) {
			return $post_id;
		}

		$nonce = $_POST['smartling_connector_nonce'];

		if ( ! wp_verify_nonce( $nonce, self::WIDGET_NAME ) ) {
			return $post_id;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}
		}

		$data    = $_POST['smartling_post_widget'];
		$locales = array ();

		if ( null !== $data && array_key_exists( 'locales', $data ) ) {

			foreach ( $data['locales'] as $key => $locale ) {
				if ( array_key_exists( 'enabled', $locale ) && 'on' === $locale['enabled'] ) {
					$locales[ $key ] = $locale['locale'];
				}
			}

			if ( count( $locales ) > 0 ) {
				$postEntity = new PostEntity( $this->getLogger() );

				$originalId  = $this->getEntityHelper()->getOriginal( $post_id );
				$post        = $postEntity->get( $originalId );
				$manager     = $this->getManager();
				$submissions = $manager->getEntityBySourceGuid( $originalId );

				switch ( $_POST['submit'] ) {
					case 'Send to Smartling':
						foreach ( $locales as $key => $locale ) {
							/**
							 * @var SmartlingCore $core
							 */
							$core = Bootstrap::getContainer()->get( 'entrypoint' );

							$result = $core->sendForTranslation(
								WordpressContentTypeHelper::CONTENT_TYPE_POST,
								$this->getPluginInfo()->getSettingsManager()->getLocales()->getDefaultBlog(),
								(int) $this->getEntityHelper()->getOriginal( $post_id ),
								(int) $key,
								$this->getEntityHelper()->getTarget( $post_id, $key )
							);


						}
						break;
					case 'Download':
						foreach ( $locales as $key => $locale ) {
							$submission = null;
							if ( $submissions ) {
								foreach ( $submissions as $item ) {
									/** @var SubmissionEntity $item */
									if ( $item->getTargetBlog() == $key ) {
										$submission = $item;
										break;
									}
								}
							}
							if ( $submission ) {
								$manager->download( $submission );
							}
						}
						break;
				}
			}
		}
		add_action( 'save_post', array ( $this, 'save' ) );
	}
}