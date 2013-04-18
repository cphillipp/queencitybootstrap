<?php
/*
Plugin Name: Video SEO for WordPress SEO by Yoast
Version: 1.4
Plugin URI: http://yoast.com/wordpress/video-seo/
Description: This Video SEO module adds all needed meta data and XML Video sitemap capabalities to the metadata capabilities of WordPress SEO to fully optimize your site for video results in the search results.
Author: Joost de Valk
Author URI: http://yoast.com

Copyright 2012 Joost de Valk (email: joost@yoast.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// retrieve our license key from the DB
$options = get_option( 'wpseo_video' );

if ( !is_array( $options ) ) {
	$options = get_option( 'wpseo_xml' );

	$options['dbversion'] = 1;

	update_option( 'wpseo_video', $options );
}

if ( isset( $options['yoast-video-seo-license'] ) && !empty( $options['yoast-video-seo-license'] ) ) {
	if ( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
		// load our custom updater
		include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
	}

	$edd_updater = new EDD_SL_Plugin_Updater( 'http://yoast.com', __FILE__, array(
			'version'   => '1.4', // current version number
			'license'   => trim( $options['yoast-video-seo-license'] ), // license key
			'item_name' => 'Video SEO for WordPress', // name of this plugin in the Easy Digital Downloads system
			'author'    => 'Joost de Valk' // author of this plugin
		)
	);
}
/**
 * All functionality for fetching video data and creating an XML video sitemap with it.
 *
 * @link       http://codex.wordpress.org/oEmbed oEmbed Codex Article
 * @link       http://oembed.com/ oEmbed Homepage
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Video
 */

/**
 * wpseo_video_Video_Sitemap class.
 *
 * @package WordPress SEO Video
 * @since   0.1
 */
class wpseo_Video_Sitemap {

	/**
	 * Constructor for the wpseo_video_Video_Sitemap class.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		$options = get_option( 'wpseo_video' );

		$this->set_defaults();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'init' ) );

		if ( is_admin() ) {
			add_action( 'update_option_wpseo_video', array( $this, 'activate_license' ) );

			add_action( 'admin_init', array( $this, 'options_init' ) );
			add_action( 'admin_menu', array( $this, 'register_settings_page' ) );

			if ( isset( $options['yoast-video-seo-license'] ) && isset( $options['yoast-video-seo-license-status'] )
				&& $options['yoast-video-seo-license-status'] == 'valid'
			) {
				add_action( 'save_post', array( $this, 'update_video_post_meta' ) );

				add_filter( 'wpseo_save_metaboxes', array( $this, 'save_meta_boxes' ), 10, 1 );

				add_action( 'wpseo_tab_header', array( $this, 'tab_header' ) );
				add_action( 'wpseo_tab_content', array( $this, 'tab_content' ) );

				add_filter( 'wpseo_snippet', array( $this, 'snippet_preview' ), 10, 3 );
				add_filter( 'wpseo_metadesc_length', array( $this, 'meta_length' ), 10, 1 );
				add_filter( 'wpseo_metadesc_length_reason', array( $this, 'meta_length_reason' ), 10, 1 );

				add_filter( 'wpseo_linkdex_results', array( $this, 'filter_linkdex_results' ), 10, 3 );
			}
		} else {
			if ( isset( $options['yoast-video-seo-license'] ) && isset( $options['yoast-video-seo-license-status'] )
				&& $options['yoast-video-seo-license-status'] == 'valid'
			) {
				// OpenGraph
				add_action( 'wpseo_opengraph', array( $this, 'opengraph' ) );
				add_filter( 'wpseo_opengraph_type', array( $this, 'opengraph_type' ), 10, 1 );
				add_filter( 'wpseo_opengraph_image', array( $this, 'opengraph_image' ), 10, 1 );

				// XML Sitemap Index addition
				add_filter( 'wpseo_sitemap_index', array( $this, 'add_to_index' ) );

				// Content filter for non-detected video's
				add_filter( 'the_content', array( $this, 'content_filter' ), 5, 1 );

				// MRSS
				add_action( 'rss2_ns', array( $this, 'mrss_namespace' ) );
				add_action( 'rss2_item', array( $this, 'mrss_item' ), 10, 1 );
				add_filter( 'mrss_media', array( $this, 'mrss_add_video' ) );
			}
		}
	}

	function options_init() {
		register_setting( 'yoast_wpseo_video_options', 'wpseo_video' );
	}

	/**
	 * Register the Video SEO submenu.
	 */
	function register_settings_page() {
		add_submenu_page( 'wpseo_dashboard', __( 'Video SEO', 'wordpress-seo' ), __( 'Video SEO', 'wordpress-seo' ),
			'manage_options', 'wpseo_video', array( $this, 'admin_panel' ) );
	}

