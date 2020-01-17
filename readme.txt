=== Smartling Connector ===
Contributors: smartling
Tags: translation, localization, localisation, translate, multilingual, smartling, internationalization, internationalisation, automation, international
Requires at least: 4.6
Tested up to: 5.3
Stable tag: 1.11.2
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
* PHP Version 5.6 or higher
* PHP extensions:
 * `mbstring`
 * `curl`
 * `libxml`
 * `xml`
 * `xmlreader`
 * `xmlwriter`
 * `yaml`
 * `gd`
* For wpengine hosting maximum execution time should be set to 300 seconds.


1. Configure [Multilingual Press](https://wordpress.org/plugins/multilingual-press) using instructions found [here](https://help.smartling.com/docs/wordpress-connector-install-and-configure).
1. Upload the unzipped Smartling Connector plugin to the `/wp-content/plugins/` directory.
1. Go to the Plugins screen and **Network Activate** the Smartling Connector plugin.
1. Navigate to the Smartling - Settings page (`/wp-admin/network/admin.php?page=smartling_configuration_profile_list`) and click **Add Profile**.
1. Set the `Project ID`, `User Identifier` and `Token Secret` credentials found in the Smartling Dashboard for the project created for WordPress.
1. Select the Default Locale and the target locales for the translated sites you are creating.

== Frequently Asked Questions ==

Additional information on the Smartling Connector for WordPress can be found [here](https://help.smartling.com/v1.0/docs/wordpress-connector/).

== Screenshots ==

1. Submit posts for translation using the Smartling Post Widget directly from the Edit page. View translation status for languages that have already been requested.
2. The Bulk Submit page allows submission of Post, Page, Category, Tag, Navigation Menu and Theme Widget text for all configured locales from a single interface.
3. Track translation status within WordPress from the Submissions Board. View overall progress of submitted translation requests as well as resend updated content.

== Changelog ==
= 1.11.2 =
* Fixed issue with download button at post edit page.

= 1.11.1 =
* Updated 3rd party libraries.

= 1.11.0 =
* Added extension to manage shortcodes and filters to be handled by smartling-connector.

= 1.10.16 =
* Improved ACF Pro plugin support.
* Improved background tasks.

= 1.10.15 =
* Fixed possible issue when shortcodes are not re-build correctly.
* Fixed possible issue when several same-name shortcodes have same-name attributes with different translation
* Fixed Gutenberg detection

= 1.10.14 =
* Fixed possible issue when shortcodes are not processed correctly if they are not registered in admin-panel

= 1.10.13 =
* Fixed possible issue when window.React is present not only on Gutenberg Edit page, but on all Admin pages and makes Bulk Submit page unusable.
* Fixed possible issue when permanently removed translation is re-sent for translation after original content is changed and auto-resubmit function is enabled.
* Fixed possible issue of unhandled Exception on BulkSubmit page when default 'post' content-type is disabled.

= 1.10.12 =
* Fixed possible unhandled exception when translation is manually edited and saved.

= 1.10.11 =
* Fixed possible issue when permanently deleted content may appear again.
* Fixed possible issue when disabled profile locales might be re-send for translation.
* Minor fixes

= 1.10.10 =
* Downgraded dependency library to avoid possible issue when upload widget is frozen.

= 1.10.9 =
* Added yst_prominent_words term to ignore filter
* Added ability to set via API post types and term types to ignore filter that will not be sent for translation.

= 1.10.8 =
* Fixed JS naming conflict which broke ACF DateTime field functionality
* Fixed possible issue when multiple terms are deleted

= 1.10.7 =
* Fixed possible UI freeze on Gutenberg post edit page
* Fixed possible minor UI issues

= 1.10.6 =
* Changed minimum required PHP version to 5.6
* Fixed possible issue when ACF configuration is not handled correctly for ACF Pro 5.7.12+
* Improved security on AJAX requests
* Improved total plugin stability

= 1.10.5 =
* Fixed UI issue when Translation Lock block is not displayed when using Gutenberg editor.
* Fixed UI issue when Content upload widget is not displayed when using Gutenberg editor.
* Fixed possible issue when post metadata has several values for single meta_key. Last value is used.
* Known issue: Gutenberg (when installed as plugin) build-in UI js library conflicts with smartling-connector Upload widget when using Gutenberg editor.

= 1.10.4 =
* Fixed possible issue when submission has not valid value for Post-based content-type
* Removed old way to re-send content to Smartling on download
* UI improvement: Disabled translation edit link on Smartling download widget for submissions without existent translation
* UI improvement: Disabled checkbox on download widget for submissions in 'New' state
* Known issie: Due to API change upload widget is not displayed on content edit screen in WP 5+
* Known issue: Due to API change translation lock UI is not accessible in WP 5+

= 1.10.3 =
* Fixed possible issue when wordpress api returns unexpected result while saving post
* Fixed support for ACF pro for version 5.7.11+
* Known issie: Due to API change upload widget is not displayed on content edit screen in WP 5+
* Known issue: Due to API change translation lock UI is not accessible in WP 5+

= 1.10.2 =
* Fixed possible issue when last version of Gutenberg plugin breaks connector functionality.

= 1.10.1 =

**Manual steps are required after plugin is updated. Please read and follow steps below:**
1. **Open `Smartling` -> `Settings page`**
2. **Click `Show Expert Settings`**
3. **Click `reset to defaults` in `Logging Customization` section**
4. **Click `Apply changes`**

* Fixed possible issue when content cannot be uploaded to Smartling for translation.
* Fixed `Download` button smartling download widget when Gutenberg editor is used.
* Known issue: ACF version 5.7.11+  is not supported yet

= 1.10.0 =
* Added support for Gutenberg posts. Now smartling-connector supports Gutenberg blocks in posts.
* Fixed possible issue when a duplicate of post is created on content upload.
* Known issue: ACF version 5.7.11+  is not supported yet
* Known issie: Download button on Smartling widget on content edit page may not work as expected if Gutenberg editor is used.

= 1.9.3 =
* Fixed possible deprecation warning when running at PHP 7.3
* Fixed possible issue when Job description overwrites taxonomy description.

= 1.9.2 =
* Fixed possible issue when content submit dialog may be shown to users without required capabilities.
* Minor stability updates.

= 1.9.1 =
* Fixed possible issue when cloning content.
* Fixed possible issue when shortcodes are used.

= 1.9.0 =
* Multilingual Press free plugin is no longer required, but is recommended for better site naming.
* Improved background cron jobs to be thread-safe for multi-instance installation.

= 1.8.6 =
* Improved internal API for better integration

= 1.8.5 =
* Fixed possible issue with sending content for translation.
* Small fixes and improvements.

= 1.8.4 =
* Fixed possible issue with content update detection.
* Small fixes and improvements.

= 1.8.3 =
* Improved UI speed while adding content for translation from content edit screen.
* Improved Jod fields validation to fix possible unhandled exception error.
* Fixed help link for install and configure article.

= 1.8.2 =
* Fixed download widget design issue

= 1.8.1 =
* Improved Translation Progress screen filters and UI
* Improved Smartling Download widget
* Fixed compatibility issue with `folders` plugin

= 1.8.0 =
* Improved translation Lock feature: added ability to lock separate fields.

= 1.7.9 =

**This version may require manual actions be performed BEFORE updating. Please read and following steps below:**

* **If Smartling Cloud Log plugin is installed and active - deactivate and remove Smartling Cloud Log plugin.**
* **Upgrade smartling-connector.**

**Improvements**:

* Improved Upload functionality to avoid possible issue when upload takes more than 20 minutes.
* Integrated cloud logging functionality.

= 1.7.8 =
* Fixed possible issue when expert filtering settings may not be saved.
* Improved Download widget to allow check checkboxes for locales that have been sent for translation.
* Improved stability

= 1.7.7 =
* Updated SDK - fixed possible issue when wrong credentials are used.

= 1.7.6 =
* Fixed possible issue when new category may be not assigned to translation after download.
* Improved support of ACF plugin. Added support of fields with `clone` type.
* Added `Test Connection` button on Configuration profile creation (edit) screen.

= 1.7.5 =
* Moved to new version of Guzzle library.

= 1.7.4 =
* Fixed possible issue when cloned post is sent for translation instead of cloning.
* Fixed possible issue when taxonomies are not handled during cloning.

= 1.7.3 =
* Fixed logging.
* Updated PHP-SDK to improve long operations.
* Fixed issue when new created site could have cloned content from original site.

= 1.7.2 =
* Fixed possible issue when PHP yaml extension is not installed and enabled.
* Small updates and improvements

= 1.7.1 =
* Improved Upload cron task to speed up upload of related content.
* Optimized database requests for `Settings` page. Fixed possible issue when plugin ran out of memory with large amount of submissions.
* Updated `Bulk Submit` page to have two tabs: `Translate` and `Clone`.
* Added optional profile option `Clone attachments` that forces attachments to be cloned always if enabled.
* Improved Job Widget UI.
* Improved Translation Locales list in Job Widget to display locales in several columns if possible.

= 1.7.0 =
* Moved to Translation Jobs
* Added ability to manually disable ACF plugin support.
* Dropped support of a free ACF plugin.
* Optimized database requests related to ACF plugin support
* Improved logging subsystem

= 1.6.12 =
* Added ability to disable automatic ACF configuration lookup.

= 1.6.12 =
* Fixed possible notice
* Optimized database query.

= 1.6.11 =
* Fixed possible incorrect filter selection when fields names partially match.

= 1.6.10 =
* Improved functionality that works with temporary files.
* Fixed issue when image file could be not copied to translation site.
* Fixed possible issue when some submissions or XML file may not be removed while translation is removed.

= 1.6.9 =
* Reverted additional logging and validations for temporary files.

= 1.6.8 =
* Fixed possible issue when Translation Lock functionality is broken.
* Fixed possible issue when Cloning functionality is broken.
* Improved functionality that works with temporary files.
* Added automatic detection of post type if delete_post(, true) is used for proper handling of before_delete_post hook

= 1.6.7 =
* Fixed possible fatal error when non standard ACF plugin field type is used.

= 1.6.6 =
* Fixed possible fatal error related to missing class DiagnosticsHelper fixed.

= 1.6.5 =
* Improved Translation Lock functionality to protect content from Upload and Download.
* Fixed issue when deletion of translation may leave smartling-submission.

= 1.6.4 =

**Note, that [Smartling ACF localization](https://wordpress.org/plugins/smartling-acf-localization) plugin is not supported anymore. Now it is a part of Smartling-connector plugin.**

**This version may require manual migration steps from previous versions. Please read and following steps below:**

* **If [Smartling ACF localization](https://wordpress.org/plugins/smartling-acf-localization) plugin is used then deactivate Smartling-connector plugin *BEFORE* upgrading to avoid Smartling-connector cron jobs execution.**
* **If [Smartling ACF localization](https://wordpress.org/plugins/smartling-acf-localization) plugin is used then deactivate it.**
* **Upgrade smartling-connector.**
* **If [Smartling ACF localization](https://wordpress.org/plugins/smartling-acf-localization) plugin was used - remove it.**

**Improvements**:

* Fixed possible issue when ACF image types become broken.
* Added ACL Localization plugin as a part of smartling-connector plugin to fix possible issue when translation becomes broken if ACF Localization plugin is disabled or removed.
* Fixed minor CSS issue when css file was loaded twice.
* Added support for Wordpress 4.9

= 1.6.3 =
* Fixed possible notice issue on post-edit page smartling widget.

= 1.6.2 =
* Fixed issue if site doesn't have `administrator` role.
* Fixed title for `Download` button.
* Fixed issue when download cron crashes on any download error.
* Added ability to reinstall custom cron jobs automatically.
* Added links to translation edit page on translation widget (edit page).

= 1.6.1 =
* Improved upload to smartling flow when post doesn't have any revision. Before it required to submit post 2 times (the 1st submit created revision, the 2nd submitted post to smartling). Now it works seamles, you should not work does post have revisions or no
* Fixed default value of page size configuration for existing configurations

= 1.6.0 =

**This version may require manual migration steps from previous versions. Please read and following steps below:**

* **If [Smartling ACF localization](https://wordpress.org/plugins/smartling-acf-localization) plugin is used then it must be updated first.**
* **Disable all custom plugins than extend Smartling Connector before update connector. An example, custom plugins that add support of custom content. Note that signaure of `EntityAbstract.getAll()` method was changed and custom code should be updated.**

**Improvements**:

* Improved search on Translation Progress Screen.
* Fixed locale display on Translation Progress Screen.
* Added ability to search on Bulk Submit Screen.
* Added ability to change amount of displayed rows per page on smartling-connector screens.

= 1.5.11 =
* Fixed issue with categories. Category is not populated from original posts to all target posts if you submit an original post for more than one locale.
* Fixed issue with images synchronization between sites. In some cases, images are not updated on target sites.

= 1.5.10 =
* Fixed potential issue of plugin crash if `logs` folder is not accessible for writing.
* Added the new option in the Smartling settings. Now you can turn logging off, or you can change where to store log file.

= 1.5.9 =
* Added ability to skip some self-checks (Settings screen)
* Added ability to sync media files (Configuration profile expert settings) to fix possible issues when source media is replaced (even without changing file name).
* Improved post deletion handling to remove related submissions.

= 1.5.8 =
* Improved environment self-test
* Added notification about new `smartling-connector` plugin releases.
* Added ability to describe related taxonomies using general type `'taxonomy'` or `'term'` instead of using internal wordpress names (`'category'`, `'post_tag'`)

= 1.5.7 =
* Improved implementation of `Clone` functionality.
* Moved `Clone` functionality to Bulk Submit page.
* Fixed changes detection.

= 1.5.6 =
* Fixed 'Call to undefined method' potential issue

= 1.5.5 =
* Fixed 'Call to undefined method' potential issue

= 1.5.4 =
* Added missing dependencies.

= 1.5.3 =
* Improved DOM error handling.
* Fixed Translation Progress screen search.
* Fixed possible issue when all strings are excluded from translation.
* Fixes possible issue when wordpress is installed into subfolder.
* Small improvements

= 1.5.2 =
* Added automatic support for all registered taxonomies. Previously needed taxonomy descriptors should be removed.
* Added automatic support for all registered public custom post types (including relations with taxonomies). Previously needed custom post types descriptors should be removed.
* Added configuration profile option that allows to keep translation in draft even after translation is completed.
* Added ability to lock / unlock translations on Translation Progress screen via bulk action.
* Added caching to read operations.
* Added caching to hash calculation operations.
* Fixed issue of metadata duplication if value is an array with one element.
* Small improvements

= 1.5.1 =
* Fixed potential issue that triggers `E_NOTICE` while editing post with third-party plugin.
* Fixed potential issue with uncaught exception if previously used content-type was removed from wordpress.
* Removed recovery script generation code
* Small improvements

= 1.5.0 =
* Added filtering for shortcode attributes
* UI improvements for long locale lists
* Small improvements

= 1.4.4 =
* Added filter `smartling_register_custom_taxonomy` to register custom taxonomies handlers, [Usage example](https://github.com/Smartling/wordpress-integration-example/blob/master/src/Declarations/CustomTaxonomies.php).
* Added filter `smartling_register_custom_type` to register custom post types handlers, [Usage example](https://github.com/Smartling/wordpress-integration-example/blob/master/src/Declarations/CustomPostTypes.php).
* Added filter `smartling_register_field_filter` to set content field localization rules, [Usage example](https://github.com/Smartling/wordpress-integration-example/blob/master/src/Declarations/FieldFilters.php).
* Added ability to set localization rules for shortcode attributes
* Added ability to inform connector plugin about shortcodes that are not registered in admin panel.
* Improved memory and cpu usage. Plugin is loaded only for cron jobs and admin page.
* Fixed possible issue that prevents sending post for translation from edit post page
* Fixed issue related to featured image translation
* Fixed issue related to multiple post_save hook handlers
* Released a [integration-example plugin](https://github.com/Smartling/wordpress-integration-example/releases/tag/v.1.2) that demonstrates smartling-connector plugin extension possibilities.

= 1.4.3 =
* Fixed issue with shortcodes if no space between closing and opening shortcode.
* Added filter that allows overwrite pre-defined values for each translation, e.g. url
* Minor updates

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
