=== Smartling Connector ===
Contributors: smartling
Tags: translation, localization, localisation, translate, multilingual, smartling, internationalization, internationalisation, automation, international
Requires at least: 5.5
Tested up to: 6.1
Requires PHP: 8.0
Stable tag: 3.0.0
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
= 3.0.1 =
* Added option to remove "Test run" flag from a blog that was previously used to do a test run

= 3.0.0 =
* Minimum required PHP version is now 8.0!
* Fixed upload queue getting stuck

= 2.16.7 =
* Added Beaver Builder 2.6 to supported versions

= 2.16.6 =
* Changed handling of related items: plugin will now honor the changes in registered custom post types and taxonomies by ExportedApi::FILTER_SMARTLING_REGISTER_CUSTOM_POST_TYPE and ExportedApi::FILTER_SMARTLING_REGISTER_CUSTOM_TAXONOMY when sending related items for translation

= 2.16.5 =
* Tested compatibility with WP 6.1

= 2.16.4 =
* Changed related assets handling. The plugin will treat unknown post types as the default 'attachment' post type when working with related content

= 2.16.3 =
* Fixed locked serialized metadata fields becoming corrupt after translation is applied

= 2.16.2 =
* Improved logging when discovering related assets
* PHP 7.4 will stop receiving security updates on 28th of November! Refactored code to remove known E_DEPRECATED notices, next major release expected to require at least PHP 8.1

= 2.16.1 =
* Fixed Gutenberg block rules with JSON paths in nested Gutenberg blocks

= 2.16.0 =
* Removed ACF and manual relation handling obsolete expert settings. The behaviours controlled by these options have been reverted to the default values

= 2.15.1 =
* Added options to prevent plugin from escaping post content and metadata before saving. This is useful if there are other plugins that hook on the "post content" or "save metadata" and add extra escaping slashes to them

= 2.15.0 =
* The plugin will no longer automatically fail submissions based on content type not registered in WordPress

= 2.14.9 =
* Added support for Elementor global widgets translation using the related items translation interface

= 2.14.8 =
* Improved supported for nested ACF in Gutenberg block fields (e.g. repeater blocks)

= 2.14.7 =
* Added debugging for related content replacer
* Added diagnostic message for WordPress not in multisite mode
* Fixed critical error with locking when metadata was null
* Improved detection of active AIOSEO pack, Beaver Builder and Elementor plugins when running with limited capabilities

= 2.14.6 =
* Added Beaver Builder plugin support

= 2.14.5 =
* Fix scoping to avoid conflict with Symfony\Polyfill\Intl\Idn

= 2.14.4 =
* Fixed post content not being sent for translation when Elementor is active, but not used for a specific post

= 2.14.3 =
* Fixed errors when saving metadata by improving the escaping process

= 2.14.2 =
* Fixed issue where content without Elementor data was unable to be sent for translation

= 2.14.1 =
* Added sending related attachments for translation along with Elementor plugin content

= 2.14.0 =
* Added Elementor plugin support

= 2.13.6 =
* Improved AIOSEO pack plugin translation for fields that contain tags

= 2.13.5 =
* Fixed upload queue length appearing stuck

= 2.13.4 =
* Added actions to alter translated content or do other actions just before translation gets saved
* Fixed block level locking for nested Gutenberg blocks

= 2.13.3 =
* Added support for sending related items from within ACF Gutenberg blocks

= 2.13.2 =
* Fixed AIOSEO pack plugin translation using wrong fieldset when translating taxonomies

= 2.13.1 =
* Added display of error messages when widget uploads fail
* Fixed check/uncheck all links in widgets affecting all Smartling checkboxes on a page
* Fixed source title detection for taxonomy submissions

= 2.13.0 =
* Added support for AIOSEO pack
* Fixed shortcodes with no attributes preventing content uploads
* Fixed widget uploads broken when an audit log record could not be created

= 2.12.9 =
* Fixed broken content in ACF fields after translation

= 2.12.8 =
* Fixed cloning affecting fully locked submissions

= 2.12.7 =
* Fixed terms meta values stored as array instead of scalar values

= 2.12.6 =
* Added purge upload queue action (sets all NEW submissions to CANCELLED)
* Fixed smartlingLockId attribute being sent for translation

= 2.12.5 =
* Fixed taxonomy page widget not downloading content

= 2.12.4 =
* Fixed parent page not being sent for translation when sending content one or two levels deep

= 2.12.3 =
* Added support for nested attributes via JSON path in fine-tuning

= 2.12.2 =
* Fixed related content not being sent for translation from the post based content UI

= 2.12.1 =
* Fixed issue where ACF blocks couldn't be decoded and were missing in translated submissions

= 2.12.0 =
* Fixed regression where media duplication occured and/or related item ids were not changed when cloning items. This release majorly changes the handling of related items while cloning, from this release onwards only the items directly sent for cloning (including cloning one or two levels deep) should be cloned, without any related submissions.

= 2.11.5 =
* Cloned submissions are excluded from status check background job. This change fixes cloned submissions changing status from completed to failed after time passes

= 2.11.4 =
* Fixed upload widget not uploading content

= 2.11.3 =
* Fixed content not sent for translation when no related content is present

