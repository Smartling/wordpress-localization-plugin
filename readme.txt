=== Smartling Connector ===
Contributors: smartling
Tags: translation, localization, localisation, translate, multilingual, smartling, internationalization, internationalisation, automation, international
Requires at least: 5.5
Tested up to: 5.9
Requires PHP: 7.4
Stable tag: 2.12.2
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

= 1.15.7 =
* Added media attachments rules user interface in Fine Tune section. It allows to specify paths to media attachments inside Gutenberg blocks. The plugin will link the attachment ids for known paths between source and target sites

= 1.15.6 =
* Fixed issue where translated image-text blocks appear to have "invalid content" warnings in editor

= 1.15.5 =
* Fixed issue where quotes would break encoded JSON values in custom blocks

= 1.15.4 =
* Fixed issue where deleting a blog would fail with a critical wordpress error

= 1.15.3 =
* Fixed issue where menu items were not sent for translation when sending menus for translation

= 1.15.2 =
* Fixed issue when notices were not displayed on successful translation download
* Fixed compatibility with newer Multilingual Press plugin versions

= 1.15.1 =
* Auto synchronize properties on translated page with source feature doesn't alter locked and excluded fields

= 1.15.0 =
* Added new UI (Smartling -> Taxonomy links) where you can link existing translated taxonomies with the source taxonomies. It helps connector to respect existing translations and use them in translated posts.
* Fixed issue where attachments were partially uploaded if post content contained multiple ACF blocks of the same type

= 1.14.2 =
* Fixed issue where submitting related content type that was not registered in WordPress caused submission to fail

= 1.14.1 =
* Fixed issue where submitting a widget that has navigation menus for translation caused a WordPress emergency

= 1.14.0 =
* Major change in `Auto synchronize properties` behavior. Starting from this release the connector will always replace all metadata in the translated post with metadata from the source post. The translations are then applied to freshly copied metadata.
* If you use ACF flexible fields then you should enable `Auto synchronize properties`. It will force connector always replicate ACF fields from source to target and keep flexible fields consistent
* Fixed error messages not displaying when adding content to existing jobs

= 1.13.11 =
* Fixed upload flow when post gets uploaded to Smartling without related content

= 1.13.10 =
* Fixed download flow if ACF attachment field has an empty id inside Gutenberg block

= 1.13.9 =
* Fixed upload issue if translation was requested from "Create job" form in WP 5.5.1+

= 1.13.8 =
* The plugin will now mark submissions as failed when receiving certain errors from the API. Such submissions will be skipped while checking submissions statuses unless resubmitted manually

= 1.13.7 =
* Fixed bulk submit page not submitting when post type slug contained dashes

= 1.13.6 =
* Fixed daily bucket job uploads handling failure due to content being added to an unsuitable translation job

= 1.13.5 =
* Fixed issue where submissions would not download if the upload sent only current content for translation

= 1.13.4 =
* Improved support for ACF in Gutenberg blocks when translations are being downloaded in bulk

= 1.13.3 =
* Added support for attachments inside ACF fields in Gutenberg blocks

= 1.13.2 =
* Fixed issue where clicking the download button would not download translations
* Skip all related content checkbox now has a default state expert setting
* Further improved support for Gutenberg blocks (editor should no longer display "invalid content" warning)
* Fixed issue with some items not being ingested when using ACF plugin

= 1.13.1 =
* Display reason for failed submissions in status circle
* Improved support for core Gutenberg blocks (fixed issue with "This block contains unexpected or invalid content" message when editing the block in translated content)

= 1.13.0 =
Added expert setting to support regular expressions for Exclude fields by field name and Copy fields by field name

= 1.12.7 =
* Fixed issue when Smartling widget erroneously reported that translations were downloaded even if they weren't
* Fixed issue with daily bucket jobs being created when active profile is set to manual upload

= 1.12.6 =
Improved translation support of ACF Blocks for Gutenberg

= 1.12.5 =
Fixed issue where a content linking error causes a wordpress critical error message

= 1.12.4 =
* Fixed time submitted display on translation progress page
* Fixed ACF integration: images and other non-translated media should copy to translated content

= 1.12.3 =
Fixed issue with locale labels in locale selection wizard when Multilingual Press locale is unknown

= 1.12.2 =
Fixed issue with creating a new job from the `Post Edit` screen

= 1.12.1 =
* Changed the default behavior for how we handle related content for the "request translations" flow (see v1.12.0 for more details):
 * When you request the translation from the `Post Edit` screen it submits only the current page and optionally you can tell it to submit only the immediate related content (tags, categories, images, etc)
 * When you request from the `Bulk Submit` screen, it will only submit the selected content (any related content should be requested manually)
* Fixed compatibility with the ACF plugin and extended support for unicode characters (emoji, accented characters) in translation

= 1.12.0 =
This release brings major changes to how the connector works with related content. An example, a page may have a tag and a featured image. Before when you requested translation for a page, the connector submitted the page, plus the tag and image (3 file uploads in total). This approach guarantees that the translated page will look similar to the original page and the layout is not broken. The drawback of this approach, you can't control how much content will be uploaded to Smartling. If your site has cross-references between pages then requesting a single page may trigger hundreds of uploads.
The new connector version adds the new toggle that controls this behavior. Now you can tell the connector to submit only requested content or requested content + immediate related content.

The new toggle can be found in `Smartling` -> `Settings` -> `Show Expert Settings` -> `Handle relations`, where:

* `Automatically` - use the legacy approach, upload as much related content to Smartling as possible.
* `Manually` - use the new approach, upload only the requested content or the requested content + the immediate related content.

= 1.11.2 =
* Fixed issue with download button at post edit page.

= 1.11.1 =
* Added required third-party libraries

= 1.11.0 =
* Added extension to manage shortcodes and filters to be handled by smartling-connector.

Old entries moved to changelog.txt
