<?php

namespace Smartling\Specific\SurveyMonkey;

use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class PrepareRelatedSMSpecificTrait
 *
 * @package Smartling\Specific\SurveyMonkey
 */
trait PrepareRelatedSMSpecificTrait {

	/**
	 * SM Specific
	 *
	 * @param SubmissionEntity $submission
	 * @param string           $relatedContentType
	 */
	private function processMediaAttachedToWidgetSM ( SubmissionEntity $submission, $relatedContentType ) {
		if (
			WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT === $relatedContentType
			&& WordpressContentTypeHelper::CONTENT_TYPE_WIDGET === $submission->getContentType()
		) {
			$widgetSettings = $this->readContentEntity( $submission )->getSettings();

			if ( array_key_exists( 'attachment_id', $widgetSettings ) ) {
				$newMediaId = $this->translateAndGetTargetId(
					WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT,
					$submission->getSourceBlogId(),
					(int) $widgetSettings['attachment_id'],
					$submission->getTargetBlogId()
				);

				/**
				 * @var WidgetEntity $targetContent
				 */
				$targetContent             = $this->readTargetContentEntity( $submission );
				$settings                  = $targetContent->getSettings();
				$settings['attachment_id'] = $newMediaId;
				$targetContent->setSettings( $settings );
				$this->saveTargetEntity( $submission, $targetContent );
			}
		}
	}

	/**
	 * @param SubmissionEntity $submission
	 * @param string           $relatedContentType
	 */
	private function processTestimonialAttachedToWidgetSM ( SubmissionEntity $submission, $relatedContentType ) {
		if (
			WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL === $relatedContentType
			&& WordpressContentTypeHelper::CONTENT_TYPE_WIDGET === $submission->getContentType()
		) {
			$widgetSettings = $this->readContentEntity( $submission )->getSettings();

			if ( array_key_exists( 'testimonial_id', $widgetSettings ) ) {
				$newTestimonialId = $this->translateAndGetTargetId(
					WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL,
					$submission->getSourceBlogId(),
					(int) $widgetSettings['testimonial_id'],
					$submission->getTargetBlogId()
				);

				/**
				 * @var WidgetEntity $targetContent
				 */
				$targetContent              = $this->readTargetContentEntity( $submission );
				$settings                   = $targetContent->getSettings();
				$settings['testimonial_id'] = $newTestimonialId;
				$targetContent->setSettings( $settings );
				$this->saveTargetEntity( $submission, $targetContent );
			}
		}
	}

	/**
	 * @param SubmissionEntity $submission
	 * @param string           $relatedContentType
	 */
	private function processTestimonialsAttachedToWidgetSM ( SubmissionEntity $submission, $relatedContentType ) {
		if (
			WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL === $relatedContentType
			&& WordpressContentTypeHelper::CONTENT_TYPE_WIDGET === $submission->getContentType()
		) {
			$widgetSettings = $this->readContentEntity( $submission )->getSettings();

			if ( array_key_exists( 'testimonials', $widgetSettings ) ) {
				$newTestimonials = [ ];
				foreach ( $widgetSettings['testimonials'] as $testimonialId ) {
					$newTestimonials[] = $this->translateAndGetTargetId(
						WordpressContentTypeHelper::CONTENT_TYPE_POST_TESTIMONIAL,
						$submission->getSourceBlogId(),
						(int) $testimonialId,
						$submission->getTargetBlogId()
					);
				}
				/**
				 * @var WidgetEntity $targetContent
				 */
				$targetContent            = $this->readTargetContentEntity( $submission );
				$settings                 = $targetContent->getSettings();
				$settings['testimonials'] = $newTestimonials;
				$targetContent->setSettings( $settings );
				$this->saveTargetEntity( $submission, $targetContent );
			}
		}
	}
}