<?php
/*
Plugin Name: Local SEO for WordPress SEO by Yoast
Version: 1.1.1
Plugin URI: http://yoast.com/wordpress/local-seo/
Description: This Video SEO module adds XML Video sitemap capabalities to the XML sitemap capabilities of WordPress SEO.
Author: Joost de Valk and Arjan Snaterse
Author URI: http://yoast.com

Copyright 2012-2013 Joost de Valk & Arjan Snaterse

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

/**
 * All functionality for fetching location data and creating an KML file with it.
 *
 * @package    WordPress SEO
 * @subpackage WordPress SEO Local
 */

$options = get_option('wpseo_local');
if ( isset( $options['license'] ) && !empty( $options['license'] ) ) {
	if ( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
		// load our custom updater
		include( dirname( __FILE__ ) . '/includes/EDD_SL_Plugin_Updater.php' );
	}

	$edd_updater = new EDD_SL_Plugin_Updater( 'http://yoast.com', __FILE__, array(
			'version'     => '1.1.1', // current version number
			'license'     => trim( $options['license'] ), // license key (used get_option above to retrieve from DB)
			'item_name'   => 'Local SEO for WordPress', // name of this plugin
			'author'      => 'Joost de Valk' // author of this plugin
		)
	);
}

if ( !defined('WPSEO_LOCAL_URL') )
	define( 'WPSEO_LOCAL_URL', plugin_dir_url( __FILE__ ) );
if ( !defined('WPSEO_LOCAL_PATH') )
	define( 'WPSEO_LOCAL_PATH', plugin_dir_path( __FILE__ ) );
if ( !defined('WPSEO_LOCAL_BASENAME') )
	define( 'WPSEO_LOCAL_BASENAME', plugin_basename( __FILE__ ) );

define( 'WPSEO_LOCAL_FILE', __FILE__ );

load_plugin_textdomain( 'yoast-local-seo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

/**
 * Initialize the Local SEO module on plugins loaded, so WP SEO should have set its constants and loaded its main classes.
 *
 * @since 0.2
 */
function wpseo_local_seo_init() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		require_once 'includes/wpseo-local-functions.php';
		require_once 'classes/wpseo-local-admin.class.php';
		require_once 'classes/wpseo-local-frontend.class.php';
		require_once 'widgets/widget-show-address.php';
		require_once 'widgets/widget-show-map.php';
		require_once 'widgets/widget-show-openinghours.php';
		
		$WPSEO_Local_Search_Admin = new WPSEO_Local_Search_Admin();
		$WPSEO_Frontend_Local = new WPSEO_Frontend_Local();
	}
	else {
		add_action( 'all_admin_notices', 'wpseo_local_missing_error' );
	}
}
add_action( 'plugins_loaded', 'wpseo_local_seo_init' );

/**
 * Throw an error if WordPress SEO is not installed.
 *
 * @since 0.2
 */
function wpseo_local_missing_error() {
	echo '<div class="error"><p>Please <a href="' . admin_url( 'plugin-install.php?tab=search&type=term&s=wordpress+seo&plugin-search-input=Search+Plugins' ) . '">install &amp; activate WordPress SEO by Yoast</a> and then go to the Local SEO section to enable the Local SEO module to work.</p></div>';
}