Video SEO
=========

Requires at least: 3.2
Tested up to: 3.4  
Stable tag: 1.4

Video SEO adds Video SEO capabilities to WordPress SEO.

Description
-----------

This plugin adds Video XML Sitemaps as well as the necessary OpenGraph markup, Schema.org videoObject markup and mediaRSS for your videos.

Installation
------------

1. Go to Plugins -> Add New.
2. Click "Upload" right underneath "Install Plugins".
3. Upload the zip file that this readme was contained in.
4. Activate the plugin.
5. Go to SEO -> XML Sitemaps and enter your license key.
6. Save settings, your license key will be validated. If all is well, you should now see the XML Video Sitemap settings.
7. Make sure to hit the "Re-index video's" button if you have video's in old posts.

Frequently Asked Questions
--------------------------

You can find the FAQ [online here](http://yoast.com/video-seo-plugin-faq/).

Changelog
=========

1.4
---

* Bugfixes / Enhancements:
    * Fix Vimeo embed detection.
    * Switch Vimeo to oEmbed API.
    * When available, use html5_file for jwplayer embeds.
* New features:
    * Added video content optimization tips in the page analysis tab of WordPress SEO.
    * Added support for WP Video Lightbox plugin.
    * Added initial support for Flowplayer plugin.
    * Added support for Wistia video hosting platform.
    * Added support for Vippy video hosting platform (thanks to Ronald Huereca).

1.3.4
-----

* Bugfixes:
    * Fixed Viddler check.
    * Fix strip tags for videoObject output.
    * Don't filter content when in a feed.
    * Improve parsing of VideoPress embed ID's.
* Enhancements:
    * Added support for checking custom fields for video's.
    * Added support for Press75's Simple Video Embedder (and thus for all their themes).

1.3.3
-----

* Bugfixes:
    * Properly catch thumbnail images when the path is relative instead of absolute.
    * Strip shortcodes for plugins that don't register them properly as well.
    * Prevent empty titles.
    * Wrap XML sitemap and MediaRSS textual content in CDATA tags, this solves about 900.000 issues with encoding.
    * Fixed [Veoh](http://www.veoh.com/) support.
* Enhancements:
    * When a post is in more than one category, the excess categories are now used as tags.
    * Don't print sitemap lines for video's that have no thumbnail and either a content location or a player location.
    * If the description and excerpt are empty, use the title for the description, as an empty description is invalid.
    * Changed the name of the family friendly variable, so it can't go "wrong" with old data.
    * Added support for the `video:uploader` tag. This automatically links to the post authors posts page.
    * Make terms use their own name as category in XML sitemap.
    * Added support for jwplayer shortcode embeds with file and image attributes instead of mediaid.
    * Added support for the [WordPress Video Plugin](http://wordpress.org/extend/plugins/wordpress-video-plugin/).
    * Added support for the [MediaElements.js](http://wordpress.org/extend/plugins/media-element-html5-video-and-audio-player/) plugin.
    * Added support for the [WP YouTube Player](http://wordpress.org/extend/plugins/wp-youtube-player/) plugin.
    * Added support for the [Advanced YouTube Embed Plugin by Embed Plus](http://wordpress.org/extend/plugins/embedplus-for-wordpress/) plugin.
    * Added support for the [VideoJS - HTML5 Video Player for WordPress](http://wordpress.org/extend/plugins/videojs-html5-video-player-for-wordpress/) plugin.
    * Added support for the [YouTube Shortcode](http://wordpress.org/extend/plugins/youtube-shortcode/) plugin.

1.3.2
-----

* Bugfixes:
    * Fix XSLT URL issue, for real this time. Sometimes you have to ignore WordPress internals because they are just
      plain wrong. This is such a time. The path to the XSL file should now always be correct. Note the word "should"
      though.
    * Improve matching of Youtube ID's, apparently those can contain underscores too.
    * Improve reindexation process by running through consecutive loops of 100 posts, to avoid memory issues.
    * Fixed very annoying bug where video's would be mark as non-family-frienldy by default.
    * Force view count to be an integer.
* Enhancements:
    * Switched around the logic for family friendliness. It now assumes all video's are family friendly by default and
      you have to check the box to make it NON family friendly.

1.3.1
-----

* Bugfixes:
    * Prevent relative paths to images
    * Prevent post_id from showing up in XML Video Sitemap
    * Fix wrong URL to XSLT
* Enhancements:
    * Added support for [JW Player Plugin](http://wordpress.org/extend/plugins/jw-player-plugin-for-wordpress/) embeds  (only embeds with `mediaid=<number>` will work for now).

1.3
---

* Bugfixes:
    * Even more YouTube embed fixes, also fixes empty Youtube ID issue.
    * Properly grab thumbnail from YouTube instead of "assuming" a URL.
    * Improve code that grabs duration from YouTube API.
* Enhancements:
    * Add support for searching through category / tag / term descriptions for video content.
    * Get viewcount from YouTube API.
    * Add option to hide sitemap from everyone except admins and Googlebot.
    * Add option to disable the video integration on a single post and page by adding a checkbox on the Video tab.
    * Changed the way reindex gets called, so the admin keeps working immediately after a reindex without a refresh.
    * Added option to force reindexation of old posts that have already been indexed as having video (normally
      they're just refreshed but no external calls are being done).

1.2.2
-----

* Bugfixes:
    * Properly work with [youtube]video-id[/youtube] type embed shortcodes.
* Enhancements:
    * Option to only show the XML video sitemap to admins and to googlebot, not to any other visitors. This prevents
      other visitors from downloading your video files.

1.2.1
-----

* Bugfixes:
    * Properly works with index.php URLs.
    * Sends right URL for video sitemap on Google ping at all times.
    * Correctly clean up video descriptions & tags for display in the XML sitemap.
* Enhancements:
    * Added support for Smart Youtube Pro.
    * Added support for Viddler iframe embeds.
    * Added support for youtu.be oEmbeds.
    * Preliminary Brightcove support.

1.2
---

* The Video tab in the meta box now works, so you can change the preview image.
* The plugin now adds full support for the videoObject schema.
* Several fixes to video recognition, especially for youtube iframe embeds, be sure to click re-index on the Video SEO page if you have those.

1.1
---

* This version should work better on activation.
* The plugin settings are now moved into its own SEO -> Video SEO admin page and out of the XML Sitemaps page.
* The plugin now recognizes youtube and vimeo embeds with an object tag or an iframe, to use this just click reindex video's.
* Improved the snippet preview date display.
* Fixed a few notices.

1.0
---

* Initial version

0.2
---

* First private beta release