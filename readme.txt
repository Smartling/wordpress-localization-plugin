=== Smartling Connector ===
Contributors: smartling
Tags: translation, localization, localisation, translate, multilingual, smartling, internationalization, internationalisation, automation, international
Requires at least: 5.5
Tested up to: 5.9
Requires PHP: 7.4
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
* PHP Version 7.4 or higher
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
= 3.0.0 =
* The plugin will no longer automatically fail submissions based on content type not registered in WordPress.

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
