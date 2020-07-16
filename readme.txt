=== Smartling Connector ===
Contributors: smartling
Tags: translation, localization, localisation, translate, multilingual, smartling, internationalization, internationalisation, automation, international
Requires at least: 4.6
Tested up to: 5.4
Stable tag: 1.13.2
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
 * `json`
* For wpengine hosting maximum execution time should be set to 300 seconds.


1. Configure [Multilingual Press](https://wordpress.org/plugins/multilingual-press) using instructions found [here](https://help.smartling.com/docs/wordpress-connector-install-and-configure).
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
= 1.13.2 =
* Further improved support for Gutenberg blocks
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
