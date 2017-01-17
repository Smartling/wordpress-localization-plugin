=== Smartling Connector ===
Contributors: smartling
Tags: translation, localization, localisation, translate, multilingual, smartling, internationalization, internationalisation, automation, international
Requires at least: 4.6
Tested up to: 4.7.1
Stable tag: 1.4.2
License: GPLv2 or later

Translate content in WordPress quickly and easily with Smartling’s Global Fluency Platform.

== Description ==

The Smartling Connector extends the WordPress interface for seamless management of the translation process, all while leveraging the tools contained in Smartling’s Global Fluency Platform. Easily installed with minimal developer support, the combination of WordPress and Smartling provides users with a powerful technology solution to manage the translation and localization process with full visibility and control across the entire organization.

Integration Features

* Automatic change detection for content updates
* Robust custom workflow engine configurable per language
* Automatic download of completed translations to WordPress
* Translation Memory integration
* No tie-ins to translation agencies or vendors
* Reporting for translation velocity, efficiency

== Installation ==

= Minimum Requirements =
* WordPress 4.6 or higher
* Multisite mode enabled
* Multilingual Press free plugin
* PHP Version 5.5 or higher
* PHP extensions:
 * `mbstring`
 * `curl`
 * `libxml`
 * `xml`
 * `xmlreader`
 * `xmlwriter`

