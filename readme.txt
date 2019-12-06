=== Upcoming Meetings BMLT ===

Contributors: pjaudiomv
Plugin URI: https://wordpress.org/plugins/upcoming-meetings-bmlt/
Tags: bmlt, basic meeting list toolbox, Upcoming Meetings, Upcoming Meetings BMLT, narcotics anonymous, na
Requires at least: 4.0
Requires PHP: 5.6
Tested up to: 5.3
Stable tag: 1.2.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Upcoming Meetings BMLT is a plugin that displays the next 'N' number of meetings from the current time on your page or in a widget using the upcoming_meetings shortcode.

SHORTCODE
Basic: [upcoming_meetings]
Attributes: root_server, services, recursive, grace_period, num_results, display_type, timezone, location_text, time_format, weekday_language

-- Shortcode parameters can be combined

== Usage ==

A minimum of root_server, services and timezone attributes are required, which would return the next 5 meetings in simple view with a 15minute grace period.

Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; timezone=&quot;America/New_York&quot;]

**recursive** to recurse service bodies add recursive=&quot;1&quot;
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; timezone=&quot;America/New_York&quot; recursive=&quot;1&quot;]

**services** to add multiple service bodies just seperate by a comma.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50,37,26&quot; timezone=&quot;America/New_York&quot;]

**grace_period** To add a grace period to meeting lookup add grace_period=&quot;15&quot; this would add a 15 minute grace period.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; timezone=&quot;America/New_York&quot; grace_period=&quot;15&quot;]

**num_results** To limit the number of results add num_results=&quot;5&quot; this would limit results to 5.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; timezone=&quot;America/New_York&quot; state=&quot;1&quot; num_results=&quot;5&quot;]

**display_type** To change the display type add display_type=&quot;table&quot; there are three different types **simple**, **table**, **block**
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; timezone=&quot;America/New_York&quot; display_type=&quot;table&quot;]

**timezone** This is required and should be set to what timezones your meetings are in, We can not rely on servers time zone. add timezone=&quot;America/New_York&quot; you can set this in the admin setting or short code. A complete list of timezones can be found here http://php.net/manual/en/timezones.php
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; timezone=&quot;America/New_York&quot;]

**location_text** to display the location nam,e using the simple display add location_text=&quot;1&quot;
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; services=&quot;50&quot; timezone=&quot;America/New_York&quot; location_text=&quot;1&quot;]

**time_format** This allows you to be able to switch between 12 and 24 hour. the default is 12. To switch to 24 hour add time_format=&quot;24&quot;
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; timezone=&quot;America/New_York&quot; time_format=&quot;24&quot;]

**weekday_language** This allows you to change the language of the weekday names. To change language to danish set weekday_language=&quot;dk&quot;. Currently supported languages are Danish and English, the default is English.
Ex. [upcoming_meetings root_server=&quot;https://www.domain.org/main_server&quot; timezone=&quot;America/New_York&quot; weekday_language=&quot;dk&quot;]

== EXAMPLES ==

<a href="https://sca.charlestonna.org/upcoming-meetings/">https://sca.charlestonna.org/upcoming-meetings/</a>

<a href="https://sca.charlestonna.org/upcoming-meetings-table/">https://sca.charlestonna.org/upcoming-meetings-table/</a>

<a href="https://sca.charlestonna.org/upcoming-meetings-block/">https://sca.charlestonna.org/upcoming-meetings-block/</a>


== MORE INFORMATION ==

<a href="https://github.com/pjaudiomv/upcoming-meetings-bmlt" target="_blank">https://github.com/pjaudiomv/upcoming-meetings-bmlt</a>


== Installation ==

This section describes how to install the plugin and get it working.

1. Download and install the plugin from WordPress dashboard. You can also upload the entire Upcoming Meetings BMLT Plugin folder to the /wp-content/plugins/ directory
2. Activate the plugin through the Plugins menu in WordPress
3. Add [upcoming_meetings] shortcode to your WordPress page/post.
4. At a minimum assign root_server, services and timezone attributes.

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png
3. screenshot-3.png
4. screenshot-4.png

== Changelog ==

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
