=== Smartling Connector ===
Contributors: smartling
Tags: translation, localization, multilingual, internationalization, smartling
Requires at least: 5.5
Tested up to: 6.6.2
Requires PHP: 8.0
Stable tag: 5.0.0
License: GPLv2 or later

Translate content in WordPress quickly and seamlessly with Smartling, the industry-leading Translation Management System.

== Description ==

The Smartling Connector facilitates the translation of WordPress content within Smartling.
Easily installed with minimal developer support, the combination of WordPress and Smartling provides users with a powerful technology solution to manage the translation and localization process with full visibility and control across the entire organization.
Translations are requested from within WordPress, and translated content is automatically returned to your WordPress environment.

Integration Features

* Automatic change detection for content updates
* Robust custom workflow engine configurable per language
* Automatic download of completed translations to WordPress
* Translation Memory integration
* No tie-ins to translation agencies or vendors
* Reporting for translation velocity, efficiency

== Installation ==

= Minimum Requirements =
* WordPress 5.5 or higher
* Multisite mode enabled
* PHP Version 8.0 or higher
* PHP extensions:
 * `mbstring`
 * `curl`
 * `libxml`
 * `xml`
 * `xmlreader`
 * `xmlwriter`
 * `yaml`
 * `gd`
 * `json`
* For "WP Engine" hosting maximum execution time should be set to 300 seconds.


