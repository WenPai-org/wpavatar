=== WPAvatar ===
Contributors: wpfanyi
Tags: wpavatar, avatar, wp avatar, 头像, gravatar
Requires at least: 5.4
Tested up to: 6.2
Requires PHP: 5.6
Stable tag: 1.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is free forever, and its purpose is to supplement the essential functions that the Chinese version of WordPress lacks.

== Description ==
### Must-have for WordPress sites in China, showing your ICP license. ###

More information at [https://wpicp.com](https://wpicp.com)


How to use the ICP license:

**1.Shortcode**

You can use the [wpicp_license] shortcode to display ICP anywhere, usually the Ministry of Industry and Information Technology of China requires it to be displayed on the homepage of the website.


**2.Add to footer**

If you need to integrate with your own theme or plugin, you can use do_shortcode() function to add.


== Frequently Asked Questions ==

###Who needs this plugin###

All WordPress websites in China, as well as businesses and individuals who want to do business in China.

###Who are you###

We are the first WordPress agency in China, Welcome to China and nice to meet you.

### Found a bug in this plugin? ###

Please submit issues here: [https://github.com/WenPai-org/wpicp-license/issues](https://github.com/WenPai-org/wpicp-license/issues), we will fix it in the next version, thank you for your feedback!

== Installation ==

Starting with WP ICP License consists of just two steps: installing and setting up the plugin. WP ICP License is designed to work with your site’s specific needs, so don’t forget to go through the WP ICP License configuration wizard as explained in the ‘after activation’ step!

###Install WP ICP License form within WordPress###

1. Visit the plugins page within your dashboard and select ‘Add New’;
2. Search for ‘WPICP License’;
3. Activate WPICP License from your Plugins page;
4. Go to ‘after activation’ below.


###After Activate###

1. Setting Menu: Go to ‘Setting’ => ‘ICP licensen’;
2. Enter the correct ICP Number;
3. Add shortcodes to footer.php or text widgets;
3. You’re done!

== Screenshots ==

1. Setting Page
2. Translation Ready

== Changelog ==


### 1.2 ###

* Add Chinese province shortcodes.
* Use the shortcode [wpicp_province] to display the full province name.
* Use the shortcode [wpicp_p] to display the short name of the province.
* Added ICP license input box to settings page.


### 1.1 ###

* Add China Wangan License(PSB)
* Use ShortCode [wpicp_wangan]

### 1.0.1 ###

* Fix glotpress language pack issue, thanks for reporting by Alex.
* Delete preset language zh_CN/zh_TW.

### 1.0.0 ###

* Released first edition
* Support shortcode method
