<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 22:47
 */

namespace Smartling\WP\Controller;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class PostWidgetController extends WPAbstract implements WPHookInterface {
	const WIDGET_NAME = 'smartling_connector_post_widget';
	const WIDGET_DATA_NAME = 'smartling_post_widget_data';

	public function register () {
		add_action( 'add_meta_boxes', array ( $this, 'box' ) );
		add_action( 'save_post', array ( $this, 'save' ) );
	}

	public function box ( $post_type ) {
		$post_types = array ( 'post' );
		if ( in_array( $post_type, $post_types ) ) {
			add_meta_box(
				self::WIDGET_NAME,
				'Smartling Post Widget',
				array ( $this, 'preView' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public function preView ( $post ) {
		wp_nonce_field( self::WIDGET_NAME, 'smartling_connector_nonce' );
		if ( $post->post_content
		     && $post->post_title
		) {
			$originalId = $this->getEntityHelper()->getOriginal($post->ID);
			$submission = null;

			if($originalId) {
				$submissions = $this->getManager()->getEntityBySourceGuid( $originalId );
			}
			$this->view( array(
				"submissions" => $submissions,
				"post" => $post
				)
			);
		} else {
			echo '<p>Need to save the post</p>';
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

		$data = $_POST['smartling_post_widget'];
		$locales = array();

		if(null !== $data && array_key_exists('locales', $data)) {

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
							$core = Bootstrap::getContainer()->get('entrypoint');

							$result = $core->sendForTranslation(
								WordpressContentTypeHelper::CONTENT_TYPE_POST,
								$this->getPluginInfo()->getOptions()->getLocales()->getDefaultBlog(),
								(int) $this->getEntityHelper()->getOriginal( $post_id ),
								(int) $key,
								$this->getEntityHelper()->getTarget( $post_id, $key )
							);

							$submission = $core->getLastSubmissionEntity();


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