1. Configure [Multilingual Press](https://wordpress.org/plugins/multilingual-press) using instructions found [there](http://support.smartling.com/hc/en-us/articles/205418457).
1. Upload the unzipped Smartling Connector plugin to the `/wp-content/plugins/` directory.
1. Go to the Plugins screen and **Network Activate** the Smartling Connector plugin.
1. Navigate to the Smartling - Settings page (`/wp-admin/network/admin.php?page=smartling_configuration_profile_list`) and click **Add Profile**.
1. Set the `Project ID`, `User Identifier` and `Token Secret` credentials found in the Smartling Dashboard for the project created for WordPress.
1. Select the Default Locale and the target locales for the translated sites you are creating.

== Frequently Asked Questions ==

Additional information on the Smartling Connector for WordPress can be found [there](http://support.smartling.com/hc/en-us/sections/200967468-Wordpress-Connector).

== Screenshots ==

1. Submit posts for translation using the Smartling Post Widget directly from the Edit page. View translation status for languages that have already been requested.
2. The Bulk Submit page allows submission of Post, Page, Category, Tag, Navigation Menu and Theme Widget text for all configured locales from a single interface.
3. Track translation status within WordPress from the Submissions Board. View overall progress of submitted translation requests as well as resend updated content.

== Changelog ==
= 1.4.2 =
* Fixed issue with possible metadata duplication if metadata contains serialized PHP array.
* Fixed issue with detected attachments translation.

= 1.4.1 =
* Fixed UI issue on Bulk Submit screen for all post-based types. Duplicate of items were displayed
* Updated error messages for submission Failed status. Added more details about root cause
* Fixed custom types registration. Custom type was not registered properly if it derives from builtin ContenTypeXXX classes

= 1.4.0 =
* Changed standard filters. Reset filters for configuration profiles needed.
* Added API to register custom content-types. Breaks backward compatibility with 1.3.x branch. Custom content-types registered in old way (plugin code modification) won't work anymore.
* Minimum required PHP version changed to 5.5
* Small other architecture improvements that simplify registering custom content-types.
* Removed ReferencedPageProcessor, ReferencedCategoryProcessor filters

= 1.3.7 =
* Added ability to configure cron jobs TTL
* Improved upload functionality.

= 1.3.6 =
* Added filter to clean source content from invalid characters before sending for translation
* Improved general stability
* Improved logging for upload cron job (added size of queue)
* Fixed small UI issues
* Fixed metadata cleanup

= 1.3.5 =
* Added support for blocking of parallel execution of smartling-connector cron jobs
* Added buttons in Expert settings of configuration profile that allows reset values to default or undo unsaved changes.
* Added new option that allows download translation as soon as string is published (not the whole file)
* Added new option that allows to choose if translation should be fully rebuild on download.
* Improved cleanup to remove all related submissions if blog is deleted.

= 1.3.4 =
* Fixed issue with database update script

= 1.3.3 =
* Added support for cyclic references between content-types (an example: parent page, ACF page_reference, etc)
* Changed behaviour of `Clone` button. Now while cloning smartling connector plugin is looking for references to link content
* Changed the way smartling connector works with Upload queue
 * Upload executes in two phases. First phase is lookup for references and creation of needed submissions. Second phase is just upload
 * Less time and resources needed to process Upload queue
* Synchronous nested uploads were replaced with asynchronous
* Download log button now displays the current log file size. Added additional link that allows to remove current log file
* Added by default for new configuration profiles rules to ignore [Kraken.io](https://wordpress.org/plugins/kraken-image-optimizer/) metadata fields
* Small fixes and improvements

= 1.3.2 =
* Fixed possible issue with `ReferencedContentProcessor` that may cause referenced content will not be translated

= 1.3.1 =
* Improved metadata filtering. Developers can use API to setup build-in filters for their custom metadata fields (useful in case of ACF usage) and even create own customized processors and embed them into their plugin or theme
* Improved stability (minor possible issues fixed)

**Upgrade steps**

Smartling connector improves metadata field processing to extend customization abilities
Added filter that allows to handle any type of referenced content (if supported by smartling connector)
Because of this architecture update a small reconfiguration needed after update

0. Go to Smartling Settings page (`/wp-admin/admin.php?page=smartling_configuration_profile_list`) and follow next instructions for each configuration profile:
1. Go to edit profile screen
2. Click `Show Expert Settings`
3. Remove next lines from `Exclude fields by field name` edit box:
    * `post_parent`
    * `parent`
4. Add next line to `Exclude fields by field name` edit box:
    * `_wp_attachment_metadata.*`
5. Add next lines to `Copy fields by field name` edit box:
    * `post_parent`
    * `parent`
6. Click `Save Changes`

= 1.3.0 =
* Refactored architecture to support custom metadata handlers for properties
* Refactored handling of post `Featured Image`. It was moved from core functionality to the new metadata handler
 * Now it can be used also for handling image type in [ACF](https://www.advancedcustomfields.com/)
 * Also it can handle properties with reference to any file
* Added the new metadata handler which can copy value from source to target without translation. It's useful if you need to propagate value from source blog to target blogs without translations (numbers; dates; etc)

= 1.2.7 =
* Fixed case when shortcode is not properly masked or is double masked

= 1.2.6 =
* Improved filters on Submissions Page
* Improved code stability

= 1.2.5 =
* Added support of Wordpress 4.6 (Upgrade to Wordpress 4.6 is required)
* Fixed possible issue related to attachments when Wordpress database is corrupted
* Fixed some possible minor issues

= 1.2.4 =
* Fixed possible PHP NOTICE in URL converting

= 1.2.3 =
* Minor refactoring and added more logging
* Fixed case when translation is not downloaded in case its status is broken in DB (progress = 100% but status = "In Progress")

= 1.2.2 =
* Fixed issue with checking translation progress by cron job

= 1.2.1 =
* Added ability to clone content without sending it to translation

= 1.2.0 =
* Fixed bug with shortcodes translation when the quotes of attributes could be broken. Shortcodes in source strings are masked and any translatable attribute of shortcode is added for translation as a separate string. This fix may change source strings sent to Smartling. Content should be resend to Smartling to apply fix

= 1.1.11 =
* New action 'smartling_before_init' is available to tune smartling-connector in runtime

= 1.1.10 =
* Fixed possible issue if translation content is deleted
* Added submission cleanup functionality

= 1.1.9 =
* Posts and based on posts (e.g., pages) content translations are created as 'drafts' and converter to 'published' once translation is 100% ready
* Media with absolute URL from media library (e.g. images) are tracked and also translated
* Small UI updates on Bulk Submit screen
* Small UI updates on profile edit form screen
* Small updates on Submission Board screen
* Submission 'Failed' status has a tooltip with last error info
* Connection to Smartling servers is optimized

= 1.1.8 =
* Added hierarchy support for categories
* Added hierarchy support for pages

= 1.1.7 =
* Added ability to detect changes in original content for Posts, Pages, Tags, Categories and their derivatives. Changes can be  resend to Smartling automatically
* Added notification in admin panel for any errors during database migration
* Optimized Database usage and internal queues
* Fixed bug with incorrect alter database query for utf8 databases found in version 1.1.6
* Small UI updates and improvements

= 1.1.6 =
* Added ability to track changes in translations (on Smartling side). In other words, Smartling Connector now can detect retranslation in already translated post; categories; etc and redownload updated translations automatically
* Added filter that allows fileUri modification
* Added the new table on Smartling Settings which allows to manage Smartling cron jobs and related queues. It can be useful during site integration and troubleshooting

**Upgrade steps**

Smartling Connector registers cron jobs on `Plugin Activate` hook. But this event doesn't happen when you update plugin from marketplace. To solve this issue you should perform 2 simple steps right after updating Smartling Connector plugin. If you miss steps below then basic operations (upload an original content; download translated content) will not work

1. Open `Plugins` page and find Smartling connector (`/wp-admin/network/plugins.php?s=smartling`)
1. Click `Network Deactivate`
1. Click `Network Activate`

= 1.1.5 =
* Added support for menu hierarchy. Now translated menu will be always insync with the original menu even if you add\delete\reorder menu item in an original one
* Fixed bug with database tables creation for some MySQL versions
* Small fixes and updates

= 1.1.4 =
* Improved file URI generation for Smartling Dashboard.
* Small fixes and updates

= 1.1.3 =
* Added support of term metadata Wordpress API
* Fixed UI issue found in Firefox and Safari
* Fixed issue that prevents target content linking (post with categories)
* Minor UI improvements
* Minor internal improvements

= 1.1.2 =
* Added ability to manage translation filters on Profile Edit Screen
* Minor UI updates

= 1.1.1 =
* Added capabilities to split plugin functionality between roles

= 1.1.0 =
* Upgrade to new Smartling API

= 1.0.29 =
* Added support of images from media library referenced by relative URL

= 1.0.28 =
* Fixed issue with unsupported types while sending to Smartling

= 1.0.27 =
* Fixed issue with structure deserialization
* Fixed issue with Navigation Menu translation
* Added ability to translate Featured Image
* Added internal API events
* Logging improved

= 1.0.26 =
* Fixed plugin issue related to site deletion that is used in settings
* Added ability to translate media attachments
* Fixed issue with lost relation to categories on translation download
* Fixed issue with post duplicate on translation download
* minor updates and improvements

= 1.0.25 =
* Added ability to translate standard WordPress widgets from the Bulk Submit Page

= 1.0.22 =
* Copy non-translatable custom fields from original to translated posts

= 1.0.21 =
* Database cron marker allows configuration of cron for high-load websites
* Cron tasks triggered every 5 minutes instead of every hour

= 1.0.19 =
* Pages can be locked to prevent changes to translated post/page from being overwritten

= 1.0.18 =
* Smartling self-diagnostics are displayed on admin panel
* Smartling database tables are rebuilt if required on plugin activation

= 1.0.16 =
* Hide content types that are supported by the connector but not present in WordPress

= 1.0.15 =
* Basic support for custom content types

= 1.0.14 =
* Compatibility with WordPress 4.2.x.
* Improvements to Locale display on Submissions Board and Bulk Submit screens

= 1.0.12 =
* Support for multiple translation profiles

== Upgrade Notice ==

= 1.1.6 =
* Improved detection of translated content changes. Now Smartling widgets display translation status more accurate
* Important! Manual steps are required in case migration from previous version