= 2.11.2 =
* Fixed relations to second level content not changed in target blog when cloning two levels deep

= 2.11.1 =
* Fixed media library attachments duplication when cloning

= 2.11.0 =
* Added test run. Test run submits posts and pages to a new blog for "pseudo" translation. This should help quickly check compatibility between your content and the plugin.

= 2.10.0 =
* Changed media attachment rules to Gutenberg block rules. It is now possible to copy a block attribute value instead of translating it, or exclude it entirely. This is set up in the Fine-Tuning section.

= 2.9.0 =
* Added retries for communication with external locking service

= 2.8.4 =
* Fixed submissions not being deleted on media attachments deletion. It is now easier to resubmit missing media attachments when translating related content.
* Added display of status messages when running cron jobs manually

= 2.8.3 =
* Fixed reference to image in core/image Gutenberg block's inner HTML class on translation to prevent WordPress notice at translated post edit page
* Changed serialization to prevent php built-in serialize function vulnerabilities

= 2.8.2 =
* Added escaping for job names in Translation Progress screen
* Submissions get deleted when content they reference is deleted. For example, this will allow easy resubmission of related content that was deleted on the target site

= 2.8.1 =
* Added new action in Translation Progress screen to check status and fail submissions for which no corresponding Smartling locale exists
* Fixed nested Gutenberg blocks losing their attributes after translation
* Disabled loading of external entities in libxml

= 2.8.0 =
* Scoped dependencies to prevent conflicts with other installed versions of GuzzleHttp and Symfony libraries
* Fixed content not being added to upload from post widget

= 2.7.1 =
* Fixed relations not changed in target blog when cloning

= 2.7.0 =
* Switched from database to external service locking for cron jobs. It avoids the issue when hosting doesn't allow SQL `SELECT ... FOR UPDATE`

= 2.6.0 =
* Added two levels deep cloning/translation to post widget
* Fixed menu items not being submitted for translation when using bulk submit to upload menus

= 2.5.3 =
* Fixed downloads failing if translated content has html anchors

= 2.5.2 =
* Fixed block locking UI not visible when a blog is both a source and a target for translation
* Fixed overly strict language mappings validation in profile configuration

= 2.5.1 =
* Fixed WordPress fatal error on plugin activation

= 2.5.0 =
* Added support for MultilingualPress3. On creating submissions Smartling connector will also add content relations for MLP3.

= 2.4.14 =
* Visual changes in the locking sidebar

= 2.4.13 =
* Fixed misconfiguration

= 2.4.12 =
* Gutenberg block level locking ids now created on every visual editor operation instead of on saving content
* Added replacing of relative links in A tags to other post based content

= 2.4.11 =
* Fixed Gutenberg block level locking frontend not working with WordPress 5.7 and above

= 2.4.10 =
* Added validation to language mappings in profile configuration to prevent undefined behaviour when a single Smartling locale was mapped to multiple target blogs

= 2.4.9 =
* Added expert setting to disable automatic generation of Gutenberg blocks locking ids (defaults to OFF)
* Fixed critical wordpress error when cloning from Bulk Submit page

= 2.4.8 =
* Add locking for Gutenberg blocks in the Gutenberg visual editor based on block ids. Block ids will be generated randomly for all Gutenberg blocks on saving content. After new content is sent for translation and downloaded, a new sidebar block "Smartling lock" will become available for blocks with generated ids. Previous location based method for locking is now obsolete, please re-lock the required blocks in the visual editor, as the support for location-based locking will be disabled in a future release.

= 2.4.7 =
* Added job name search to search field in `Translation Progress`

= 2.4.6 =
* Fixed delivery of Gutenberg blocks where attributes have nested objects. Before appropriate escaping was done only for attributes on the top level only

= 2.4.5 =
* Improve job link in translations progress

= 2.4.4 =
* Added escaping to HTML output to prevent xss injections

= 2.4.3 =
* Fixed an issue when job links were not displayed on `Translation Progress` after submitting content for translation for the first time

= 2.4.2 =
* Fixed an issue where image attachments in ACF blocks do not get uploaded

= 2.4.1 =
* Fixed an issue with downloads when the post has links to images that are not attachments

= 2.4.0 =
* Added ability to lock inner Gutenberg blocks

= 2.3.1 =
* Fixed menu translation when menu item is a link to related content

= 2.3.0 =
* Added ability to use all registered post types for references in Gutenberg blocks
* Fixed menu translation when menu items consisted of post types or taxonomies

= 2.2.1 =
* Fixed critical wordpress error on new profile creation

= 2.2.0 =
* Added more publish options for delivering translated posts. Now you can choose what post status to set after delivering: don't change existing status; set to `draft`; set to `published`

= 2.1.0 =
* Minimum required PHP version is now 7.4!
* Added a link to the most recent job for submissions on Translation Progress screen

= 2.0.1 =
* Fixed extra slashes being added when using fine tuning forms

= 2.0.0 =
* Minimum required PHP version is now 7.2! The next plugin release will require PHP 7.4.
* Added ability to lock translation for some of the Gutenberg blocks in post content. The lock will preserve translated blocks, unless there are changes to the parents' post structure of blocks, such as blocks reordering, or adding new blocks.

Old entries moved to changelog.txt