	/**
	 * See if there's a license to activate
	 *
	 * @since 1.0
	 */
	function activate_license() {
		$options = get_option( 'wpseo_video' );

		if ( !isset( $options['yoast-video-seo-license'] ) || empty( $options['yoast-video-seo-license'] ) ) {
			unset( $options['yoast-video-seo-license'] );
			unset( $options['yoast-video-seo-license-status'] );
			update_option( 'wpseo_video', $options );
		} else if ( isset( $options['yoast-video-seo-license'] ) && !empty( $options['yoast-video-seo-license'] ) ) {

			// data to send in our API request
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $options['yoast-video-seo-license'],
				'item_name'  => urlencode( 'Video SEO for WordPress' ) // the name of our product in EDD
			);

			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, 'http://yoast.com' ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) ) {
				return;
			}

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "valid" or "invalid"

			$options['yoast-video-seo-license-status'] = $license_data->license;
			update_option( 'wpseo_video', $options );
		}
	}

	/**
	 * Adds the rewrite for the video XML sitemap
	 *
	 * @since 0.1
	 */
	public function init() {
		// Add oEmbed providers
		$this->add_oembed();

		// Register the sitemap
		if ( isset( $GLOBALS['wpseo_sitemaps'] ) )
			$GLOBALS['wpseo_sitemaps']->register_sitemap( 'video', array( $this, 'build_video_sitemap' ) );
	}

	/**
	 * Register defaults for the video sitemap
	 *
	 * @since 0.2
	 */
	function set_defaults() {
		$options = get_option( 'wpseo_video' );

		if ( !isset( $options['videositemap_posttypes'] ) ) {
			$options['videositemap_posttypes'] = array( 'post', 'page' );
		}
	}

	/**
	 * Return the Video Sitemap URL
	 *
	 * @since 1.2.1
	 *
	 * @return string The URL to the video Sitemap.
	 */
	function sitemap_url() {
		$base = $GLOBALS['wp_rewrite']->using_index_permalinks() ? 'index.php/' : '';
		return home_url( $base . 'video-sitemap.xml' );
	}

	/**
	 * Adds the video XML sitemap to the Index Sitemap.
	 *
	 * @since  0.1
	 *
	 * @param string $str String with the filtered additions to the index sitemap in it.
	 * @return string $str String with the Video XML sitemap additions to the index sitemap in it.
	 */
	public function add_to_index( $str ) {
		$date = get_option( 'wpseo_video_xml_update' );

		if ( !$date || $date == '' ) {
			$date = date( 'c' );
		}

		$str .= '<sitemap>' . "\n";
		$str .= '<loc>' . $this->sitemap_url() . '</loc>' . "\n";
		$str .= '<lastmod>' . $date . '</lastmod>' . "\n";
		$str .= '</sitemap>' . "\n";
		return $str;
	}

	/**
	 * Pings Google with the (presumeably updated) Video Sitemap.
	 *
	 * @since 0.1
	 */
	private function ping() {
		wp_remote_get( 'http://www.google.com/webmasters/tools/ping?sitemap=' . urlencode( $this->sitemap_url() ) );
	}

	/**
	 * Updates the last update time transient for the video sitemap and pings Google with the sitemap.
	 *
	 * @since 0.1
	 */
	private function update_sitemap() {
		update_option( 'wpseo_video_xml_update', date( 'c' ) );
		$this->ping();
	}

	/**
	 * Adds oembed endpoints for supported video platforms that are not supported by core.
	 *
	 * @since 1.3.5
	 */
	function add_oembed() {
		wp_oembed_add_provider( '/https?:\/\/(.+)?(wistia\.com|wi\.st)\/(medias|embed)\/.*/', 'http://fast.wistia.com/oembed', true );
	}

	/**
	 * Add the MRSS namespace to the RSS feed.
	 *
	 * @since 0.1
	 */
	public function mrss_namespace() {
		echo 'xmlns:media="http://search.yahoo.com/mrss/"';
	}

	/**
	 * Add the MRSS info to the feed
	 *
	 * Based upon the MRSS plugin developed by Andy Skelton
	 *
	 * @since     0.1
	 * @copyright Andy Skelton
	 */
	public function mrss_item() {
		global $mrss_gallery_lookup;
		$media = array();

		// Honor the feed settings. Don't include any media that isn't in the feed.
		if ( get_option( 'rss_use_excerpt' ) || !strlen( get_the_content() ) ) {
			$content = the_excerpt_rss();
		} else {
			// If any galleries are processed, we need to capture the attachment IDs.
			add_filter( 'wp_get_attachment_link', 'mrss_gallery_lookup', 10, 5 );
			$content = apply_filters( 'the_content', get_the_content() );
			remove_filter( 'wp_get_attachment_link', 'mrss_gallery_lookup', 10, 5 );
			$lookup = $mrss_gallery_lookup;
			unset( $mrss_gallery_lookup );
		}

		// img tags
		$images = 0;
		if ( preg_match_all( '|<img ([^>]+)|', $content, $matches ) ) {
			foreach ( $matches[1] as $attrs ) {
				$item = $img = array();
				// Construct $img array from <img> attributes
				foreach ( wp_kses_hair( $attrs, array( 'http' ) ) as $attr )
					$img[$attr['name']] = $attr['value'];
				if ( !isset( $img['src'] ) )
					continue;
				$img['src'] = $this->mrss_url( $img['src'] );
				// Skip emoticons
				if ( isset( $img['class'] ) && false !== strpos( $img['class'], 'wp-smiley' ) )
					continue;
				$id = false;
				if ( isset( $lookup[$img['src']] ) ) {
					$id = $lookup[$img['src']];
				} elseif ( isset( $img['class'] ) && preg_match( '/wp-image-(\d+)/', $img['class'], $match ) ) {
					$id = $match[1];
				}
				if ( $id ) {
					// It's an attachment, so we will get the URLs, title, and description from functions
					$attachment =& get_post( $id );
					$src        = wp_get_attachment_image_src( $id, 'full' );
					if ( !empty( $src[0] ) )
						$img['src'] = $src[0];
					$thumbnail = wp_get_attachment_image_src( $id, 'thumbnail' );
					if ( !empty( $thumbnail[0] ) && $thumbnail[0] != $img['src'] )
						$img['thumbnail'] = $thumbnail[0];
					$title = get_the_title( $id );
					if ( !empty( $title ) )
						$img['title'] = trim( $title );
					if ( !empty( $attachment->post_excerpt ) )
						$img['description'] = trim( $attachment->post_excerpt );
				}
				// If this is the first image in the markup, make it the post thumbnail
				if ( ++$images == 1 ) {
					if ( isset( $img['thumbnail'] ) )
						$media[]['thumbnail']['attr']['url'] = $img['thumbnail'];
					else
						$media[]['thumbnail']['attr']['url'] = $img['src'];
				}

				$item['content']['attr']['url']    = $img['src'];
				$item['content']['attr']['medium'] = 'image';
				if ( !empty( $img['title'] ) ) {
					$item['content']['children']['title']['attr']['type'] = 'html';
					$item['content']['children']['title']['children'][]   = $img['title'];
				} elseif ( !empty( $img['alt'] ) ) {
					$item['content']['children']['title']['attr']['type'] = 'html';
					$item['content']['children']['title']['children'][]   = $img['alt'];
				}
				if ( !empty( $img['description'] ) ) {
					$item['content']['children']['description']['attr']['type'] = 'html';
					$item['content']['children']['description']['children'][]   = $img['description'];
				}
				if ( !empty( $img['thumbnail'] ) )
					$item['content']['children']['thumbnail']['attr']['url'] = $img['thumbnail'];
				$media[] = $item;
			}
		}

		$media = apply_filters( 'mrss_media', $media );
		$this->mrss_print( $media );
	}

	private function mrss_url( $url ) {
		if ( preg_match( '!^https?://!', $url ) )
			return $url;
		if ( $url{0} == '/' )
			return rtrim( home_url(), '/' ) . $url;
		return home_url() . $url;
	}

	private function mrss_gallery_lookup( $link, $id ) {
		global $mrss_gallery_lookup;
		preg_match( '/ src="(.*?)"/', $link, $matches );
		$mrss_gallery_lookup[$matches[1]] = $id;
		return $link;
	}

	private function mrss_print( $media ) {
		if ( !empty( $media ) )
			foreach ( $media as $element )
				$this->mrss_print_element( $element );
		echo "\n";
	}

	private function mrss_print_element( $element, $indent = 2 ) {
		echo "\n";
		foreach ( $element as $name => $data ) {
			echo str_repeat( "\t", $indent ) . "<media:$name";
			$cdata = false;
			if ( $name == 'title' || $name == 'description' )
				$cdata = true;
			if ( !empty( $data['attr'] ) ) {
				foreach ( $data['attr'] as $attr => $value )
					echo " $attr=\"" . esc_attr( ent2ncr( $value ) ) . "\"";
			}
			if ( !empty( $data['children'] ) ) {
				$nl = false;
				echo ">";
				foreach ( $data['children'] as $_name => $_data ) {
					if ( is_int( $_name ) ) {
						if ( $cdata )
							echo '<![CDATA[';
						echo ent2ncr( esc_html( $_data ) );
						if ( $cdata )
							echo ']]>';
					} else {
						$nl = true;
						$this->mrss_print_element( array( $_name => $_data ), $indent + 1 );
					}
				}
				if ( $nl )
					echo "\n" . str_repeat( "\t", $indent );
				echo "</media:$name>";
			} else {
				echo " />";
			}
		}
	}

	/**
	 * Add the video output to the MRSS feed.
	 *
	 * @since 0.1
	 */
	public function mrss_add_video( $media ) {
		global $post;

		$video = wpseo_get_value( 'video_meta', $post->ID );

		if ( !$video || $video == 'none' )
			return $media;

		$item['content']['attr']['url']                             = $video['player_loc'];
		$item['content']['attr']['duration']                        = $video['duration'];
		$item['content']['children']['player']['attr']['url']       = $video['player_loc'];
		$item['content']['children']['title']['attr']['type']       = 'html';
		$item['content']['children']['title']['children'][]         = $video['title'];
		$item['content']['children']['description']['attr']['type'] = 'html';
		$item['content']['children']['description']['children'][]   = $video['description'];
		$item['content']['children']['thumbnail']['attr']['url']    = $video['thumbnail_loc'];
		$item['content']['children']['keywords']['children'][]      = implode( ',', $video['tag'] );
		array_unshift( $media, $item );

		return $media;
	}

	/**
	 * Downloads an externally hosted thumbnail image to the local server
	 *
	 * @since 0.1
	 *
	 * @param string  $url        The remote URL of the image.
	 * @param string  $vid        Array with the video data
	 *
	 * @return string $img[0] The link to the now locally hosted image.
	 */
	private function make_image_local( $url, $vid ) {

		if ( isset( $vid['post_id'] ) ) {
			$att = get_posts( array(
				'numberposts' => 1,
				'post_type'   => 'attachment',
				'meta_key'    => 'wpseo_video_id',
				'meta_value'  => isset( $vid['id'] ) ? $vid['id'] : '',
				'post_parent' => $vid['post_id'],
				'fields'      => 'ids'
			) );

			if ( count( $att ) > 0 ) {
				$img = wp_get_attachment_image_src( $att[0], 'medium' );

				if ( strpos( $img[0], 'http' ) !== 0 )
					$img = get_site_url( null, $img[0] );
				else
					$img = $img[0];

				return $img;
			}

		}

		// Disable wp smush.it to speed up the process
		remove_filter( 'wp_generate_attachment_metadata', 'wp_smushit_resize_from_meta_data' );

		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return false;
		} else {
			preg_match( '/[^\?]+\.(jpg|jpe|jpeg|gif|png)(\?.*)?/i', $url, $matches );

			$title      = sanitize_title( strtolower( $vid['title'] ) );
			$file_array = array(
				'name'     => sanitize_file_name( preg_replace( '/[^a-z0-9\s\-_]/i', '', $title ) ) . '.' . strtolower( $matches[1] ),
				'tmp_name' => $tmp
			);

			if ( isset( $vid['post_id'] ) && !defined( 'WPSEO_VIDEO_NO_ATTACHMENTS' ) ) {
				$ret = media_handle_sideload( $file_array, $vid['post_id'], 'Video thumbnail for ' . $vid['type'] . ' video ' . $vid['title'] );

				if ( isset( $vid['id'] ) )
					update_post_meta( $ret, 'wpseo_video_id', $vid['id'] );

				$img = wp_get_attachment_image_src( $ret, 'medium' );

				// Try and prevent relative paths to images
				if ( strpos( $img[0], 'http' ) !== 0 )
					$img = get_site_url( null, $img[0] );
				else
					$img = $img[0];

				return $img;
			} else {
				$file = wp_handle_sideload( $file_array, array( 'test_form' => false ) );

				if ( !isset( $file['error'] ) ) {
					return $file['url'];
				}
			}
		}
	}

	/**
	 * Checks whether there are oembed URLs in the post that should be included in the video sitemap.
	 *
	 * @since 0.1
	 *
	 * @param string $content the content of the post.
	 * @return array|boolean returns array $urls with type of video as array key and video URL as content, or false on negative
	 */
	private function grab_embeddable_urls( $content ) {
		global $echo;

		// For compatibility with Youtube Lyte
		$content = str_replace( 'httpv://', 'http://', $content );

		// For compatibility with Smart Youtube Pro
		$content = str_replace( 'httpvh://', 'http://', $content );

		// Catch both the single line embeds as well as the embeds using the [embed] shortcode.
		preg_match_all( '|\[embed([^\]]+)?\](https?://[^\s"]+)\[/embed\]|im', $content, $matches );
		preg_match_all( '/^\s*(https?:\/\/[^\s"]+)\s*$/im', $content, $matches2 );

		$matched_urls = array_merge( $matches[2], $matches2[1] );

		if ( preg_match( '|(<iframe.*</iframe>)|', $content, $iframe ) ) {
			if ( !preg_match( '#src="([^"]+)"#', $iframe[1], $iframesrc ) )
				preg_match( "#src='([^']+)'#", $iframe[1], $iframesrc );

			$matched_urls[] = $iframesrc[1];
		}

		if ( preg_match( '|(<object.*</object>)|', $content, $matches ) ) {
			if ( preg_match( '|<param name="src" value="([^"]+)"( )?/?>|', $content, $srcmatch ) ) {
				$matched_urls[] = $srcmatch[1];
			} else if ( preg_match( '|<param name="movie" value="([^"]+)"( )?/?>|', $content, $moviematch ) ) {
				$matched_urls[] = $moviematch[1];
			}
		}

		if ( preg_match( '/<a href=(\'|")(https?:\/\/(www\.)?(youtube|vimeo)\.com\/[^\1]+)\1 rel=(\'|")wp-video-lightbox\1>/', $content, $matches ) ) {
			$matched_urls[] = $matches[2];
		}

		if ( count( $matched_urls > 0 ) ) {
			$urls = array();
			foreach ( $matched_urls as $match ) {
				if ( substr( $match, 0, 4 ) != 'http' )
					$match = 'http:' . $match;
				if ( $echo && WP_DEBUG )
					echo $match . '<br/>';

				$url_info = $this->parse_url( $match );
				if ( !isset( $url_info['domain'] ) )
					continue;
				if ( $echo && WP_DEBUG )
					echo $url_info['domain'] . '<br/>';
				switch ( $url_info['domain'] ) {
					case 'blip.tv':
						$urls['blip'] = $match;
						break;
					case 'brightcove.com':
						if ( preg_match( '#<param name="flashVars" value="videoId=(\d+)#', $content, $bcmatch ) ) {
							$urls['brightcove'] = $bcmatch[1];
						}
						break;
					case 'dailymotion.com':
						$urls['dailymotion'] = $match;
						break;
					case 'flickr.com':
						$urls['flickr'] = $match;
						break;
					case 'viddler.com':
						$urls['viddler'] = $match;
						break;
					case 'vimeo.com':
						$urls['vimeo'] = $match;
						break;
					case 'wistia.com':
						$urls['wistia'] = $match;
						break;
					case 'wordpress.tv':
						$urls['wordpress.tv'] = $match;
						break;
					case 'youtu.be':
						$urls['youtube'] = $match;
						break;
					case 'youtube.com':
						$urls['youtube'] = $match;
						break;
					case 'youtube-nocookie.com':
						$urls['youtube'] = $match;
						break;
				}
			}
			if ( count( $urls ) > 0 ) {
				return $urls;
			} else
				return false;
		} else {
			return false;
		}
	}

	/**
	 * Parse a URL and find the host name and more.
	 *
	 * @since 1.1
	 *
	 * @link  http://php.net/manual/en/function.parse-url.php#83875
	 *
	 * @param string $url The URL to parse
	 * @return array
	 */
	function parse_url( $url ) {
		$r = "^(?:(?P<scheme>\w+)://)?";
		$r .= "(?:(?P<login>\w+):(?P<pass>\w+)@)?";
		$r .= "(?P<host>(?:(?P<subdomain>[-\w\.]+)\.)?" . "(?P<domain>[-\w]+\.(?P<extension>\w+)))";
		$r .= "(?::(?P<port>\d+))?";
		$r .= "(?P<path>[\w/]*/(?P<file>\w+(?:\.\w+)?)?)?";
		$r .= "(?:\?(?P<arg>[\w=&]+))?";
		$r .= "(?:#(?P<anchor>\w+))?";
		$r = "!$r!"; // Delimiters

		preg_match( $r, $url, $out );

		return $out;
	}

	/**
	 * Wrapper for the WordPress internal wp_remote_get function, making sure a proper user-agent is sent along.
	 *
	 * @since 0.1
	 *
	 * @param string $url The URL to retrieve.
	 * @return array|boolean $body Returns the body of the post when successfull, false when unsuccessfull.
	 */
	private function remote_get( $url ) {
		$response = wp_remote_get( $url,
			array(
				'redirection' => 1,
				'httpversion' => '1.1',
				'user-agent'  => 'WordPress Video SEO plugin ' . WPSEO_VERSION . '; WordPress (' . home_url( '/' ) . ')'
			)
		);

		if ( !is_wp_error( $response ) && $response['response']['code'] == 200 ) {
			return $response['body'];
		} else {
			return false;
		}
	}

	/**
	 * Use the "new" post data with the old video data, to prevent the need for an external video API call when the video hasn't changed.
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The "new" video array
	 * @param array  $oldvid The old video array
	 * @param string $thumb  Possibly the thumbnail, if set manually.
	 * @return array $vid With the new values from $vid and the old values from $oldvid combined.
	 */
	private function use_old_video_data( $vid, $oldvid, $thumb ) {
		$oldvid['title']            = $vid['title'];
		$oldvid['description']      = $vid['description'];
		$oldvid['publication_date'] = $vid['publication_date'];
		if ( isset( $vid['category'] ) )
			$oldvid['category'] = $vid['category'];
		if ( isset( $vid['tag'] ) )
			$oldvid['tag'] = $vid['tag'];

		if ( $thumb != '' )
			$oldvid['thumbnail_loc'] = $thumb;

		return $oldvid;
	}

	/**
	 * Retrieve video details from Brightcove
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch" from Brightcove, if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function brightcove_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( ( isset( $oldvid['url'] ) && $oldvid['url'] == $vid['url'] ) || ( isset( $oldvid['id'] ) && isset( $vid['id'] ) && $oldvid['id'] == $vid['id'] ) ) {
			return $this->use_old_video_data( $vid, $oldvid, $thumb );
		}

		if ( is_numeric( $vid['url'] ) ) {
			$vid['id'] = $vid['url'];
			unset( $vid['url'] );
		}

		if ( !isset( $vid['id'] ) )
			return false;

		$token = get_option( 'bc_api_key' );

		if ( empty( $token ) )
			return false;

		$request = 'http://api.brightcove.com/services/library?command=find_video_by_id&video_id=' . $vid['id'] . '&video_fields=name,playsTotal,videoStillURL,length,FLVURL&token=' . $token;

		$response = $this->remote_get( $request );
		if ( $response == null || $response == 'null' )
			return false;

		$bc = json_decode( $response );
		if ( isset( $bc->error ) ) {
			if ( WP_DEBUG )
				echo '<pre>' . print_r( $bc, 1 ) . '</pre>';
			return false;
		}
		if ( WP_DEBUG )
			echo '<pre>' . print_r( $bc, 1 ) . '</pre>';

		$vid['type']          = 'brightcove';
		$vid['duration']      = $bc->length / 1000;
		$vid['view_count']    = (int) $bc->playsTotal;
		$vid['thumbnail_loc'] = $this->make_image_local( $bc->videoStillURL, $vid );
		$vid['content_loc']   = $bc->FLVURL;
		return $vid;
	}

	/**
	 * Retrieve video details from Viddler
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch" from Viddler, if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function viddler_details( $vid, $oldvid = array(), $thumb = '' ) {

		if ( ( isset( $oldvid['url'] ) && isset( $vid['url'] ) && $oldvid['url'] == $vid['url'] ) || ( isset( $oldvid['id'] ) && isset( $vid['id'] ) && $oldvid['id'] == $vid['id'] ) ) {
			return $this->use_old_video_data( $vid, $oldvid, $thumb );
		}

		if ( !isset( $vid['id'] ) && isset( $vid['url'] ) ) {
			if ( preg_match( '#https?://(www.)?viddler\.com/embed/([^/]+).*#', $vid['url'], $match ) )
				$vid['id'] = $match[2];
		}

		if ( isset( $vid['id'] ) )
			$response = $this->remote_get( 'http://api.viddler.com/api/v2/viddler.videos.getDetails.php?key=0118093f713643444556524f452f&video_id=' . $vid['id'] );
		else
			$response = $this->remote_get( 'http://api.viddler.com/api/v2/viddler.videos.getDetails.php?key=0118093f713643444556524f452f&url=' . $vid['url'] );

		if ( $response ) {
			$video = unserialize( $response );

			$vid['id']         = $video['video']['id'];
			$vid['duration']   = $video['video']['length'];
			$vid['view_count'] = (int) $video['video']['view_count'];
			$vid['player_loc'] = 'http://www.viddler.com/player/' . $video['video']['id'] . '/';
			$vid['type']       = 'viddler';

			if ( isset( $video['video']['files'] ) ) {
				foreach ( $video['video']['files'] as $file ) {
					if ( $file['ext'] == 'mp4' )
						$vid['content_loc'] = $file['url'];
				}
			}

			if ( $thumb != '' )
				$vid['thumbnail_loc'] = $thumb;
			else
				$vid['thumbnail_loc'] = $this->make_image_local( $video['video']['thumbnail_url'], $vid );

			return $vid;
		}

		return false;
	}

	/**
	 * Retrieve video details from Flickr
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function flickr_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( preg_match( '|/(\d+)/?$|', $vid['url'], $matches ) ) {
			$vid['id'] = $matches[1];

			if ( isset( $oldvid['id'] ) && $vid['id'] == $oldvid['id'] )
				return $this->use_old_video_data( $vid, $oldvid, $thumb );

			$response = $this->remote_get( "http://api.flickr.com/services/rest/?method=flickr.photos.getInfo&api_key=2d2985adb59d21e6933368e41e5ca3b0&photo_id=" . $vid['id'] . "&format=json&nojsoncallback=1" );

			if ( $response ) {
				$flickr = json_decode( $response );

				if ( $flickr->photo->media != 'video' )
					return false;

				$vid['duration']   = $flickr->photo->video->duration;
				$vid['view_count'] = (int) $flickr->photo->views;
				$vid['type']       = 'flickr';
				$vid['player_loc'] = 'http://www.flickr.com/apps/video/stewart.swf?v=109786&intl_lang=en_us&photo_secret=' . $flickr->photo->secret . '&photo_id=' . $vid['id'];

				if ( $thumb != '' )
					$vid['thumbnail_loc'] = $thumb;
				else
					$vid['thumbnail_loc'] = $this->make_image_local( 'http://farm' . $flickr->photo->farm . '.staticflickr.com/' . $flickr->photo->server . '/' . $matches[1] . '_' . $flickr->photo->secret . '.jpg', $vid );
				return $vid;
			}
		}
		return false;
	}

	/**
	 * Retrieve video details from Dailymotion
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function dailymotion_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( !isset( $vid['id'] ) ) {
			if ( preg_match( '|https?://(www\.)?dailymotion.com/video/([^_]+)_.*|', $vid['url'], $matches ) )
				$vid['id'] = $matches[2];
			else
				return false;
		}

		if ( isset( $oldvid['id'] ) && $vid['id'] == $oldvid['id'] )
			return $this->use_old_video_data( $vid, $oldvid, $thumb );

		$response = $this->remote_get( 'https://api.dailymotion.com/video/' . $vid['id'] . '?fields=duration,embed_url,thumbnail_large_url,views_total' );

		if ( $response ) {
			$video = json_decode( $response );

			$vid['view_count'] = (int) $video->views_total;
			$vid['duration']   = $video->duration;
			$vid['player_loc'] = $video->embed_url;
			$vid['type']       = 'dailymotion';

			if ( $thumb != '' )
				$vid['thumbnail_loc'] = $thumb;
			else
				$vid['thumbnail_loc'] = $this->make_image_local( $video->thumbnail_large_url, $vid );

			return $vid;
		}

		return false;
	}

	/**
	 * Retrieve video details from Blip.tv
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function blip_details( $vid, $oldvid = array(), $thumb = '' ) {

		if ( !isset( $vid['id'] ) || empty( $vid['id'] ) ) {
			if ( preg_match( '/.*-(\d+)$/', $vid['url'], $matches ) )
				$vid['id'] = $matches[1];

			// This isn't active yet as the ID here doesn't translate directly into a Blip ID...
			// if ( preg_match( '|http://blip\.tv/play/([^\.]+)\.html.*|', $vid['url'], $matches ) )
			//	$vid['id'] = $matches[1];
		}

		if ( isset( $vid['id'] ) ) {

			if ( isset( $oldvid['id'] ) && $vid['id'] == $oldvid['id'] )
				return $this->use_old_video_data( $vid, $oldvid, $thumb );

			$response = $this->remote_get( 'http://blip.tv/rss/view/' . $vid['id'] );

			echo '<pre>' . print_r( $response, 1 ) . '</pre>';

			if ( $response ) {
				preg_match( "|<blip:runtime>([\d]+)</blip:runtime>|", $response, $match );
				$vid['duration'] = $match[1];

				preg_match( '|<media:player url="([^"]+)">|', $response, $match );
				$vid['player_loc'] = $match[1];

				preg_match( '|<enclosure length="[\d]+" type="[^"]+" url="([^"]+)"/>|', $response, $match );
				$vid['content_loc'] = $match[1];

				$vid['type'] = 'blip.tv';

				if ( $thumb != '' ) {
					$vid['thumbnail_loc'] = $thumb;
				} else {
					preg_match( '|<media:thumbnail url="([^"]+)"/>|', $response, $match );
					$vid['thumbnail_loc'] = $this->make_image_local( $match[1], $vid );
				}
				return $vid;
			}
		}
		return false;
	}

	/**
	 * Retrieve video details from Vimeo
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function vimeo_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( !isset( $vid['id'] ) ) {
			if ( preg_match( '#https?://(player\.|www\.)?vimeo\.com/(video/)?(\d+)#', $vid['url'], $matches ) )
				$vid['id'] = $matches[3];

			if ( preg_match( '#https?://(www\.)?vimeo\.com/moogaloop\.swf\?clip_id=([^&]+)#', $vid['url'], $matches ) )
				$vid['id'] = $matches[2];
		}

		if ( isset( $vid['id'] ) ) {
			if ( isset( $oldvid['id'] ) && $vid['id'] == $oldvid['id'] )
				return $this->use_old_video_data( $vid, $oldvid, $thumb );

			$response = $this->remote_get( 'http://vimeo.com/api/oembed.json?url=http://vimeo.com/' . $vid['id'] );
			if ( $response ) {
				$video = json_decode( $response );

				$vid['player_loc'] = 'http://www.vimeo.com/moogaloop.swf?clip_id=' . $vid['id'];
				$vid['duration']   = $video->duration;
				$vid['type']       = 'vimeo';

				if ( $thumb != '' )
					$vid['thumbnail_loc'] = $thumb;
				else
					$vid['thumbnail_loc'] = $this->make_image_local( $video->thumbnail_url, $vid );

				return $vid;
			}
		}

		return false;
	}

	/**
	 * Retrieve video details from Vippy
	 *
	 * @since 1.3.4
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function vippy_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( isset( $oldvid['id'] ) && isset( $vid['id'] ) && $oldvid['id'] == $vid['id'] ) {
			return $this->use_old_video_data( $vid, $oldvid, $thumb );
		}
		$vippy_id = $vid['id'];
		if ( !class_exists( 'Vippy' ) ) return false; //Requires the Vippy plugin http://wordpress.org/extend/plugins/vippy/

		//Retrieve the vippy video
		$vippy       = new Vippy;
		$vippy_video = $vippy->get_video( array( 'videoId' => $vippy_id, 'statistics' => 1 ) );
		if ( isset( $vippy_video->error ) ) return false;

		//Fill the details
		$vippy_video = isset( $vippy_video->vippy[0] ) ? $vippy_video->vippy[0] : false;
		if ( !$vippy_video ) return false;
		$vid['type']        = 'vippy';
		$vid['player_loc']  = $vippy_video->open_graph_url;
		$vid['content_loc'] = $vippy_video->highQuality; //MP4
		if ( $thumb != '' )
			$vid['thumbnail_loc'] = $thumb;
		else
			$vid['thumbnail_loc'] = $this->make_image_local( $vippy_video->thumbnail, $vid );
		$vid['duration']   = round( $vippy_video->duration ); //convert 30.09 to 30
		$vid['view_count'] = (int) $vippy_video->views;
		return $vid;
	}

	/**
	 * Retrieve video details from Wistia
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function wistia_details( $vid, $oldvid = array(), $thumb = '' ) {

//		if ( isset( $oldvid['url'] ) && $vid['url'] == $oldvid['url'] )
//			return $this->use_old_video_data( $vid, $oldvid, $thumb );

//		else
		if ( isset( $vid['url'] ) ) {

			$url = urlencode( $vid['url'] );

			$response = $this->remote_get( 'http://fast.wistia.com/oembed?url=' . $url );
			if ( $response ) {
				$video = json_decode( $response );

				if ( preg_match( '/<iframe src="([^"]+)/', $video->html, $match ) ) {
					$framesrc = $this->remote_get( $match[1] );
					if ( preg_match( "/<a href='([^']+)' id='wistia_fallback'/", $framesrc, $framematch ) ) {
						$vid['content_loc'] = $framematch[1];
						$vid['duration']    = round( $video->duration );
						$vid['type']        = 'wistia';

						if ( $thumb != '' )
							$vid['thumbnail_loc'] = $thumb;
						else
							$vid['thumbnail_loc'] = $this->make_image_local( $video->thumbnail_url, $vid );

						return $vid;

					}
				}
			}
		}

		return false;
	}

	/**
	 * Retrieve video details from YouTube
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function youtube_details( $vid, $oldvid = array(), $thumb = '' ) {

		if ( !isset( $vid['id'] ) ) {
			$id_match = '[0-9a-zA-Z\-_]+';
			if ( preg_match( '|https?://(www\.)?youtube\.com/(watch)?\?v=(' . $id_match . ')|', $vid['url'], $matches ) )
				$vid['id'] = $matches[3];
			else if ( preg_match( '|https?://(www\.)?youtube(-nocookie)?\.com/embed/(' . $id_match . ')|', $vid['url'], $matches ) )
				$vid['id'] = $matches[3];
			else if ( preg_match( '|https?://(www\.)?youtube\.com/v/(' . $id_match . ')|', $vid['url'], $matches ) )
				$vid['id'] = $matches[2];
			else if ( preg_match( '|http://youtu\.be/(' . $id_match . ')|', $vid['url'], $matches ) )
				$vid['id'] = $matches[1];
			else if ( !preg_match( '|^http|', $vid['url'], $matches ) )
				$vid['id'] = $vid['url'];
		}

		if ( isset( $vid['id'] ) ) {
			if ( isset( $oldvid['id'] ) && $vid['id'] == $oldvid['id'] )
				return $this->use_old_video_data( $vid, $oldvid, $thumb );

			$vid['player_loc'] = htmlentities( 'http://www.youtube-nocookie.com/v/' . $vid['id'] );
			$vid['type']       = 'youtube';

			$response = $this->remote_get( 'http://gdata.youtube.com/feeds/api/videos/' . $vid['id'] );

			if ( $response ) {
				// Thumbnail
				if ( $thumb != '' ) {
					$vid['thumbnail_loc'] = $thumb;
				} else {
					preg_match( "|<media:thumbnail url='([^']+)'|", $response, $match );
					$vid['thumbnail_loc'] = $this->make_image_local( $match[1], $vid );
				}

				// View count
				preg_match( "|<yt:statistics favoriteCount='([\d]+)' viewCount='([\d]+)'/>|", $response, $match );
				$vid['view_count'] = (int) $match[2];

				// Duration
				preg_match( "|<yt:duration seconds='([0-9]+)'/>|", $response, $match );
				$vid['duration'] = $match[1];
				return $vid;
			}
		}

		return false;
	}

	/**
	 * Retrieve video details from VideoPress
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function videopress_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( isset( $oldvid['id'] ) && $vid['id'] == $oldvid['id'] )
			return $this->use_old_video_data( $vid, $oldvid, $thumb );

		$domain         = parse_url( home_url(), PHP_URL_HOST );
		$request_params = array( 'guid' => $vid['id'], 'domain' => $domain );

		$url = 'https://v.wordpress.com/data/wordpress.json?' . http_build_query( $request_params, null, '&' );

		$response = $this->remote_get( $url );

		if ( $response ) {
			$video = json_decode( $response );

			$vid['duration']   = $video->duration;
			$vid['player_loc'] = 'http://s0.videopress.com/player.swf?guid=' . $vid['id'];

			$vid['type'] = 'videopress';

			if ( isset( $video->mp4 ) )
				$vid['content_loc'] = $video->mp4->url;

			if ( $thumb != '' )
				$vid['thumbnail_loc'] = $thumb;
			else
				$vid['thumbnail_loc'] = $this->make_image_local( $video->posterframe, $vid );

			return $vid;
		}

		return false;
	}

	/**
	 * Retrieve video details from WordPress.tv (well grab the ID and then use the VideoPress API)
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function wordpresstv_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( isset( $oldvid['url'] ) && $vid['url'] == $oldvid['url'] )
			return $this->use_old_video_data( $vid, $oldvid, $thumb );

		$response = $this->remote_get( 'http://wordpress.tv/oembed/?url=' . $vid['url'] );
		if ( $response ) {
			$wptv = json_decode( $response );

			if ( preg_match( '|v\.wordpress\.com/([^"]+)|', $wptv->html, $match ) ) {
				$vid['id'] = $match[1];
				return $this->videopress_details( $vid, $oldvid, $thumb );
			} else {
				return false;
			}

		}
		return false;
	}

	/**
	 * Retrieve video details from Metacafe
	 *
	 * @since 0.1
	 *
	 * @link  http://help.metacafe.com/?page_id=238 Metacafe API docs.
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function metacafe_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( !isset( $vid['id'] ) ) {
			if ( preg_match( '|/watch/(\d+)/|', $vid['url'], $matches ) )
				$vid['id'] = $matches[1];
		}

		if ( isset( $vid['id'] ) ) {

			if ( isset( $oldvid['id'] ) && $vid['id'] == $oldvid['id'] )
				return $this->use_old_video_data( $vid, $oldvid, $thumb );

			$vid['type'] = 'metacafe';

			$response = $this->remote_get( 'http://www.metacafe.com/api/item/' . $vid['id'] . '/' );

			preg_match( '/duration="(\d+)"/', $response, $match );
			$vid['duration'] = $match[1];

			$vid['player_loc'] = 'http://www.metacafe.com/fplayer/' . $vid['id'] . '/.swf';

			preg_match( '/<media:content url="([^"]+)"/', $response, $match );
			$vid['content_loc'] = $match[1];

			if ( $thumb != '' ) {
				$vid['thumbnail_loc'] = $thumb;
			} else {
				preg_match( '/<media:thumbnail url="([^"]+)"/', $response, $match );
				$vid['thumbnail_loc'] = $this->make_image_local( $match[1], $vid );
			}

			return $vid;
		}

		return false;
	}

	/**
	 * Retrieve video details for Veoh Video's
	 *
	 * @since 0.1
	 *
	 * @param array  $vid    The video array with all the data.
	 * @param array  $oldvid The video array with all the data of the previous "fetch", if available.
	 * @param string $thumb  The URL to the manually set thumbnail, if available.
	 * @return array|boolean $vid Returns a filled video array when successfull, false when unsuccessful.
	 */
	private function veoh_details( $vid, $oldvid = array(), $thumb = '' ) {
		if ( !isset( $vid['id'] ) ) {
			if ( preg_match( '|veoh\.com/videos/([^/]+)$|', $vid['url'], $matches ) )
				$vid['id'] = $matches[1];
		}

		if ( isset( $vid['id'] ) ) {
			if ( isset( $oldvid['id'] ) && $vid['id'] == $oldvid['id'] )
				return $this->use_old_video_data( $vid, $oldvid, $thumb );

			$vid['type'] = 'veoh';

			$vid['player_loc'] = 'http://www.veoh.com/veohplayer.swf?permalinkId=' . $vid['id'];

			if ( $thumb != '' ) {
				$vid['thumbnail_loc'] = $thumb;
			} else {
				$vid['thumbnail_loc'] = $this->make_image_local( 'http://ll-images.veoh.com/media/w300/thumb-' . $vid['id'] . '-1.jpg', $vid );
			}

			return $vid;
		}

		return false;
	}

	/**
	 * Parse the content of a post or term description.
	 *
	 * @since 1.3
	 *
	 * @param string $content The content to parse for videos.
	 * @param array  $vid     The video array to update.
	 * @param array  $oldvid  The former video array.
	 * @return array $vid
	 */
	public function index_content( $content, $vid, $oldvid = array() ) {
		global $shortcode_tags;

		$shortcode_tags = array(
			'bliptv'                 => '',
			'blip.tv'                => '',
			'dailymotion'            => '',
			'embedplusvideo'         => '',
			'flickrvideo'            => '',
			'flowplayer'             => '',
			'flv'                    => '',
			'jwplayer'               => '',
			'metacafe'               => '',
			'tube'                   => '',
			'veoh'                   => '',
			'viddler'                => '',
			'video'                  => '',
			'video_lightbox_vimeo5'  => '',
			'video_lightbox_youtube' => '',
			'vimeo'                  => '',
			'vippy'                  => '',
			'wpvideo'                => '',
			'youtube'                => '',
			'youtube_sc'             => '',
		);

		if ( preg_match( '/' . get_shortcode_regex() . '/', $content, $matches ) ) {
			$thumb = '';
			preg_match( '/image=(\'|")?([^\'"\s]+)(\'|")?/', $matches[3], $match );
			if ( isset( $match[2] ) && !empty( $match[2] ) )
				$thumb = $match[2];

			switch ( $matches[2] ) {
				case 'bliptv':
					$vid['id'] = trim( $matches[3] );
					$vid       = $this->blip_details( $vid, $oldvid, $thumb );
					break;
				case 'blip.tv':
					if ( preg_match( '|posts_id=(\d+)|', $matches[3], $match ) ) {
						$vid['id'] = $match[1];
						$vid       = $this->blip_details( $vid, $oldvid, $thumb );
					}
					break;
				case 'dailymotion':
					if ( !empty( $matches[5] ) ) {
						$vid['url'] = $matches[5];
					} else if ( !empty( $matches[3] ) ) {
						$url_or_id = trim( $matches[3] );
						if ( strpos( $url_or_id, 'http' ) === 0 )
							$vid['url'] = $url_or_id;
						else
							$vid['id'] = $url_or_id;
					}
					$vid = $this->dailymotion_details( $vid, $oldvid, $thumb );
					break;
				case 'embedplusvideo':
					if ( preg_match( '/standard=(\'|")([^\1]+)\1/U', $matches[3], $match ) ) {
						$vid['url'] = $match[2];
						$vid        = $this->youtube_details( $vid, $oldvid, $thumb );
					}
					break;
				case 'flickrvideo':
					$vid['url'] = $matches[5];
					$vid        = $this->flickr_details( $vid, $oldvid, $thumb );
					break;
				case 'flowplayer':
					if ( preg_match( '/src=(\'|")([^\1]+)\1/U', $matches[0], $match ) ) {
						$vid['content_loc'] = $match[1];

						if ( preg_match( '/splash=(\'|")([^\1]+)\1/U', $matches[0], $match ) ) {
							$vid['thumbnail_loc'] = $match[1];
						}
						$vid['type'] = 'jwplayer';
					}
					break;
				case 'flv':
					// @todo add fallback poster image for when no poster image present
					$vid['content_loc']   = $matches[5];
					$vid['player_loc']    = plugins_url( '/vipers-video-quicktags/resources/jw-flv-player/player.swf?file=' . urlencode( $matches[5] ) );
					$vid['thumbnail_loc'] = $thumb;
					$vid['id']            = md5( $matches[5] );
					$vid['type']          = 'flv';
					break;
				case 'jwplayer':
					$vid['type'] = 'jwplayer';
					if ( preg_match( '/mediaid=(\'|")(\d+)\1/U', $matches[0], $match ) ) {
						$vid['content_loc']   = WP_CONTENT_URL . '/uploads/' . get_attached_file( $match[2] );
						$vid['duration']      = get_post_meta( $match[2], 'jwplayermodule_duration', true );
						$vid['thumbnail_loc'] = get_post_meta( $match[2], 'jwplayermodule_thumbnail_url', true );
					} else {
						if ( preg_match( '/html5_file=(\'|")([^\1]+)\1/U', $matches[0], $match ) ) {
							$vid['content_loc'] = $match[2];
						} else if ( preg_match( '/file=(\'|")([^\1]+)\1/U', $matches[0], $match ) ) {
							$vid['content_loc'] = $match[2];
						}
						if ( isset( $vid['content_loc'] ) ) {
							preg_match( '/image=(\'|")([^\1]+)\1/U', $matches[0], $match );
							$vid['thumbnail_loc'] = $match[2];
						}
					}
					break;
				case 'metacafe':
					if ( !empty( $matches[5] ) ) {
						$vid['url'] = $matches[5];
					} else if ( !empty( $matches[3] ) ) {
						$vid['id'] = trim( $matches[3] );
					}
					$vid = $this->metacafe_details( $vid, $oldvid, $thumb );
					break;
				case 'tube':
					$vid['url'] = $matches[5];
					$vid        = $this->youtube_details( $vid, $oldvid, $thumb );
					break;
				case 'veoh':
					if ( !empty( $matches[5] ) ) {
						$vid['url'] = $matches[5];
					} else if ( !empty( $matches[3] ) ) {
						$vid['id'] = trim( $matches[3] );
					}
					$vid = $this->veoh_details( $vid, $oldvid, $thumb );
					break;
				case 'viddler':
					if ( preg_match( '/.*id=([^&]+).*/', $matches[0], $match ) ) {
						$vid['id'] = $match[1];
						$vid       = $this->viddler_details( $vid, $oldvid, $thumb );
					}
					break;
				case 'video':
					if ( preg_match( '/src=(\'|")([^\1]+)\1/U', $matches[3], $match ) ) {

						$vid['type'] = 'mediaelement-js';

						$src = $match[2];

						// If the src has an extension, use it as content_loc, otherwise, see if we can find the file
						if ( substr( $src, strlen( $src ) - 4, 1 ) == '.' ) {
							$vid['content_loc'] = $src;
						} else {
							if ( substr( $src, 0, 4 ) != 'http' )
								$filename = WP_CONTENT_DIR . substr( $src, strlen( WP_CONTENT_DIR ) - strrpos( WP_CONTENT_DIR, '/' ) );
							else
								$filename = WP_CONTENT_DIR . substr( $src, strlen( WP_CONTENT_URL ) );

							if ( file_exists( $filename . '.mp4' ) ) {
								$vid['content_loc'] = $src . '.mp4';
							} elseif ( file_exists( $filename . '.m4v' ) ) {
								$vid['content_loc'] = $src . '.m4v';
							}

						}

						// If a poster image was specified, use that, otherwise, try and find a suitable .jpg
						if ( preg_match( '/poster=(\'|")([^\1]+)\1/U', $matches[3], $match ) ) {
							$vid['thumbnail_loc'] = $match[2];
						} else {
							$img_file = preg_replace( '/\.(mp4|m4v|flv)/', '.jpg', $vid['content_loc'] );
							if ( file_exists( $img_file ) )
								$vid['thumbnail_loc'] = $img_file;
						}

					}

					if ( preg_match( '/mp4=(\'|")([^\1]+)\1/U', $matches[3], $match ) )
						$vid['content_loc'] = $match[2];
					else if ( preg_match( '/ogg=(\'|")([^\1]+)\1/U', $matches[3], $match ) )
						$vid['content_loc'] = $match[2];
					else if ( preg_match( '/webm=(\'|")([^\1]+)\1/U', $matches[3], $match ) )
						$vid['content_loc'] = $match[2];

					if ( isset( $vid['content_loc'] ) && !isset( $vid['thumbnail_loc'] ) && preg_match( '/poster=(\'|")([^\1]+)\1/U', $matches[3], $match ) )
						$vid['thumbnail_loc'] = $match[2];

					break;
				case 'video_lightbox_vimeo5':
					if ( preg_match( '/video_id=(\'|")?(\d+)\1?/', $matches[0], $match ) ) {
//						echo '<pre>'.print_r($match,1).'</pre>';
						$vid['id'] = $match[2];
						$vid       = $this->vimeo_details( $vid, $oldvid, $thumb );
					}
					break;
				case 'video_lightbox_youtube':
					if ( preg_match( '/video_id=(\'|")?([0-9a-zA-Z\-_]+)\1?/', $matches[0], $match ) ) {
						$vid['id'] = $match[2];
						$vid       = $this->youtube_details( $vid, $oldvid, $thumb );
					}
					break;
				case 'vimeo':
					if ( !empty( $matches[5] ) ) {
						$vid['url'] = $matches[5];
					} else if ( !empty( $matches[3] ) ) {
						$vid['id'] = trim( $matches[3] );
					}
					$vid = $this->vimeo_details( $vid, $oldvid, $thumb );
					break;
				case 'vippy':
					$atts      = shortcode_parse_atts( $matches[3] );
					$vid['id'] = isset( $atts['id'] ) ? $atts['id'] : 0;
					$vid       = $this->vippy_details( $vid, $oldvid, $thumb );
					break;
				case 'wpvideo':
				case 'videopress':
					if ( preg_match( '/^([^\s]+)/', trim( $matches[3] ), $match ) ) {
						$vid['id'] = $match[1];
						$vid       = $this->videopress_details( $vid, $oldvid, $thumb );
					}
					break;
				case 'youtube':
					$vid['url'] = $matches[5];
					$vid        = $this->youtube_details( $vid, $oldvid, $thumb );
					break;
				case 'youtube_sc':
					if ( preg_match( '/(url|v|video)=(\'|")([^\1]+)\1/U', $matches[3], $match ) ) {
						$vid['url'] = $match[3];
						$vid        = $this->youtube_details( $vid, $oldvid, $thumb );
					}
					break;
				default:
					if ( WP_DEBUG ) {
						echo '<pre>' . print_r( $matches, 1 ) . '</pre>';
						echo '<pre>' . print_r( $vid, 1 ) . '</pre>';
					}
					$vid = false;
					break;
			}
			if ( $vid ) {
				return $vid;
			}
		}

		if ( !isset( $vid['id'] ) && $oembed = $this->grab_embeddable_urls( $content ) ) {
			foreach ( $oembed as $type => $url ) {
				$vid['url'] = $url;
				switch ( $type ) {
					case 'blip':
						$vid = $this->blip_details( $vid, $oldvid );
						break;
					case 'brightcove':
						$vid = $this->brightcove_details( $vid, $oldvid );
						break;
					case 'dailymotion':
						$vid = $this->dailymotion_details( $vid, $oldvid );
						break;
					case 'flickr':
						$vid = $this->flickr_details( $vid, $oldvid );
						break;
					case 'viddler':
						$vid = $this->viddler_details( $vid, $oldvid );
						break;
					case 'vimeo':
						$vid = $this->vimeo_details( $vid, $oldvid );
						break;
					case 'wistia':
						$vid = $this->wistia_details( $vid, $oldvid );
						break;
					case 'wordpress.tv':
						$vid = $this->wordpresstv_details( $vid, $oldvid );
						break;
					case 'youtube':
						$vid = $this->youtube_details( $vid, $oldvid );
						break;
				}

				if ( $vid !== false ) {
					return $vid;
				}
			}
		}

		return 'none';
	}

	/**
	 * Check and, if applicable, update video details for a term description
	 *
	 * @since 1.3
	 *
	 * @param object  $term The term to check the description and possibly update the video details for.
	 * @param boolean $echo Whether or not to echo the performed actions.
	 *
	 * @return mixed $vid The video array that was just stored, or "none" if nothing was stored.
	 */
	public function update_video_term_meta( $term, $echo = false ) {
		$options = array_merge( get_wpseo_options(), get_option( 'wpseo_video' ) );

		if ( !isset( $options['videositemap_taxonomies'] ) )
			return false;

		if ( !in_array( $term->taxonomy, $options['videositemap_taxonomies'] ) )
			return false;

		$tax_meta = get_option( 'wpseo_taxonomy_meta' );
		$oldvid   = array();
		if ( !isset( $_POST['force'] ) )
			$oldvid = $tax_meta[$term->taxonomy]['_video'][$term->term_id];

		$vid = array();

		$title = wpseo_get_term_meta( $term->term_id, $term->taxonomy, 'wpseo_title' );
		if ( empty( $title ) && isset( $options['title-' . $term->taxonomy] ) )
			$title = wpseo_replace_vars( $options['title-' . $term->taxonomy], (array) $term );
		if ( empty( $title ) )
			$title = $term->name;
		$vid['title'] = htmlspecialchars( $title );

		$vid['description'] = wpseo_get_term_meta( $term->term_id, $term->taxonomy, 'wpseo_metadesc' );
		if ( !$vid['description'] ) {
			$vid['description'] = htmlspecialchars( substr( preg_replace( '/\s+/', ' ', strip_tags( $this->strip_shortcodes( get_term_field( 'description', $term->term_id, $term->taxonomy ) ) ) ), 0, 300 ) );
		}

		$vid['publication_date'] = date( "Y-m-d\TH:i:s+00:00" );

		$vid = $this->index_content( $term->description, $vid, $oldvid );

		if ( $vid != 'none' ) {
			$tax_meta[$term->taxonomy]['_video'][$term->term_id] = $vid;
			update_option( 'wpseo_taxonomy_meta', $tax_meta );

			if ( $echo ) {
				$link = get_term_link( $term );
				echo 'Updated <a href="' . $link . '">' . $vid['title'] . '</a> - ' . $vid['type'] . '<br/>';
			}
		}

		return $vid;
	}

	/**
	 * Returns the custom fields to check for posts.
	 *
	 * @since 1.3.4
	 *
	 * @return array $custom_fields Array of custom fields.
	 */
	function get_custom_fields() {
		$custom_fields = array(
			'videoembed', // Press75 Simple Video Embedder
			'_videoembed_manual', // Press75 Simple Video Embedder
			'_videoembed', // Press75 Simple Video Embedder
		);
		$options       = get_option( 'wpseo_video' );
		if ( isset( $options['custom_fields'] ) ) {
			$custom_fields = array_merge( $custom_fields, explode( ',', $options['custom_fields'] ) );
		}
		return $custom_fields;
	}

	/**
	 * Check and, if applicable, update video details for a post
	 *
	 * @since 0.1
	 *
	 * @param object  $post The post to check and possibly update the video details for.
	 * @param boolean $echo Whether or not to echo the performed actions.
	 *
	 * @return mixed $vid The video array that was just stored, or "none" if nothing was stored.
	 */
	public function update_video_post_meta( $post, $echo = false ) {
		global $wp_query;

		if ( is_numeric( $post ) )
			$post = get_post( $post );

		if ( !isset( $post->ID ) )
			return false;

		$options = array_merge( get_wpseo_options(), get_option( 'wpseo_video' ) );

		if ( !isset( $options['videositemap_posttypes'] ) )
			return false;

		if ( !in_array( $post->post_type, $options['videositemap_posttypes'] ) )
			return false;

		$_GLOBALS['post'] = $post;

		$oldvid = array();
		if ( !isset( $_POST['force'] ) )
			$oldvid = wpseo_get_value( 'video_meta', $post->ID );

		$title = wpseo_get_value( 'title', $post->ID );
		if ( ( !$title || empty( $title ) ) && isset( $options['title-' . $post->post_type] ) )
			$title = wpseo_replace_vars( $options['title-' . $post->post_type], (array) $post );
		else if ( ( !$title || empty( $title ) ) && !isset( $options['title-' . $post->post_type] ) )
			$title = wpseo_replace_vars( "%%title%% - %%sitename%%", (array) $post );

		if ( empty( $title ) )
			$title = $post->post_title;

		$vid = array();

		if ( $post->post_type == 'post' ) {
			$wp_query->is_single = true;
			$wp_query->is_page   = false;
		} else {
			$wp_query->is_single = false;
			$wp_query->is_page   = true;
		}

		$vid['post_id'] = $post->ID;

		$vid['title']            = htmlspecialchars( $title );
		$vid['publication_date'] = mysql2date( "Y-m-d\TH:i:s+00:00", $post->post_date_gmt );

		$vid['description'] = wpseo_get_value( 'metadesc', $post->ID );
		if ( empty( $vid['description'] ) || !$vid['description'] ) {
			$vid['description'] = htmlspecialchars( substr( preg_replace( '/\s+/', ' ', strip_tags( $this->strip_shortcodes( $post->post_content ) ) ), 0, 300 ) );
		}

		$content = $post->post_content;
		if ( $custom_fields = $this->get_custom_fields() ) {
			foreach ( $custom_fields as $cf ) {
				$content .= "\n" . get_post_meta( $post->ID, $cf, true ) . "\n";
			}
		}
		$vid = $this->index_content( $content, $vid, $oldvid );

		if ( 'none' != $vid ) {
			// Grab the meta data from the post
			$cats = wp_get_object_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
			if ( isset( $cats[0] ) ) {
				$vid['category'] = htmlspecialchars( $cats[0] );
				unset( $cats[0] );
			}

			$tags = wp_get_object_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );

			// If there is more than one category, use the rest as tags.
			if ( count( $cats ) > 0 )
				$tags = array_merge( $tags, $cats );

			$tag = array();
			if ( is_array( $tags ) ) {
				foreach ( $tags as $t ) {
					$tag[] = $t;
				}
			} else {
				if ( isset( $cats[0] ) )
					$tag[] = $cats[0]->name;
			}

			$focuskw = wpseo_get_value( 'focuskw', $post->ID );
			if ( !empty( $focuskw ) )
				$tag[] = $focuskw;
			$vid['tag'] = $tag;

			if ( $echo )
				echo 'Updated <a href="' . home_url( '?p=' . $post->ID ) . '">' . $post->post_title . '</a> - ' . $vid['type'] . '<br/>';
		}

		wpseo_set_value( 'video_meta', $vid, $post->ID );

		return $vid;
	}

	/**
	 * Remove both used and unused shortcodes from content.
	 *
	 * @since 1.3.3
	 *
	 * @param string $content Content to remove shortcodes from.
	 * @return string
	 */
	private function strip_shortcodes( $content ) {
		$content = preg_replace( '|\[(.+?)\](.+?\[/\\1\])?|s', '', $content );

		return $content;
	}

	/**
	 * Check whether the current visitor is actually Googlebot
	 *
	 * @since 1.2.2
	 *
	 * @return boolean
	 */
	private function is_google_bot() {
		if ( preg_match( "/Googlebot/", $_SERVER['HTTP_USER_AGENT'] ) ) {
			$hostname = gethostbyaddr( $_SERVER['REMOTE_ADDR'] );

			if ( preg_match( "/googlebot\.com$/", $hostname ) && gethostbyname( $hostname ) == $_SERVER['REMOTE_ADDR'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The main function of this class: it generates the XML sitemap's contents.
	 *
	 * @since 0.1
	 */
	public function build_video_sitemap() {
		$options = get_option( 'wpseo_video' );

		// Restrict access to the video sitemap to admins and Googlebot
		if ( isset( $options['cloak_sitemap'] ) && $options['cloak_sitemap'] && !current_user_can( 'manage_options' ) && !$this->is_google_bot() ) {
			wp_die( "We're sorry, access to our video sitemap is restricted to site admins and Googlebot." );
		}

		$output = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
		xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		if ( isset( $options['videositemap_posttypes'] ) ) {
			$args = array(
				'post_type'      => $options['videositemap_posttypes'],
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'offset'         => 0,
				'meta_key'       => '_yoast_wpseo_video_meta',
				'meta_compare'   => '!=',
				'meta_value'     => 'none'
			);

			while ( $items = get_posts( $args ) ) {

				if ( !empty( $items ) ) {
					foreach ( $items as $item ) {
						if ( false != wpseo_get_value( 'meta-robots', $item->ID ) && strpos( wpseo_get_value( 'meta-robots', $item->ID ), 'noindex' ) !== false )
							continue;

						$disable = wpseo_get_value( 'videositemap-disable', $item->ID );
						if ( $disable == 'on' )
							continue;

						$video = wpseo_get_value( 'video_meta', $item->ID );

						// Allow for the video's thumbnail to be overridden by the meta box input
						$videoimg = wpseo_get_value( 'videositemap-thumbnail', $item->ID );
						if ( $videoimg && trim( $videoimg ) != '' )
							$video['thumbnail_loc'] = $videoimg;

						// When we don't have a thumbnail and either a player_loc or a content_loc, skip this video.
						if (
							!isset( $video['thumbnail_loc'] )
							|| ( !isset( $video['player_loc'] ) && !isset( $video['content_loc'] ) )
						) {
							continue;
						}

						$video['permalink'] = get_permalink( $item );

						$rating = apply_filters( 'wpseo_video_rating', wpseo_get_value( 'videositemap-rating', $item->ID ) );
						if ( $rating && is_numeric( $rating ) && $rating >= 0 && $rating <= 5 )
							$video['rating'] = number_format( $rating, 1 );

						$not_family_friendly = apply_filters( 'wpseo_video_family_friendly', wpseo_get_value( 'videositemap-not-family-friendly', $item->ID ) );
						if ( $not_family_friendly && $not_family_friendly == 'on' )
							$video['family_friendly'] = "no";
						else
							$video['family_friendly'] = "yes";

						$video['author'] = $item->post_author;

						$output .= $this->print_sitemap_line( $video );
					}
				}

				$args['offset'] += 99;
			}

		}

		$tax_meta = get_option( 'wpseo_taxonomy_meta' );
		$terms    = get_terms( $options['videositemap_taxonomies'] );
		foreach ( $terms as $term ) {
			if ( !isset( $tax_meta[$term->taxonomy]['_video'][$term->term_id] ) )
				continue;
			$video = $tax_meta[$term->taxonomy]['_video'][$term->term_id];
			if ( is_array( $video ) ) {
				$video['permalink'] = get_term_link( $term, $term->taxonomy );
				$video['category']  = $term->name;
				$output .= $this->print_sitemap_line( $video );
			}
		}

		$output .= '</urlset>';
		$GLOBALS['wpseo_sitemaps']->set_sitemap( $output );
		$GLOBALS['wpseo_sitemaps']->set_stylesheet(
			'<?xml-stylesheet type="text/xsl" href="' . trailingslashit( WP_PLUGIN_URL . '/' . basename( dirname( __FILE__ ) ) ) . 'xml-video-sitemap.xsl"?>'
		);
	}

	/**
	 * Print a full <url> line in the sitemap.
	 *
	 * @since 1.3
	 *
	 * @param $video array The video object to print out
	 * @return string The output generated
	 */
	public function print_sitemap_line( $video ) {
		if ( !is_array( $video ) )
			return '';

		$output = "\t<url>\n";
		$output .= "\t\t<loc>" . $video['permalink'] . '</loc>' . "\n";
		$output .= "\t\t<video:video>\n";

		foreach ( $video as $key => $val ) {
			if ( in_array( $key, array( 'id', 'url', 'type', 'permalink', 'post_id' ) ) )
				continue;

			if ( $key == 'author' ) {
				$output .= "\t\t\t<video:uploader info='" . get_author_posts_url( $val ) . "'><![CDATA[" . get_the_author_meta( 'display_name', $val ) . "]]></video:uploader>\n";
				continue;
			}

			$xtra = '';
			if ( $key == 'player_loc' )
				$xtra = ' allow_embed="yes"';

			if ( $key == 'description' && empty( $val ) )
				$val = $video['title'];

			if ( !is_array( $val ) ) {
				$val = $this->clean_string( $val );
				if ( in_array( $key, array( 'description', 'category', 'tag', 'title' ) ) )
					$val = '<![CDATA[' . $val . ']]>';
				$output .= "\t\t\t<video:" . $key . $xtra . ">" . $val . "</video:" . $key . ">\n";
			} else {
				$i = 1;
				foreach ( $val as $v ) {
					// Only 32 tags are allowed
					if ( $key == 'tag' && $i == 33 )
						break;
					$v = $this->clean_string( $v );
					if ( in_array( $key, array( 'description', 'category', 'tag', 'title' ) ) )
						$v = '<![CDATA[' . $v . ']]>';
					$output .= "\t\t\t<video:" . $key . $xtra . ">" . $v . "</video:" . $key . ">\n";
					$i++;
				}
			}
		}

		// Allow custom implementations with extra tags here
		$output .= apply_filters( 'wpseo_video_item', '' );

		$output .= "\t\t</video:video>\n";

		$output .= "\t</url>\n";

		return $output;
	}

	/**
	 * Cleans a string for XML display purposes.
	 *
	 * @since 1.2.1
	 *
	 * @link  http://php.net/manual/en/function.html-entity-decode.php#98697 Modified for WP from here.
	 *
	 * @param string $in     The string to clean.
	 * @param int    $offset Offset of the string to start the cleaning at.
	 *
	 * @return string Cleaned string.
	 */
	function clean_string( $in, $offset = null ) {
		$out = trim( $in );
		$out = $this->strip_shortcodes( $out );
		$out = html_entity_decode( $out, ENT_QUOTES, "ISO-8859-15" );
		$out = html_entity_decode( $out, ENT_QUOTES, get_bloginfo( 'charset' ) );
		if ( !empty( $out ) ) {
			$entity_start = strpos( $out, '&', $offset );
			if ( $entity_start === false ) {
				// ideal
				return _wp_specialchars( $out );
			} else {
				$entity_end = strpos( $out, ';', $entity_start );
				if ( $entity_end === false ) {
					return _wp_specialchars( $out );
				} // zu lang um eine entity zu sein
				else if ( $entity_end > $entity_start + 7 ) {
					// und weiter gehts
					$out = $this->clean_string( $out, $entity_start + 1 );
				} // gottcha!
				else {
					$clean = substr( $out, 0, $entity_start );
					$subst = substr( $out, $entity_start + 1, 1 );
					// &scaron; => "s" / &#353; => "_"
					$clean .= ( $subst != "#" ) ? $subst : "_";
					$clean .= substr( $out, $entity_end + 1 );
					// und weiter gehts
					$out = $this->clean_string( $clean, $entity_start + 1 );
				}
			}
		}
		return _wp_specialchars( $out );
	}

	/**
	 * Adds the header for the Video tab in the WordPress SEO meta box on edit post pages.
	 *
	 * @since 0.1
	 */
	public
	function tab_header() {
		global $post;

		$video = wpseo_get_value( 'video_meta', $post->ID );

		if ( !$video || $video == 'none' )
			return;

		$options = get_option( 'wpseo_video' );

		if ( isset( $options['videositemap_posttypes'] ) ) {
			foreach ( $options['videositemap_posttypes'] as $post_type ) {
				if ( $post->post_type == $post_type )
					echo '<li class="video"><a <a class="wpseo_tablink" href="#wpseo_video">' . __( 'Video', 'wordpress-seo' ) . '</a></li>';
			}
		}
	}

	/**
	 * Outputs the content for the Video tab in the WordPress SEO meta box on edit post pages.
	 *
	 * @since 0.1
	 */
	public
	function tab_content() {
		global $post;

		$video = wpseo_get_value( 'video_meta', $post->ID );

		if ( !$video || $video == 'none' )
			return;
		$options = get_option( 'wpseo_video' );

		if ( !isset( $options['videositemap_posttypes'] ) || !in_array( $post->post_type, $options['videositemap_posttypes'] ) )
			return;

		$content = '';
		foreach ( $this->get_meta_boxes() as $meta_box ) {
			$content .= $this->do_meta_box( $meta_box );
		}
		$this->do_tab( 'video', __( 'Video', 'wordpress-seo' ), $content );
	}

	/**
	 * Output a tab in the WP SEO Metabox
	 *
	 * @since 0.2
	 *
	 * @param string $id      CSS ID of the tab.
	 * @param string $heading Heading for the tab.
	 * @param string $content Content of the tab.
	 */
	public
	function do_tab( $id, $heading, $content ) {
		?>
    <div class="wpseotab <?php echo $id ?>">
        <h4 class="wpseo-heading"><?php echo $heading ?></h4>
        <table class="form-table">
			<?php echo $content ?>
        </table>
    </div>
	<?php
	}

	/**
	 * Adds a line in the meta box
	 *
	 * @since 0.2
	 *
	 * @param array $meta_box Contains the vars based on which output is generated.
	 * @return string
	 */
	function do_meta_box( $meta_box ) {
		$content = '';

		if ( !isset( $meta_box['name'] ) ) {
			$meta_box['name'] = '';
		} else {
			$meta_box_value = wpseo_get_value( $meta_box['name'] );
		}

		$class = '';
		if ( !empty( $meta_box['class'] ) )
			$class = ' ' . $meta_box['class'];

		$placeholder = '';
		if ( isset( $meta_box['placeholder'] ) && !empty( $meta_box['placeholder'] ) )
			$placeholder = $meta_box['placeholder'];

		if ( ( !isset( $meta_box_value ) || empty( $meta_box_value ) ) && isset( $meta_box['std'] ) )
			$meta_box_value = $meta_box['std'];

		$content .= '<tr>';
		$content .= '<th scope="row"><label for="yoast_wpseo_' . $meta_box['name'] . '">' . $meta_box['title'] . ':</label></th>';
		$content .= '<td>';

		switch ( $meta_box['type'] ) {
			case "text":
				$ac = '';
				if ( isset( $meta_box['autocomplete'] ) && $meta_box['autocomplete'] == 'off' )
					$ac = 'autocomplete="off" ';
				$content .= '<input type="text" placeholder="' . $placeholder . '" id="yoast_wpseo_' . $meta_box['name'] . '" ' . $ac . 'name="yoast_wpseo_' . $meta_box['name'] . '" value="' . esc_attr( $meta_box_value ) . '" class="large-text"/><br />';
				break;
			case "textarea":
				$content .= '<textarea class="large-text" rows="3" id="yoast_wpseo_' . $meta_box['name'] . '" name="yoast_wpseo_' . $meta_box['name'] . '">' . esc_html( $meta_box_value ) . '</textarea>';
				break;
			case "select":
				$content .= '<select name="yoast_wpseo_' . $meta_box['name'] . '" id="yoast_wpseo_' . $meta_box['name'] . '" class="yoast' . $class . '">';
				foreach ( $meta_box['options'] as $val => $option ) {
					$selected = '';
					if ( $meta_box_value == $val )
						$selected = 'selected="selected"';
					$content .= '<option ' . $selected . ' value="' . esc_attr( $val ) . '">' . $option . '</option>';
				}
				$content .= '</select>';
				break;
			case "multiselect":
				$selectedarr         = explode( ',', $meta_box_value );
				$meta_box['options'] = array( 'none' => 'None' ) + $meta_box['options'];
				$content .= '<select multiple="multiple" size="' . count( $meta_box['options'] ) . '" style="height: ' . ( count( $meta_box['options'] ) * 16 ) . 'px;" name="yoast_wpseo_' . $meta_box['name'] . '[]" id="yoast_wpseo_' . $meta_box['name'] . '" class="yoast' . $class . '">';
				foreach ( $meta_box['options'] as $val => $option ) {
					$selected = '';
					if ( in_array( $val, $selectedarr ) )
						$selected = 'selected="selected"';
					$content .= '<option ' . $selected . ' value="' . esc_attr( $val ) . '">' . $option . '</option>';
				}
				$content .= '</select>';
				break;
			case "checkbox":
				$checked = '';
				if ( $meta_box_value != 'off' )
					$checked = 'checked="checked"';
				$expl = ( isset( $meta_box['expl'] ) ) ? esc_html( $meta_box['expl'] ) : '';
				$content .= '<input type="checkbox" id="yoast_wpseo_' . $meta_box['name'] . '" name="yoast_wpseo_' . $meta_box['name'] . '" ' . $checked . ' class="yoast' . $class . '"/> ' . $expl . '<br />';
				break;
			case "radio":
				if ( $meta_box_value == '' )
					$meta_box_value = $meta_box['std'];
				foreach ( $meta_box['options'] as $val => $option ) {
					$selected = '';
					if ( $meta_box_value == $val )
						$selected = 'checked="checked"';
					$content .= '<input type="radio" ' . $selected . ' id="yoast_wpseo_' . $meta_box['name'] . '_' . $val . '" name="yoast_wpseo_' . $meta_box['name'] . '" value="' . esc_attr( $val ) . '"/> <label for="yoast_wpseo_' . $meta_box['name'] . '_' . $val . '">' . $option . '</label> ';
				}
				break;
			case "divtext":
				$content .= '<p>' . $meta_box['description'] . '</p>';
		}

		if ( isset( $meta_box['description'] ) )
			$content .= '<p>' . $meta_box['description'] . '</p>';

		$content .= '</td>';
		$content .= '</tr>';

		return $content;
	}

	/**
	 * Defines the meta box inputs
	 *
	 * @since 0.1
	 *
	 * @return array $mbs meta box inputs
	 */
	public
	function get_meta_boxes() {
		global $post;

		if ( !isset( $post->ID ) )
			return array();

		$video = wpseo_get_value( 'video_meta', $post->ID );

		if ( !$video || $video == 'none' )
			return array();

		$mbs = array(
			array(
				"name"  => "videositemap-disable",
				"type"  => "checkbox",
				"std"   => 'off',
				"title" => __( "Disable video", "wordpress-seo" ),
				"expl"  => sprintf( __( "Disable video for this %s", "wordpress-seo" ), $post->post_type ),
			),
			array(
				"name"        => "videositemap-thumbnail",
				"type"        => "text",
				"std"         => "",
				"placeholder" => __( "URL to thumbnail image (remember it'll be displayed as 16:9)", "wordpress-seo" ),
				"title"       => __( "Video Thumbnail", "wordpress-seo" )
			),
			array(
				"name"  => "videositemap-not-family-friendly",
				"type"  => "checkbox",
				"std"   => "off",
				"title" => __( "Not Family-friendly", "wordpress-seo" ),
				"expl"  => __( "If this video should only be available for safe search users, check this box.", "wordpress-seo" ),
			),
			array(
				"name"        => "videositemap-rating",
				"type"        => "text",
				"std"         => "",
				"title"       => __( "Rating", "wordpress-seo" ),
				"placeholder" => __( "Between 0 and 5", "wordpress-seo" ),
			)
		);
		return $mbs;
	}

	/**
	 * Save the values from the meta box inputs
	 *
	 * @since 0.1
	 *
	 * @param array $mbs meta boxes to merge the inputs with.
	 * @return array $mbs meta box inputs
	 */
	public
	function save_meta_boxes( $mbs ) {
		$mbs = array_merge( $mbs, $this->get_meta_boxes() );
		return $mbs;
	}

	/**
	 * Replace the default snippet with a video snippet by hooking this function into the wpseo_snippet filter.
	 *
	 * @since 0.1
	 *
	 * @param string $content The original snippet content.
	 * @param object $post    The post object of the post for which the snippet was generated.
	 * @param array  $vars    An array of variables for use within the snippet, containing title, description, date and slug
	 * @return string $content The new video snippet if video metadata was found for the post.
	 */
	public
	function snippet_preview( $content, $post, $vars ) {
		$options = get_option( 'wpseo_video' );

		if ( !isset( $options['videositemap_posttypes'] ) || !in_array( $post->post_type, $options['videositemap_posttypes'] ) )
			return $content;

		$video = wpseo_get_value( 'video_meta', $post->ID );

		$disable = wpseo_get_value( 'videositemap-disable', $post->ID );
		if ( $disable == 'on' )
			return $content;

		if ( !$video || $video == 'none' )
			return $content;

		extract( $vars );

		$videoimg = wpseo_get_value( 'videositemap-thumbnail', $post->ID );
		if ( !$videoimg && isset( $video['thumbnail_loc'] ) )
			$videoimg = $video['thumbnail_loc'];

		if ( is_ssl() )
			$videoimg = str_replace( 'http://', 'https://', $videoimg );

		$duration = $duration_snip = '';
		if ( isset( $video['duration'] ) ) {
			$mins = floor( $video['duration'] / 60 );
			$secs = $video['duration'] - ( $mins * 60 );
			if ( $secs == 0 )
				$secs = '00';
			else if ( $secs < 10 )
				$secs = '0' . $secs;
			$duration = $mins . ':' . $secs;

			if ( $video['duration'] > 60 )
				$duration_snip = number_format( $video['duration'] / 60 ) . ' min';
			else
				$duration_snip = $video['duration'] . ' sec';
		}
		$url = trailingslashit( home_url() ) . $vars['slug'];
		$url = str_replace( 'http://', '', $url );

		$content = '<div id="wpseosnippet">
			<table class="video" cellpadding="0" cellspacing="0">
				<tr>
					<td colspan="2">
						<h4 style="margin:0;font-weight:normal;"><a class="title" target="_blank" href="' . get_permalink( $post->ID ) . '">' . $vars['title'] . '</a></h4>
					</td>
				</tr>
				<tr>
					<td style="padding-right:8px;padding-top:4px; vertical-align:top;" width="1%">
						<div style="position:relative;width:120px;height:65px;overflow:hidden">
							<a href="#" style="text-decoration:none">
								<div style="position:relative;top:-12px">
									<img align="middle" style="display:inline-block;height:90px;margin:0px 0px 0px 0px;width:120px" width="120" height="90" src="' . $videoimg . '"/>
								</div>
								<span style="position:absolute;bottom:0;right:0;text-align:right;font-size:11px;color:#000;background-color:#000;padding:1px 3px;text-decoration:none;font-weight:bold;filter:alpha(opacity=70);-moz-opacity:0.7;-khtml-opacity:0.7;opacity:0.7">&#x25B6;&nbsp;' . $duration . '</span>
								<span style="position:absolute;bottom:0;right:0;text-align:right;font-size:11px;color:#fff;padding:1px 3px;text-decoration:none;font-weight:bold">&#x25B6;&nbsp;' . $duration . '</span>
							</a>
						</div>
					</td>
					<td style="padding-top: 1px; vertical-align: text-top;">
						<div>
							<cite class="url">' . $url . '</cite>
							<p style="color:#666;font-size:13px;line-height:16px;">' . date( 'j M Y', strtotime( $post->post_date ) ) . ' - ' . $duration_snip . '</p>
							<p style="color:#222;font-size:13px;line-height:16px;" class="desc"><span class="content">' . $vars['desc'] . '</span></p>
						</div>
					</td>
				</tr>
			</table>
			<div style="margin-top:7px">';

		return $content;
	}

	/**
	 * Roughly calculate the length of an FLV video.
	 *
	 * @since 1.3.1
	 *
	 * @param string $file The path to the video file to calculate the length for
	 *
	 * @return integer Duration of the video
	 */
	function get_flv_duration( $file ) {
		if ( $flv = fopen( $file, 'rb' ) ) {
			fseek( $flv, -4, SEEK_END );
			$arr             = unpack( 'N', fread( $flv, 4 ) );
			$last_tag_offset = $arr[1];
			fseek( $flv, -( $last_tag_offset + 4 ), SEEK_END );
			fseek( $flv, 4, SEEK_CUR );
			$t0                    = fread( $flv, 3 );
			$t1                    = fread( $flv, 1 );
			$arr                   = unpack( 'N', $t1 . $t0 );
			$milliseconds_duration = $arr[1];
			return $milliseconds_duration;
		} else {
			return 0;
		}
	}

	/**
	 * Restricts the length of the meta description in the snippet preview and throws appropriate warnings.
	 *
	 * @since 0.1
	 *
	 * @param int $length The snippet length as defined by default.
	 * @return int $length The max snippet length for a video snippet.
	 */
	public
	function meta_length( $length ) {
		global $post;

		$video = wpseo_get_value( 'video_meta', $post->ID );

		if ( !$video || $video == 'none' )
			return $length;
		else
			return 115;
	}

	/**
	 * Explains the length restriction of the meta description
	 *
	 * @since 0.1
	 *
	 * @param string $reason Input string.
	 * @return string $reason  The reason why the meta description is limited.
	 */
	public
	function meta_length_reason( $reason ) {
		global $post;

		$video = wpseo_get_value( 'video_meta', $post->ID );

		if ( !$video || $video == 'none' )
			return $reason;
		else
			return __( ' (because it\'s a video snippet)', 'wordpress-seo' );
	}

	/**
	 * Outputs the admin panel for the Video Sitemaps on the XML Sitemaps page with the WP SEO admin
	 *
	 * @since 0.1
	 */
	public
	function admin_panel() {
		$options = get_option( 'wpseo_video' );
		$xmlopt  = get_option( 'wpseo_xml' );
		?>
    <div class="wrap">

        <a href="http://yoast.com/wordpress/video-seo/">
            <div id="yoast-icon"
                 style="background: url('<?php echo WPSEO_URL; ?>images/wordpress-SEO-32x32.png') no-repeat;"
                 class="icon32">
                <br/>
            </div>
        </a>

        <h2 id="wpseo-title"><?php _e( "Yoast WordPress SEO: ", 'wordpress-seo' ); echo __( 'Video SEO Settings', 'wordpress-seo' ); ?></h2>

        <form action="<?php echo admin_url( 'options.php' ); ?>" method="post" id="wpseo-conf">

			<?php

			settings_fields( 'yoast_wpseo_video_options' );

			echo '<h2>' . __( 'License Key' ) . '</h2>';
			echo '<label class="textinput" for="license">' . __( 'License Key', 'wordpress-seo' ) . ':</label> '
				. '<input id="license" class="textinput" type="text" name="wpseo_video[yoast-video-seo-license]" value="'
				. ( isset( $options['yoast-video-seo-license'] ) ? $options['yoast-video-seo-license'] : '' ) . '"/>';

			if ( isset( $options['yoast-video-seo-license'] ) && isset( $options['yoast-video-seo-license-status'] ) &&
				$options['yoast-video-seo-license-status'] == 'valid'
			) {

				if ( !isset( $xmlopt['enablexmlsitemap'] ) ) {
					echo '<p>' . __( 'Please enable the XML sitemap under the SEO -> XML Sitemaps settings', 'wordpress-seo' ) . '</p>';
				} else {
					echo '<br/><br/><a class="button" target="_blank" href="' . $this->sitemap_url() . '">' . __( 'Video Sitemap', 'wordpress-seo' ) . '</a> ';

					if ( !isset( $options['cloak_sitemap'] ) )
						$options['cloak_sitemap'] = false;

					if ( !isset( $options['custom_fields'] ) )
						$options['custom_fields'] = '';

					echo '<h2>' . __( 'General Settings', 'wordpress-seo' ) . '</h2>';
					echo '<p><input class="checkbox double" id="cloak_sitemap" type="checkbox" name="wpseo_video[cloak_sitemap]" ' . checked( $options['cloak_sitemap'] ) . '> ';
					echo '<label for="cloak_sitemap">' . __( 'Hide the sitemap from normal visitors?', 'wordpress-seo' ) . '</label></p>';

					echo '<p><label for="wpseo_video_custom_fields">' . __( 'Custom fields we should check for video content (comma separated):', 'wordpress-seo' ) . '</label><br/>';
					echo '<input type="text" class="textinput" size="100" id="wpseo_video_custom_fields" name="wpseo_video[custom_fields]" value="' . $options['custom_fields'] . '"></p>';

					echo '<h2>' . __( 'Post Types to include in XML Video Sitemap', 'wordpress-seo' ) . '</h2>';
					echo '<p>' . __( 'Determine which post types on your site might contain video.', 'wordpress-seo' ) . '</p>';

					foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $posttype ) {
						$sel = '';
						if ( isset( $options['videositemap_posttypes'] )
							&& is_array( $options['videositemap_posttypes'] )
							&& in_array( $posttype->name, $options['videositemap_posttypes'] )
						)
							$sel = 'checked="checked" ';
						echo '<input class="checkbox double" id="include' . $posttype->name . '" type="checkbox" '
							. 'name="wpseo_video[videositemap_posttypes][' . $posttype->name . ']" ' . $sel . 'value="' . $posttype->name . '"/> '
							. '<label for="include' . $posttype->name . '">' . $posttype->labels->name . '</label><br class="clear">';
					}

					echo '<h2>' . __( 'Taxonomies to include in XML Video Sitemap', 'wordpress-seo' ) . '</h2>';
					echo '<p>' . __( 'You can also include your taxonomy archives, for instance, if you have videos on a category page.', 'wordpress-seo' ) . '</p>';

					foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
						$sel = '';
						if ( isset( $options['videositemap_taxonomies'] )
							&& is_array( $options['videositemap_taxonomies'] )
							&& in_array( $tax->name, $options['videositemap_taxonomies'] )
						)
							$sel = 'checked="checked" ';
						echo '<input class="checkbox double" id="include' . $tax->name . '" type="checkbox" '
							. 'name="wpseo_video[videositemap_taxonomies][' . $tax->name . ']" ' . $sel . 'value="' . $tax->name . '"/> '
							. '<label for="include' . $tax->name . '">' . $tax->labels->name . '</label><br class="clear">';
					}

					echo '<br class="clear"/>';
				}

			} else {

				echo '<p>' . __( 'Insert the license key you got when you bought the plugin, then click save.', 'wordpress-seo' ) . '</p>';
			}

			?>
            <div class="submit">
                <input type="submit" class="button-primary" name="submit"
                       value="<?php _e( "Save Settings", 'wordpress-seo' ); ?>"/>
            </div>
        </form>

		<?php
		if ( isset( $_POST['reindex'] ) ) {
			$this->reindex();
		}
		?>

        <h2><?php _e( 'Indexation of posts', 'wordpress-seo' ); ?></h2>

        <form method="post" action="">
            <input type="checkbox" name="force" id="force"> <label
                for="force"><?php _e( "Force reindex of already indexed video's.", 'wordpress-seo' ); ?></label><br/>
            <br/>
            <input type="submit" class="button" name="reindex"
                   value="<?php _e( 'Re-Index Videos', 'wordpress-seo' ); ?>"/>
        </form>
    </div>
	<?php

	}

	/**
	 * Based on the video type being used, this content filtering function will automatically optimize the embed codes
	 * to allow for proper recognition by search engines.
	 *
	 * This function also, since version 1.2, adds the schema.org videoObject output.
	 *
	 * @link  http://schema.org/VideoObject
	 * @link  https://developers.google.com/webmasters/videosearch/schema
	 *
	 * @since 0.1
	 *
	 * @param string $content The content of the post.
	 * @return string $content The content of the post as modified by the function, if applicable.
	 */
	public
	function content_filter( $content ) {
		global $post, $content_width;

		if ( is_feed() )
			return $content;

		if ( !is_object( $post ) )
			return $content;

		$video = wpseo_get_value( 'video_meta', $post->ID );

		if ( !$video || $video == 'none' )
			return $content;

		$disable = wpseo_get_value( 'videositemap-disable', $post->ID );
		if ( $disable == 'on' )
			return $content;

		if ( !is_numeric( $content_width ) )
			$content_width = 400;

		switch ( $video['type'] ) {
			case 'vimeo':
				$content = str_replace( '<iframe src="http://player.vimeo.com', '<noframes><embed src="http://vimeo.com/moogaloop.swf?clip_id=' . $video['id'] . '" type="application/x-shockwave-flash" width="400" height="300"></embed></noframes><iframe src="http://player.vimeo.com', $content );
				break;
			case 'dailymotion':
				// If dailymotion is embedded using the Viper shortcode, we have to add a noscript version too
				if ( strpos( $content, '<iframe src="http://www.dailymotion' ) === false ) {
					$content = str_replace( '[/dailymotion]', '[/dailymotion]<noscript><iframe src="http://www.dailymotion.com/embed/video/' . $video['id'] . '" width="' . $content_width . '" height="' . floor( $content_width / 1.33 ) . '" frameborder="0"></iframe></noscript>', $content );
				}
				break;
		}

		$desc = trim( wpseo_get_value( 'metadesc', $post->ID ) );
		if ( !$desc || empty( $desc ) ) {
			$desc = trim( substr( $this->strip_shortcodes( $this->strip_tags( $post->post_content ) ), 0, 300 ) );
		}

		if ( empty( $desc ) )
			$desc = $this->strip_tags( get_the_title() );

		$content .= '<span itemprop="video" itemscope itemtype="http://schema.org/VideoObject">';
		$content .= '<meta itemprop="name" content="' . esc_attr( $this->strip_tags( get_the_title() ) ) . '">';
		$content .= '<meta itemprop="thumbnailURL" content="' . esc_attr( $video['thumbnail_loc'] ) . '">';
		$content .= '<meta itemprop="description" content="' . esc_attr( $desc ) . '">';
		$content .= '<meta itemprop="uploadDate" content="' . date( 'c', strtotime( $post->post_date ) ) . '">';
		if ( isset( $video['player_loc'] ) )
			$content .= '<meta itemprop="embedURL" content="' . $video['player_loc'] . '">';
		if ( isset( $video['content_loc'] ) )
			$content .= '<meta itemprop="contentURL" content="' . $video['content_loc'] . '">';
		if ( isset( $video['duration'] ) )
			$content .= '<meta itemprop="duration" content="' . $this->iso_8601_duration( $video['duration'] ) . '">';
		$content .= '</span>';

		return $content;
	}

	/**
	 * A better strip tags that leaves spaces intact (and rips out more code)
	 *
	 * @since 1.3.4
	 *
	 * @link  http://www.php.net/manual/en/function.strip-tags.php#110280
	 *
	 * @param string $string string to strip tags from
	 *
	 * @return string
	 */
	function strip_tags( $string ) {

		// ----- remove HTML TAGs -----
		$string = preg_replace( '/<[^>]*>/', ' ', $string );

		// ----- remove control characters -----
		$string = str_replace( "\r", '', $string ); // --- replace with empty space
		$string = str_replace( "\n", ' ', $string ); // --- replace with space
		$string = str_replace( "\t", ' ', $string ); // --- replace with space

		// ----- remove multiple spaces -----
		$string = trim( preg_replace( '/ {2,}/', ' ', $string ) );

		return $string;

	}

	/**
	 * Convert the duration in seconds to an ISO 8601 compatible output. Assumes the length is not over 24 hours.
	 *
	 * @link http://en.wikipedia.org/wiki/ISO_8601
	 *
	 * @param int $duration The duration in seconds.
	 *
	 * @return string $out ISO 8601 compatible output.
	 */
	function iso_8601_duration( $duration ) {
		$out = 'PT';
		if ( $duration > 3600 ) {
			$hours = floor( $duration / 3600 );
			$out .= $hours . 'H';
			$duration = $duration - ( $hours * 3600 );
		}
		if ( $duration > 60 ) {
			$minutes = floor( $duration / 60 );
			$out .= $minutes . 'M';
			$duration = $duration - ( $minutes * 60 );
		}
		if ( $duration > 0 ) {
			$out .= $duration . 'S';
		}
		return $out;
	}

	/**
	 * Filter the OpenGraph type for the post and sets it to 'video'
	 *
	 * @since 0.1
	 *
	 * @param string $type The type, normally "article"
	 * @return string $type Value 'video'
	 */
	public
	function opengraph_type( $type ) {
		if ( is_singular() ) {
			global $post;

			$video = wpseo_get_value( 'video_meta', $post->ID );

			if ( !$video || $video == 'none' )
				return $type;
			else {
				$disable = wpseo_get_value( 'videositemap-disable', $post->ID );
				if ( $disable == 'on' ) {
					return $type;
				} else {
					return 'video';
				}
			}

		} else if ( is_tax() || is_category() || is_tag() ) {
			$options = get_option( 'wpseo_video' );

			$term = get_queried_object();

			if ( isset( $options['videositemap_taxonomies'] )
				&& is_array( $options['videositemap_taxonomies'] )
				&& in_array( $term->taxonomy, $options['videositemap_taxonomies'] )
			) {
				$tax_meta = get_option( 'wpseo_taxonomy_meta' );
				if ( isset( $tax_meta[$term->taxonomy]['_video'][$term->term_id] ) ) {
					return 'video';
				}
			}

		}

		return $type;
	}

	/**
	 * Filter the OpenGraph image for the post and sets it to the video thumbnail
	 *
	 * @since 0.1
	 *
	 * @param string $image URL to the image
	 * @return string $image URL to the video thumbnail image
	 */
	public
	function opengraph_image( $image ) {
		if ( is_singular() ) {
			global $post;

			$video = wpseo_get_value( 'video_meta', $post->ID );

			if ( !$video || $video == 'none' )
				return $image;

			$disable = wpseo_get_value( 'videositemap-disable', $post->ID );
			if ( $disable == 'on' )
				return $image;

			return $video['thumbnail_loc'];
		} else if ( is_tax() || is_category() || is_tag() ) {
			$options = get_option( 'wpseo_video' );

			$term = get_queried_object();

			if ( isset( $options['videositemap_taxonomies'] )
				&& is_array( $options['videositemap_taxonomies'] )
				&& in_array( $term->taxonomy, $options['videositemap_taxonomies'] )
			) {
				$tax_meta = get_option( 'wpseo_taxonomy_meta' );
				if ( isset( $tax_meta[$term->taxonomy]['_video'][$term->term_id] ) ) {
					$video = $tax_meta[$term->taxonomy]['_video'][$term->term_id];
					return $video['thumbnail_loc'];
				}
			}
		}
		return $image;
	}

	/**
	 * Add OpenGraph video info if present
	 *
	 * @since 0.1
	 */
	public
	function opengraph() {
		if ( is_singular() ) {
			global $post;

			$video = wpseo_get_value( 'video_meta', $post->ID );

			if ( !$video || $video == 'none' )
				return false;

			$disable = wpseo_get_value( 'videositemap-disable', $post->ID );
			if ( $disable == 'on' )
				return false;
		} else if ( is_tax() || is_category() || is_tag() ) {
			$options = get_option( 'wpseo_video' );

			$term = get_queried_object();

			if ( isset( $options['videositemap_taxonomies'] )
				&& is_array( $options['videositemap_taxonomies'] )
				&& in_array( $term->taxonomy, $options['videositemap_taxonomies'] )
			) {
				$tax_meta = get_option( 'wpseo_taxonomy_meta' );
				if ( isset( $tax_meta[$term->taxonomy]['_video'][$term->term_id] ) ) {
					$video = $tax_meta[$term->taxonomy]['_video'][$term->term_id];
				} else {
					return false;
				}
			}
		} else {
			return false;
		}

		if ( isset( $video['player_loc'] ) )
			echo '<meta property="og:video" content="' . $video['player_loc'] . '" />' . "\n";

		echo '<meta name="medium" content="video">' . "\n";
		echo '<meta name="video_type" content="application/x-shockwave-flash" />' . "\n";
		echo '<link rel="image_src" href="' . $video['thumbnail_loc'] . '">' . "\n";
		if ( isset( $video['player_loc'] ) )
			echo '<link rel="video_src" href="' . $video['player_loc'] . '">' . "\n";

	}

	/**
	 * Make the get_terms query only return terms with a non-empty description.
	 *
	 * @since 1.3
	 *
	 * @param $pieces array The separate pieces of the terms query to filter.
	 * @return mixed
	 */
	public function filter_terms_clauses( $pieces ) {
		$pieces['where'] .= " AND tt.description != ''";
		return $pieces;
	}

	/**
	 * Reindex the video info from posts
	 *
	 * @since 0.1
	 */
	public function reindex() {
		require_once ABSPATH . '/wp-admin/includes/media.php';

		echo "<strong>" . __( "Reindex starts....", "wordpress-seo" ) . "</strong><br/>";

		$options = get_option( 'wpseo_video' );

		if ( isset( $options['videositemap_posttypes'] ) ) {
			$args = array(
				'post_type'   => $options['videositemap_posttypes'],
				'post_status' => 'publish',
				'numberposts' => 100,
				'offset'      => 0,
			);

			global $wp_version;
			if ( !isset( $_POST['force'] ) ) {
				if ( version_compare( $wp_version, '3.5', ">=" ) ) {
					$args['meta_query'] = array(
						'key'     => '_yoast_wpseo_video_meta',
						'compare' => 'NOT EXISTS'
					);
				}
			}

			$post_count_total = 0;
			foreach ( $options['videositemap_posttypes'] as $post_type ) {
				$post_count_total += wp_count_posts( $post_type )->publish;
			}

			while ( $post_count_total > $args['offset'] ) {
				$results = get_posts( $args );

				echo "<br/><strong>" . sprintf( __( "Found %d posts to search through", "wordpress-seo" ), count( $results ) ) . "</strong><br/><br/>";

				foreach ( $results as $post ) {
					$this->update_video_post_meta( $post, true );
					flush();
				}
				$args['offset'] += 99;
			}
		}

		// Get all the non-empty terms.
		add_filter( 'terms_clauses', array( $this, 'filter_terms_clauses' ) );
		$terms = get_terms( $options['videositemap_taxonomies'] );
		remove_filter( 'terms_clauses', array( $this, 'filter_terms_clauses' ) );

		echo "<br/><strong>" . sprintf( __( "Found %d terms to search through", "wordpress-seo" ), count( $terms ) ) . "</strong><br/><br/>";

		foreach ( $terms as $term ) {
			$this->update_video_term_meta( $term, true );
			flush();
		}

		$this->update_sitemap();

		echo "<br/><strong>" . __( 'Reindex completed.', 'wordpress-seo' ) . "</strong>";
	}

	/**
	 * Filter the Page Analysis results to make sure we're giving the correct hints.
	 *
	 * @since 1.4
	 *
	 * @param array  $results The results array to filter and update.
	 * @param array  $job     The current jobs variables.
	 * @param object $post    The post object for the current page.
	 *
	 * @return array $results
	 */
	function filter_linkdex_results( $results, $job, $post ) {
		$video = wpseo_get_value( 'video_meta', $post->ID );
		if ( !$video || $video == 'none' )
			return $results;

		$disable = wpseo_get_value( 'videositemap-disable', $post->ID );
		if ( $disable == 'on' )
			return $results;

		if ( stripos( $job['title'], __( 'video', 'wordpress-seo' ) ) === false ) {
			$results['title_video'] = array(
				'val' => 6,
				'msg' => __( 'You should consider adding the word "video" in your title, to optimize your ability to be found by people searching for video.', 'wordpress-seo' )
			);
		} else {
			$results['title_video'] = array(
				'val' => 9,
				'msg' => __( 'You\'re using the word "video" in your title, this optimizes your ability to be found by people searching for video.', 'wordpress-seo' )
			);
		}

		if ( $results['body_length']['raw'] > 150 && $results['body_length']['raw'] < 400 ) {
			$results['body_length'] = array(
				'val' => 9,
				'msg' => __( 'Your body copy is optimal length for your video to be recognized by Google.', 'wordpress-seo' )
			);
		} else if ( $results['body_length']['raw'] < 150 ) {
			$results['body_length'] = array(
				'val' => 6,
				'msg' => __( 'Your body copy is too short for Google to understand the topic of your video, add some more content describing the contents of the video.', 'wordpress-seo' )
			);
		} else {
			$results['body_length'] = array(
				'val' => 6,
				'msg' => sprintf( __( 'Your body copy is quite long, make sure that the video is the most important asset on the page, read %1$sthis post%2$s for more info.', 'wordpress-seo' ), '<a href="http://yoast.com/video-not-showing-search-results/">', '</a>' )
			);
		}

		return $results;
	}
}

/**
 * Throw an error if WordPress SEO is not installed.
 *
 * @since 0.2
 */
function yoast_wpseo_missing_error() {
	echo '<div class="error"><p>Please <a href="' . admin_url( 'plugin-install.php?tab=search&type=term&s=wordpress+seo&plugin-search-input=Search+Plugins' ) . '">install &amp; activate WordPress SEO by Yoast</a> and then enable its XML sitemap functionality to allow the Video SEO module to work.</p></div>';
}

/**
 * Initialize the Video SEO module on plugins loaded, so WP SEO should have set its constants and loaded its main classes.
 *
 * @since 0.2
 */
function yoast_wpseo_video_seo_init() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		$wpseo_video_xml = new wpseo_Video_Sitemap();
	} else {
		add_action( 'all_admin_notices', 'yoast_wpseo_missing_error' );
	}
}

add_action( 'plugins_loaded', 'yoast_wpseo_video_seo_init' );