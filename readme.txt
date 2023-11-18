=== Upcoming Meetings BMLT ===

Contributors: pjaudiomv, bmltenabled
Plugin URI: https://wordpress.org/plugins/upcoming-meetings-bmlt/
Tags: bmlt, basic meeting list toolbox, Upcoming Meetings, Upcoming Meetings BMLT, narcotics anonymous, na
Requires at least: 4.0
Requires PHP: 8.0
Tested up to: 6.4.0
Stable tag: 1.5.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Upcoming Meetings BMLT is a plugin that displays the next 'N' number of meetings from the current time on your page or in a widget using the upcoming_meetings shortcode.

SHORTCODE
Basic: [upcoming_meetings]
Attributes: root_server, services, recursive, grace_period, num_results, display_type, timezone, location_text, time_format, weekday_language, custom_query

-- Shortcode parameters can be combined

== Usage ==

A minimum of root_server, and services attributes are required, which would return the next 5 meetings in simple view with a 15minute grace period.

Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot;]

**recursive** to recurse service bodies add recursive=&quot;1&quot;
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; recursive=&quot;1&quot;]

**services** to add multiple service bodies just seperate by a comma.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50,37,26&quot;]

**grace_period** To add a grace period to meeting lookup add grace_period=&quot;15&quot; this would add a 15 minute grace period.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; grace_period=&quot;15&quot;]

**num_results** To limit the number of results add num_results=&quot;5&quot; this would limit results to 5.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; state=&quot;1&quot; num_results=&quot;5&quot;]

**display_type** To change the display type add display_type=&quot;table&quot; there are three different types **simple**, **table**, **block**
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; display_type=&quot;table&quot;]

**timezone** By default we use your WordPress sites timezone setting, this will overwrite that. add timezone=&quot;America/New_York&quot; you can set this in the admin setting or short code. A complete list of timezones can be found here http://php.net/manual/en/timezones.php
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; timezone=&quot;America/New_York&quot;]

**location_text** to display the location nam,e using the simple display add location_text=&quot;1&quot;
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; location_text=&quot;1&quot;]

**show_header** to display header info for Table/Block display add show_header=&quot;1&quot;
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; show_header=&quot;1&quot;]

**time_format** This allows you to be able to switch between 12 and 24 hour. the default is 12. To switch to 24 hour add time_format=&quot;24&quot;
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; time_format=&quot;24&quot;]

**weekday_language** This allows you to change the language of the weekday names. To change language to danish set weekday_language=&quot;dk&quot;. Currently supported languages are da,de,en,es,fa,fr,it,pl,pt,ru,sv, the default is English.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; weekday_language=&quot;dk&quot;]

**custom_query** You can add a custom query from semantic api to filter results, for ex by format `&formats=54`.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; custom_query=&quot;&formats=54&quot;]

== EXAMPLES ==

<a href="https://www.southcoastalna.org/">https://www.southcoastalna.org/</a>


== MORE INFORMATION ==

<a href="https://github.com/bmlt-enabled/upcoming-meetings-bmlt" target="_blank">https://github.com/bmlt-enabled/upcoming-meetings-bmlt</a>


== Installation ==

This section describes how to install the plugin and get it working.

1. Download and install the plugin from WordPress dashboard. You can also upload the entire Upcoming Meetings BMLT Plugin folder to the /wp-content/plugins/ directory
2. Activate the plugin through the Plugins menu in WordPress
3. Add [upcoming_meetings] shortcode to your WordPress page/post.
4. At a minimum assign root_server, and services attributes.

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png
3. screenshot-3.png
4. screenshot-4.png

== Changelog ==

= 1.5.2 =

* Fix for default to wp system timezone.

= 1.5.1 =

* Fix for TimeZones not being validated properly and set default.

= 1.5.0 =

* Now shows virtual meeting info in table/block html display types.
* Added option to show header info for Table/Block display.
* Added support for all standard BMLT languages.
* Refactored codebase.

= 1.4.2 =

* Fix various PHP warnings.

= 1.4.1 =

* Fix for User-Agent issue that appears to be present on SiteGround hosted root servers.

= 1.4.0 =

* Updated version logic for BMLT 3.0.0 compatibility.

= 1.3.7 =

* Updated venue type logic.

= 1.3.6 =

* Removed unneeded link to additional info field.

= 1.3.5 =

* Expanded support for virtual meeting additional info field to html and table view.

= 1.3.4 =

* Added support for virtual meeting additional info field.

= 1.3.3 =
* Updated to include dial in phone number. If marked as TC it will not return the result. If marked as TC and VM it will not return the address information, just virtual links. If just VM it will append virtual links to the result along with physical location information.

= 1.3.2 =

* Added basic support for virtual meetings, if virtual_meeting_link field is field in the map link will be replaced with contents of virtual_meeting_link.

= 1.3.1 =

* If location_info contains a url it will automatically get turned into a link.

= 1.3.0 =

* Added option to specify a custom query.

= 1.2.6 =

* Fix to better comply with WordPress best practices.

= 1.2.5 =

* Now defaults timezone to WordPRess settings if not set.

= 1.2.4 =

* Added divider between meetings for simple layout.

= 1.2.3 =

* Added weekday_language support for Danish.

= 1.2.2 =

* Added time_format option to be able to change between 12 or 24 hour.

= 1.2.1 =

* Update WordPress compatibility.

= 1.2.0 =

* Added option to display the location name for the simple display.

= 1.1.3 =

* Fix for php warnings.

= 1.1.2 =

* Code cleanup.

= 1.1.1 =

* Grace period should be subtracting not added.

= 1.1.0 =

* Adjustments for defaults and added custom css box in admin.

= 1.0.0 =

* Initial WordPress submission.