The full step by step guide can be found [here](https://help.smartling.com/hc/en-us/articles/360008158133-WordPress-Connector-Install-and-Configure).

1. Upload the unzipped Smartling Connector plugin to the `/wp-content/plugins/` directory.
1. Go to the Plugins screen and **Network Activate** the Smartling Connector plugin.
1. Navigate to the Smartling - Settings page (`/wp-admin/network/admin.php?page=smartling_configuration_profile_list`) and click **Add Profile**.
1. Set the `Project ID`, `User Identifier` and `Token Secret` credentials found in the Smartling Dashboard for the project created for WordPress.
1. Select the Default Locale and the target locales for the translated sites you are creating.

== Frequently Asked Questions ==

Additional information on the Smartling Connector for WordPress can be found [here](https://help.smartling.com/hc/en-us/sections/360001716653-WordPress-Connector).

== Screenshots ==

1. Submit posts for translation using the Smartling Post Widget directly from the Edit page. View translation status for languages that have already been requested.
2. The Bulk Submit page allows submission of Post, Page, Category, Tag, Navigation Menu and Theme Widget text for all configured locales from a single interface.
3. Track translation status within WordPress from the Submissions Board. View overall progress of submitted translation requests as well as resend updated content.

== Changelog ==
= 5.0.0 =
* Related assets will now be processed for translation when the content is submitted from the widget, even if they were previously sent for translation

= 4.4.1 =
* Added possibility to clear the test run flag regardless of the test run status
* Fixed test run erroneously flagging taxonomy submissions as failed

= 4.4.0 =
* Added support for the Elementor Loop Carousel element
* Added support for the Elementor Image Gallery element

= 4.3.7 =
* Added support for Elementor internal url links and dynamic featured images

= 4.3.6 =
* Improved replacement of attachment URLs in translated content by using WordPress built-in attachment_url_to_postid() function
* Exclude and Copy fields by field name will now change to a stricter regex when changing the "Treat Exclude fields by field name and Copy fields by field name as regex" setting

= 4.3.5 =
* Added support for ACF metafields that have multiple relations

= 4.3.4 =
* Fixed issue where related ACF content was not detected when using the upload widget

= 4.3.3 =
* Fixed issue where Elementor related submissions were processed partially
* Added support for Elementor template widget

= 4.3.2 =
* Fixed a fatal error on the configuration screen

= 4.3.1 =
* Changed cloning to only work with submissions from the blog that initiates the cron job

= 4.3.0 =
* Cloning should now handle relations for supported external plugins
* Added support for Elementor social icons widget
* Fixed issue where supported elementor content was partially translated
* Fixed a fatal error on the Settings page when no configuration profiles are present

= 4.2.0 =
* Fixed cloned items having the same guid field

= 4.1.0 =
* Fixed navigation menu item parent relations after translation

= 4.0.0 =
* Reworked upload and download cron jobs to only work with submissions from the blog that initiates the cron job

= 3.12.1 =
* Fixed Elementor Templates type mismatch after translation or cloning with Elementor Pro

= 3.12.0 =
* Added ACF field group synchronization between configured sites on save.

= 3.11.7 =
* Added expert global setting to control cron lock ttl
* Added support for Elementor conditionals
* Added support of Unlimited Elements for Elementor Logo Marquee Widget

= 3.11.6 =
* Improved fix for Elementor Templates type mismatch after translation or cloning with Elementor Pro

= 3.11.5 =
* Fixed Elementor Templates type mismatch after translation or cloning
* Fixed error when sending related submissions to custom post types

= 3.11.4 =
* Fixed cloning not working when reference fields contain strings instead of expected integer ids
* Fixed Yoast SEO variables ingested for translation

= 3.11.3 =
* Fixed issue where submitting a previously cloned content for translation from the post widget did not send content for translation

= 3.11.2 =
* Removed elementor elements cache translation

= 3.11.1 =
* Added expert global setting to skip firing the `wp_after_insert_post` hook on target post creation

= 3.11.0 =
* Moved custom post type registration from `registered_post_type` to `wp_loaded` hook to avoid issues when the content type entity wrapper is not registered

= 3.10.0 =
* Remove a cloned status when the user requests translation of the previously cloned submission

= 3.9.18 =
* Updated yoast supported version to 24
* Fixed custom directives in expert settings slashes

= 3.9.17 =
* Added `smartling_filter_active_profiles` filter
* Added `Manual` translation download mode

= 3.9.16 =
* Excluded WordPress built in style editor strings from translation

= 3.9.15 =
* Added expert setting to skip ACF filter `acf_parse_save_blocks` removal. Defaults to on.

= 3.9.14 =
* Removed excessive logging

= 3.9.13 =
* Improved handling of service strings inside nested ACF fields: they no longer should be sent for translation
* Fixed Bulk Submit ingesting only one submission for translation when many were selected

= 3.9.12 =
* Fixed Bulk Submit ingesting only one submission for translation when many were selected

= 3.9.11 =
* Added related content detection for ACF field groups with group type `relation`

= 3.9.10 =
* Added logging of registered post types

= 3.9.9 =
* Fixed submissions not being uploaded when related assets list has multiple entries that point to the same content

= 3.9.8 =
* Fixed extra slashes being added in Gutenberg block attributes that had quotes after translation

= 3.9.7 =
* Excluded strings that require no translation from upload

= 3.9.6 =
* Improved ACF content detection for Gutenberg blocks, the plugin should no longer apply translation to ACF fields that don't require translation, such as `select`, `choice` and similar fields.

= 3.9.5 =
* Fixed submissions failing with "Batch is not suitable for adding files" error when uploading with related content
* Fixed auto authorise flag ignored when submitting from widget (again)

= 3.9.4 =
* Added support for related attachment from Advanced Custom Fields: Image Aspect Ratio Crop plugin
* Fixed import of fine-tuning lists failing to import partially

= 3.9.3 =
* Fixed submissions stuck in New status after uploading

= 3.9.2 =
* Fixed auto authorise flag ignored when submitting from widget

= 3.9.1 =
* Fixed submissions not being created on target site when uploading to multiple target sites
* Added possibility to dismiss admin notices for extended periods of time

= 3.9.0 =
* Reworked elementor elements processing
* Reworked upload queue

= 3.8.13 =
* Added `smartling_filter_before_clone_content_written` filter
* Added possibility of locking of nested JSON Gutenberg block attributes

= 3.8.12 =
* Fixed Elementor and other known plugins content not being added for translation when cron is invoked from non-source blog for submission
* Fixed strings not being added to some languages when uploading for multiple languages when there are more than one active profile for the source blog
* Improved shortcode detection to avoid Smartling placeholders present in translated content with shortcodes

= 3.8.11 =
* Added support for custom Smartling directives in the expert settings

= 3.8.10 =
* Added support for copying Elementor page settings on translation
* Added support for Elementor post blocks that reference other posts

= 3.8.9 =
* Added support for Yoast premium multiple related key phrases and synonyms

= 3.8.8 =
* Fixed Gutenberg attributes locking with nested blocks

= 3.8.7 =
* Added support for Redirection plugin

= 3.8.6 =
* Fixed Elementor Posts widget excerpts appearing in original language after translation

= 3.8.5 =
* Fixed Gutenberg attributes locking

= 3.8.4 =
* Added Bulk Submit submission status filter
* Fixed Elementor Posts widget excerpts appearing in original language after translation
* Daily bucket jobs date format will now use WordPress settings instead of US defaults

= 3.8.3 =
* Added support for Elementor popups that reference related content

= 3.8.2 =
* Added Elementor widgets `content` field to translatable fields, removed `anchor` field from translatable fields.
* Elementor CSS is now regenerated for downloaded content when translations are applied
* Reworked supported external plugins to not include known problematic and irrelevant fields for translation, even if the plugins are not detected or disabled

= 3.8.1 =
* Fixed fatal error when downloading translations if a nested locked Gutenberg block was present in the target content, but not in the source

= 3.8.0 =
* Changed Translation Lock screen: locked strings persist when cloning

= 3.7.1.1 =
* Bugfix release for broken widget download button

= 3.7.1 =
* Added support for Elementor icon widget

= 3.7.0 =
* Added the possibility to lock attributes in a Gutenberg block. To accomplish this, manually add the attribute `smartlingLockedAttributes` to the block and set its string value to the comma-separated list of attributes you do not want to be changed when translation is applied
    <!-- wp:paragraph {"smartlingLockId":"apkmc", "lockedAttribute1":"someValue","lockedAttribute2":"otherValue", "smartlingLockedAttributes":"lockedAttribute1,lockedAttribute2"} -->
        <p>text here</p>
    <!-- /wp:paragraph -->

= 3.6.2 =
* Added ExportedApi::ACTION_AFTER_TARGET_CONTENT_WRITTEN. This action is invoked after target content has been written to the WordPress database, both after translation and cloning
* Added Elementor links rewrite. The plugin will now try to replace links to known content in the target site

= 3.6.1 =
* Added confirmation for upload queue purge
* Added filter for cancelled submissions to Translation Progress screen
* Added upload action for Translation Progress screen. This will upload content into last job, if any, or will set status to selected submissions to Failed, if none.

= 3.6.0 =
* Added support for block level locking when cloning content. The target locked blocks that are present in the source page will have their contents preserved when cloning

= 3.5.3 =
* Added support for Elementor icon-list widget

= 3.5.2 =
* Fixed Elementor elements that could have a background image from the library but didn't have the images set, preventing valid background images from being processed as related content

= 3.5.1 =
* Added Elementor background images processing as related content

= 3.5.0 =
* Fixed a use case in which an already translated post was re-uploaded, new source strings were not authorized, and the connector automatically re-delivered translations. It happened because all authorized strings are published. This type of re-delivery confused people because it was unexpected, but it also included new strings that were untranslated.
Starting with this release, automatic delivery will occur when all authorized strings have been translated and published. Any unauthorized string will prevent the automatic delivery. To unblock automatic delivery, any unauthorized strings should be Excluded or Authorized. Manual delivery is still possible at any translation status from the sidebar widget.

= 3.4.5 =
* Added bulk submit screen backend filter
* Removed broken bulk submit screen sorting by status

= 3.4.4 =
* Fixed multiple target assets and submissions being created when sent with two levels deep dependencies in cases of complex relations. Added UI to review and fix such tangled assets.

= 3.4.3 =
* Added support of Elementor version 3.15
* Fixed post parent not being properly updated when cloning
* Fixed issues when cloning serialized metadata values

= 3.4.2 =
* Changed default Bulk Submit screen type to be Posts
* Fixed Bulk Submit screen not showing unpublished content

= 3.4.1 =
* Fixed Elementor translation uploading untranslatable fields

= 3.4.0 =
* Improved support for Elementor: fixed issue when translated related assets and css were visible when editing, but missing on the frontend
* Improved blog removal hook: will delete profiles that reference the deleted blog
* Improved adding Smartling lock attributes in the blog editor: broader support for other block editor javascript

= 3.3.1 =
* Changed behaviour for known supported plugins: unsupported versions should not upload content not meant for translation
* Improved batch uid handling to avoid batch not suitable for adding files error when uploading

= 3.3.0 =
* Reworked cloning to better preserve non-string values
* Added support of Elementor version 3.14

= 3.2.0 =
* Removed automated related items processing when uploading content. Previously, the plugin tried to create placeholders for known related content in taxonomies automatically, now any related content should be sent manually from post widget or bulk submit screen.
* Fixed regression for retries in automated daily bucket jobs
* Fixed Gravity Forms handler causing translations from Beaver Builder and Elementor to not apply

= 3.1.8 =
* Added retries for automated daily bucket jobs in cases where a translation batch was prematurely marked completed
* Fixed taxonomy page submission widget layout
* Fixed Gutenberg block level locking attributes not created on content save due to a possible race condition

= 3.1.7 =
* Fixed Beaver Builder unable to apply translation to outdated content

= 3.1.6 =
* Added support of Elementor version 3.13

= 3.1.5 =
* Fixed related submissions file name

= 3.1.4 =
* Fixed issue where cloning or translation would fail due to undefined wp_read_video_metadata function
* Fixed media attachment rules being ignored when translating
* Added support of Elementor versions 3.11 and 3.12

= 3.1.3 =
* Fixed issue where custom components could interfere with Elementor downloads

= 3.1.2 =
* Fixed translation lock screen locking unintended submissions

= 3.1.1 =
* Added support of Elementor version 3.9 to 3.10

= 3.1.0 =
* Added Gravity Forms support

= 3.0.4 =
* Added Gutenberg block rules import from exported file

= 3.0.3 =
* Fixed wrong content IDs being sent for translation when sending related content one or two levels deep
* Fixed plugin unable to change target content IDs when encountering a non-standard post type

= 3.0.2 =
* Removed possible target site content duplication when translating

= 3.0.1 =
* Added option to remove "Test run" flag from a blog that was previously used to do a test run

= 3.0.0 =
* Minimum required PHP version is now 8.0!
* Fixed upload queue getting stuck

Old entries moved to changelog.txt
