<?php

/**
 * WPSEO_Local_Search_Admin class.
 *
 * @package WordPress SEO Local
 * @since   1.0
 */
if ( !class_exists( 'WPSEO_Frontend_Local' ) ) {
	class WPSEO_Local_Search_Admin {

		var $options = array();
		var $days = array();

		/**
		 * Constructor for the WPSEO_Local_Search_Admin class.
		 *
		 * @since 1.0
		 */
		function __construct() {

			$this->options = get_option( "wpseo_local" );
			$this->days    = array(
				'monday'    => __( 'Monday' ),
				'tuesday'   => __( 'Tuesday' ),
				'wednesday' => __( 'Wednesday' ),
				'thursday'  => __( 'Thursday' ),
				'friday'    => __( 'Friday' ),
				'saturday'  => __( 'Saturday' ),
				'sunday'    => __( 'Sunday' ),
			);

			if ( wpseo_has_multiple_locations() )
				add_action( 'init', array( &$this, 'create_custom_post_type' ), 10, 1 );

			if ( is_admin() ) {
				add_action( 'update_option_wpseo_local', array( $this, 'activate_license' ) );

				add_action( 'admin_init', array( $this, 'init' ) );
				add_action( 'wpseo_import', array( &$this, 'import_panel' ), 10, 1 );

				add_action( 'admin_init', array( &$this, 'options_init' ) );
				add_action( 'admin_menu', array( &$this, 'register_settings_page' ), 20 );
				add_action( 'admin_footer', array( $this, 'config_page_footer' ) );

				add_action( 'admin_print_styles', array( &$this, 'config_page_styles' ) );
				add_action( 'admin_print_scripts', array( &$this, 'config_page_scripts' ) );

				// Create custom post type functionality + meta boxes for Custom Post Type
				add_action( 'save_post', array( &$this, 'wpseo_locations_save_meta' ), 1, 2 ); // save the custom fields

				add_filter( 'wpseo_linkdex_results', array( &$this, 'filter_linkdex_results' ), 10, 3 );
			} else {
				// XML Sitemap Index addition
				add_action( 'init', array( $this, 'init' ) );
				add_filter( 'wpseo_sitemap_index', array( $this, 'add_to_index' ) );
			}
		}

		/**
		 * Registers the settings page in the WP SEO menu
		 *
		 * @since 1.0
		 */
		function register_settings_page() {
			// TODO: hardcoded manage_options here, which should be properly inherited from WPSEO_Admin class later on
			add_submenu_page( 'wpseo_dashboard', 'Local SEO', 'Local SEO', 'manage_options', 'wpseo_local', array( &$this, 'admin_panel' ) );
		}

		/**
		 * Registers the wpseo_local setting for Settings API
		 *
		 * @since 1.0
		 */
		function options_init() {
			register_setting( 'yoast_wpseo_local_options', 'wpseo_local' );
		}

		/**
		 * See if there's a license to activate
		 *
		 * @since 1.0
		 */
		function activate_license() {
			$options = get_option( 'wpseo_local' );

			if ( !isset( $options['license'] ) || empty( $options['license'] ) ) {
				unset( $options['license'] );
				unset( $options['license-status'] );
				update_option( 'wpseo_local', $options );
				return;
			}

			if ( 'valid' == $options['license-status'] ) {
				return;
			} else if ( isset( $options['license'] ) ) {
				// data to send in our API request
				$api_params = array(
					'edd_action' => 'activate_license',
					'license'    => $options['license'],
					'item_name'  => urlencode( 'Local SEO for WordPress' ) // the name of our product in EDD
				);

				// Call the custom API.
				$url      = add_query_arg( $api_params, 'http://yoast.com/' );
				$args     = array(
					'timeout' => 25,
					'rand'    => rand( 1000, 9999 )
				);
				$response = wp_remote_get( $url, $args );

				if ( is_wp_error( $response ) ) {
					return;
				}

				// decode the license data
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );

				// $license_data->license will be either "valid" or "invalid"
				$options['license-status'] = $license_data->license;
				update_option( 'wpseo_local', $options );
			}
		}

		/**
		 * Loads some CSS
		 *
		 * @since 1.0
		 */
		function config_page_styles() {
			global $pagenow;
			if ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] == 'wpseo_local' ) {
				wp_enqueue_style( 'yoast-admin-css', WPSEO_URL . 'css/yst_plugin_tools.css', WPSEO_VERSION );
			}
		}

		/**
		 * Enqueues the (tiny) global JS needed for the plugin.
		 */
		function config_page_scripts() {
			wp_enqueue_script( 'wpseo-local-global-script', WPSEO_LOCAL_URL . 'js/wp-seo-local-global.js', array( 'jquery' ), WPSEO_VERSION, true );
			global $pagenow, $post;
			if ( ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] == 'wpseo_local' ) || ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $post->post_type == 'wpseo_locations' ) ) {
				wp_enqueue_script( 'jquery-chosen', WPSEO_LOCAL_URL . 'js/chosen.jquery.min.js', array( 'jquery' ), WPSEO_VERSION, true );
				wp_enqueue_style( 'jquery-chosen-css', WPSEO_LOCAL_URL . 'js/chosen.css', WPSEO_VERSION );
			}
		}

		/**
		 * Print the required JavaScript in the footer
		 */
		function config_page_footer() {
			global $pagenow, $post;
			if ( ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] == 'wpseo_local' ) || ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $post->post_type == 'wpseo_locations' ) ) {
				?>
				<script>
					jQuery(document).ready(function ($) {
						$(".chzn-select").chosen();
					});
				</script>
			<?php
			}
		}

		/**
		 * Adds the rewrite for the Geo sitemap and KML file
		 *
		 * @since 1.0
		 */
		public function init() {

			if ( isset( $GLOBALS['wpseo_sitemaps'] ) ) {
				$GLOBALS['wpseo_sitemaps']->register_sitemap( 'wpseo_local', array( $this, 'build_local_sitemap' ), 'geo_sitemap\.xml$' );
				$GLOBALS['wpseo_sitemaps']->register_sitemap( 'wpseo_local_kml', array( $this, 'build_kml' ), 'locations\.kml$' );
			}
		}

		/**
		 * Adds the Geo Sitemap to the Index Sitemap.
		 *
		 * @since 1.0
		 *
		 * @param $str string String with the filtered additions to the index sitemap in it.
		 * @return string $str string String with the local XML sitemap additions to the index sitemap in it.
		 */
		public function add_to_index( $str ) {
			$date = get_option( 'wpseo_local_xml_update' );

			if ( !$date || $date == '' ) {
				$date = date( 'c' );
			}

			$str .= '<sitemap>' . "\n";
			$str .= '<loc>' . home_url( 'geo_sitemap.xml' ) . '</loc>' . "\n";
			$str .= '<lastmod>' . $date . '</lastmod>' . "\n";
			$str .= '</sitemap>' . "\n";
			return $str;
		}

		/**
		 * Pings Google with the (presumeably updated) Geo Sitemap.
		 *
		 * @since 1.0
		 */
		private function ping() {
			// Ping Google. Just do it. 
			wp_remote_get( 'http://www.google.com/webmasters/tools/ping?sitemap=' . home_url( 'geo_sitemap.xml' ) );
		}

		/**
		 * Updates the last update time transient for the local sitemap and pings Google with the sitemap.
		 *
		 * @since 1.0
		 */
		private function update_sitemap() {
			update_option( 'wpseo_local_xml_update', date( 'c' ) );
			$this->ping();
		}


		/**
		 * This function generates the Geo sitemap's contents.
		 *
		 * @since 1.0
		 */
		public function build_local_sitemap() {
			// Build entry for Geo Sitemap
			$output = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:geo="http://www.google.com/geo/schemas/sitemap/1.0">
				<url>
					<loc>' . get_site_url() . '/locations.kml</loc>
					<lastmod>' . date( 'c' ) . '</lastmod>
					<priority>1</priority>
				</url>
			</urlset>';

			if ( isset( $GLOBALS['wpseo_sitemaps'] ) ) {
				$GLOBALS['wpseo_sitemaps']->set_sitemap( $output );
				$GLOBALS['wpseo_sitemaps']->set_stylesheet( '<?xml-stylesheet type="text/xsl" href="' . dirname( plugin_dir_url( __FILE__ ) ) . '/styles/geo-sitemap.xsl"?>' );
			}
		}

		/**
		 * This function generates the KML file contents.
		 *
		 * @since 1.0
		 */
		public function build_kml() {
			$location_data = $this->get_location_data();
			$errors        = array();

			if ( isset( $location_data["businesses"] ) && is_array( $location_data["businesses"] ) && count( $location_data["businesses"] ) > 0 ) {
				$kml_output = "<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
				$kml_output .= "\t<Document>\n";
				$kml_output .= "\t\t<name>" . ( !empty( $location_data['kml_name'] ) ? $location_data['kml_name'] : " Locations for " . $location_data['business_name'] ) . "</name>\n";

				if ( !empty( $location_data->author ) ) {
					$kml_output .= "\t\t<atom:author>\n";
					$kml_output .= "\t\t\t<atom:name>" . $location_data['author'] . "</atom:name>\n";
					$kml_output .= "\t\t</atom:author>\n";
				}
				if ( !empty( $location_data_fields["business_website"] ) ) {
					$kml_output .= "\t\t<atom:link href=\"" . $location_data['website'] . "\" />\n";
				}

				$kml_output .= "\t\t<open>1</open>\n";
				$kml_output .= "\t\t<Folder>\n";

				foreach ( $location_data['businesses'] as $key => $business ) {
					if ( !empty( $business ) ) {
						$business_name        = htmlentities( $business['business_name'] );
						$business_description = !empty( $business->business_description ) ? $business->business_description : "";
						$business_description = htmlentities( $business_description );
						$business_url         = $business['business_url'];
						if( wpseo_has_multiple_locations() && !empty( $business['post_id'] ) ) 
							$business_url = get_permalink( $business['post_id'] );
						
						foreach ( $business as $meta_key => $value ) {
							$business['_wpseo_' . $meta_key] = $value;
							unset( $business[$meta_key] );
						}

						$business_geo_data = $this->get_geo_data( $business, false, isset( $business['post_id'] ) ? $business['post_id'] : null );
						if ( $business_geo_data ) {
							$business['full_address']  = $business_geo_data['full_address'];
							$business['_wpseo_coords'] = $business_geo_data['coords'];
						}

						$kml_output .= "\t\t\t<Placemark>\n";
						$kml_output .= "\t\t\t\t<name><![CDATA[" . $business_name . "]]></name>\n";
						$kml_output .= "\t\t\t\t<address><![CDATA[" . $business["full_address"] . "]]></address>\n";
						$kml_output .= "\t\t\t\t<description><![CDATA[" . $business_description . "]]></description>\n";
						$kml_output .= "\t\t\t\t<atom:link href=\"" . $business_url . "\"/>\n";
						$kml_output .= "\t\t\t\t<LookAt>\n";
						$kml_output .= "\t\t\t\t\t<latitude>" . $business["_wpseo_coords"]["lat"] . "</latitude>\n";
						$kml_output .= "\t\t\t\t\t<longitude>" . $business["_wpseo_coords"]["long"] . "</longitude>\n";
						$kml_output .= "\t\t\t\t\t<altitude>1500</altitude>\n";
						$kml_output .= "\t\t\t\t\t<range></range>\n";
						$kml_output .= "\t\t\t\t\t<tilt>0</tilt>\n";
						$kml_output .= "\t\t\t\t\t<heading></heading>\n";
						$kml_output .= "\t\t\t\t\t<altitudeMode>relativeToGround</altitudeMode>\n";
						$kml_output .= "\t\t\t\t</LookAt>\n";
						$kml_output .= "\t\t\t\t<Point>\n";
						$kml_output .= "\t\t\t\t\t<coordinates>" . $business["_wpseo_coords"]["long"] . "," . $business["_wpseo_coords"]["lat"] . ",0</coordinates>\n";
						$kml_output .= "\t\t\t\t</Point>\n";
						$kml_output .= "\t\t\t</Placemark>\n";
					}
				}

				$kml_output .= "\t\t</Folder>\n";
				$kml_output .= "\t</Document>\n";
				$kml_output .= "</kml>\n";

				if ( isset( $GLOBALS['wpseo_sitemaps'] ) ) {
					$GLOBALS['wpseo_sitemaps']->set_sitemap( $kml_output );
					$GLOBALS['wpseo_sitemaps']->set_stylesheet( '<?xml-stylesheet type="text/xsl" href="' . dirname( plugin_dir_url( __FILE__ ) ) . '/styles/kml-file.xsl"?>' );
				}
			}

			return $location_data;
		}

		/**
		 * Builds an array based upon the data from the wpseo_locations post type. This data is needed as input for the Geo sitemap & KML API.
		 *
		 * @since 1.0
		 */
		function get_location_data() {
			$locations               = array();
			$locations["businesses"] = array();

			if ( wpseo_has_multiple_locations() ) {
				$posts = get_posts( array(
					'post_type'      => 'wpseo_locations',
					'posts_per_page' => -1
				) );

				foreach ( $posts as $post ) {
					$business = array(
						"business_name"        => get_the_title( $post->ID ),
						"business_address"     => get_post_meta( $post->ID, '_wpseo_business_address', true ),
						"business_city"        => get_post_meta( $post->ID, '_wpseo_business_city', true ),
						"business_state"       => get_post_meta( $post->ID, '_wpseo_business_state', true ),
						"business_zipcode"     => get_post_meta( $post->ID, '_wpseo_business_zipcode', true ),
						"business_country"     => get_post_meta( $post->ID, '_wpseo_business_country', true ),
						"business_phone"       => get_post_meta( $post->ID, '_wpseo_business_phone', true ),
						"business_fax"         => get_post_meta( $post->ID, '_wpseo_business_fax', true ),
						"business_email"       => get_post_meta( $post->ID, '_wpseo_business_email', true ),
						"business_description" => !empty( $post->post_excerpt ) ? $post->post_excerpt : substr( strip_tags( $post->post_content ), 0, 250 ),
						"business_url"         => get_permalink( $post->ID ),
						"coords"               => array(
							'lat'  => get_post_meta( $post->ID, '_wpseo_coordinates_lat', true ),
							'long' => get_post_meta( $post->ID, '_wpseo_coordinates_long', true )
						),
						"post_id"			   => $post->ID
					);
					array_push( $locations["businesses"], $business );
				}
			} else {
				$options = get_option( 'wpseo_local' );

				$business = array(
					"business_name"        => $options['location_name'],
					"business_address"     => $options['location_address'],
					"business_city"        => $options['location_city'],
					"business_state"       => $options['location_state'],
					"business_zipcode"     => $options['location_zipcode'],
					"business_country"     => $options['location_country'],
					"business_phone"       => $options['location_phone'],
					"business_fax"         => $options['location_fax'],
					"business_email"       => $options['location_email'],
					"business_description" => get_option( "blogname" ) . ' - ' . get_option( "blogdescription" ),
					"business_url"         => get_site_url(),
					"coords"               => array(
						'lat'  => $options['location_coords_lat'],
						'long' => $options['location_coords_long'],
					)
				);
				array_push( $locations["businesses"], $business );
			}

			$locations["business_name"] = get_option( "blogname" );
			$locations["kml_name"]      = "Locations for " . $locations["business_name"] . ".";
			$locations["kml_url"]       = get_site_url() . '/locations.kml';
			$locations["kml_website"]   = get_site_url();
			$locations["author"]        = get_option( "blogname" );

			return $locations;
		}

		/**
		 * Retrieves the lat/long coordinates from the Google Maps API
		 *
		 * @param Array $location_info Array with location info. Array structure: array( _wpseo_business_address, _wpseo_business_city, _wpseo_business_state, _wpseo_business_zipcode, _wpseo_business_country )
		 * @param bool  $force_update  Whether to force the update or not
		 *
		 * @return bool|array Returns coordinates in array ( Format: array( 'lat', 'long' ) ). False when call the Maps API did not succeed
		 */
		public function get_geo_data( $location_info, $force_update = false, $post_id = null ) {
			$full_address = $location_info['_wpseo_business_address'] . ', ' . wpseo_local_get_address_format( $location_info['_wpseo_business_zipcode'], $location_info['_wpseo_business_city'], $location_info['_wpseo_business_state'], true, false, false ) . ', ' . WPSEO_Frontend_Local::get_country( $location_info['_wpseo_business_country'] );

			$coordinates = array();
			
			if ( $force_update || empty( $location_info['_wpseo_coords']['lat'] ) || empty( $location_info['_wpseo_coords']['long'] ) ) {
				$response = wp_remote_get( "http://maps.google.com/maps/api/geocode/json?address=" . urlencode( $full_address ) . "&oe=utf8&sensor=false" );

				if ( is_wp_error( $response ) || ( $response['response']['code'] != 200 && !empty( $response['body'] ) ) )
					return false;

				$results = json_decode( $response['body'] );
				if ( isset( $results->results[0] ) && !empty( $results->results[0] ) ) {
					$coordinates['lat']  = $results->results[0]->geometry->location->lat;
					$coordinates['long'] = $results->results[0]->geometry->location->lng;

					if( wpseo_has_multiple_locations() ) {
						
						update_post_meta( $post_id, '_wpseo_coordinates_lat', $coordinates['lat'] );
						update_post_meta( $post_id, '_wpseo_coordinates_long', $coordinates['long'] );
					}
					else {
						$options = get_option( 'wpseo_local' );
						$options['location_coords_lat'] = $coordinates['lat'];
						$options['location_coords_long'] = $coordinates['long'];

						update_option( 'wpseo_local', $options );
					}
				}
			} else {
				$coordinates['lat']  = $location_info['_wpseo_coords']['lat'];
				$coordinates['long'] = $location_info['_wpseo_coords']['long'];
			}

			$return_array['coords']       = $coordinates;
			$return_array["full_address"] = $full_address;

			return $return_array;
		}

		/**
		 * Builds the local admin page
		 */
		public function admin_panel() {
			$options = $this->options;
			$options = wp_parse_args( (array) $options, array(
				'enablexmlgeositemap'    => false,
				'locations_slug'         => 'locations',
				'load_jquery'            => false,
				'address_format'         => '',
				'use_multiple_locations' => false,
				'location_name'          => '',
				'location_address'       => '',
				'location_city'          => '',
				'location_state'         => '',
				'location_zipcode'       => '',
				'location_country'       => '',
				'location_phone'         => '',
				'location_fax'           => '',
				'location_email'         => '',
				'location_coords_lat'    => '',
				'location_coords_long'   => '',
				'opening_hours_24h'      => false,
				'multiple_opening_hours' => false
			) );

			if ( isset( $_GET['deactivate'] ) && 'true' == $_GET['deactivate'] ) {

				if ( wp_verify_nonce( $_GET['nonce'], 'yoast_local_seo_deactivate_license' ) === false )
					return;

				// data to send in our API request
				$api_params = array(
					'edd_action' => 'deactivate_license',
					'license'    => $options['license'],
					'item_name'  => urlencode( 'Local SEO for WordPress' )
				);

				// Send the remote request
				$url = add_query_arg( $api_params, 'http://yoast.com/' );

				$response = wp_remote_get( $url, array( 'timeout' => 25, 'sslverify' => false ) );

				if ( !is_wp_error( $response ) ) {
					$response = json_decode( $response['body'] );

					if ( 'deactivated' == $response->license || 'failed' == $response->license ) {
						unset( $options['license'] );
						$options['license-status'] = 'invalid';
						update_option( 'wpseo_local', $options );
					}
				}

				echo '<script type="text/javascript">document.location = "' . admin_url( 'admin.php?page=wpseo_local' ) . '"</script>';
			}

			if ( isset( $_GET['settings-updated'] ) )
				flush_rewrite_rules();
			?>
			<div class="wrap">

				<a href="http://yoast.com/wordpress/local-seo/">
					<div id="yoast-icon"
						 style="background: url('<?php echo WPSEO_URL; ?>images/wordpress-SEO-32x32.png') no-repeat;"
						 class="icon32"><br/></div>
				</a>

				<h2 id="wpseo-title"><?php _e( "Yoast WordPress SEO: ", 'yoast-local-seo' ); _e( 'Local SEO Settings', 'yoast-local-seo' ); ?></h2>

				<form action="<?php echo admin_url( 'options.php' ); ?>" method="post" id="wpseo-conf">

					<?php

					settings_fields( 'yoast_wpseo_local_options' );

					$license_active = false;
					if ( isset( $options['license-status'] ) && $options['license-status'] == 'valid' )
						$license_active = true;

					echo '<h2>' . __( 'License', 'yoast-local-seo' ) . '</h2>';
					echo '<label class="textinput" for="license">' . __( 'License Key', 'yoast-local-seo' ) . ':</label> '
						. '<input id="license" class="textinput" type="text" name="wpseo_local[license]" value="'
						. ( isset( $options['license'] ) ? $options['license'] : '' ) . '"/><br/>';
					echo '<p class="clear description">' . __( 'License Status', 'yoast-local-seo' ) . ': ' . ( ( $license_active ) ? '<span style="color:#090; font-weight:bold">' . __( 'active', 'yoast-local-seo' ) . '</span>' : '<span style="color:#f00; font-weight:bold">' . __( 'inactive', 'yoast-local-seo' ) . '</span>' ) . '</p>';
					echo '<input type="hidden" name="wpseo_local[license-status]" value="' . ( ( $license_active ) ? 'valid' : 'invalid' ) . '"/>';

					if ( $license_active ) {
						echo '<div>';
						echo '<p><a href="' . admin_url( 'admin.php?page=wpseo_local&deactivate=true&nonce=' . wp_create_nonce( 'yoast_local_seo_deactivate_license' ) ) . '" class="button">' . __( 'Deactivate License', 'yoast-local-seo' ) . '</a></p>';
						echo '<p class="clear description">' . __( 'If you want to stop this site from counting towards your license limit, should you have one, simply press deactivate license above.', 'yoast-local-seo' ) . '</p>';
						echo '</div>';

						echo '<h2>' . __( 'Local SEO Settings', 'yoast-local-seo' ) . '</h2>';

						echo '<div style=" overflow: hidden; ">';
						echo '<label for="load_jquery" class="checkbox">' . __( 'Load jQuery', 'yoast-local-seo' ) . ':</label>';
						echo '<input class="checkbox" id="load_jquery" type="checkbox" name="wpseo_local[load_jquery]" value="1" ' . checked( '1', $options['load_jquery'], false ) . '> ';
						echo ' <span style="float: left; margin: 10px 0 0 5px;">' . __( 'Enable this when jQuery is not loaded yet in your theme.', 'yoast-local-seo' ) . '</span>';
						echo '</div>';
						echo '<br class="clear">';

						echo '<div>';
						echo '<label for="address_format" class="checkbox">' . __( 'Address format', 'yoast-local-seo' ) . ':</label>';
						echo '<select class="textinput" id="address_format" name="wpseo_local[address_format]">';
						echo '<option value="address-state-postal" ' . selected( 'address-state-postal', $options['address_format'], false ) . '>{city}, {state} {zipcode} &nbsp;&nbsp;&nbsp;&nbsp; (New York, NY 12345 )</option>';
						echo '<option value="address-state-postal-comma" ' . selected( 'address-state-postal-comma', $options['address_format'], false ) . '>{city}, {state}, {zipcode} &nbsp;&nbsp;&nbsp;&nbsp; (New York, NY, 12345 )</option>';
						echo '<option value="address-postal" ' . selected( 'address-postal', $options['address_format'], false ) . '>{city} {zipcode} &nbsp;&nbsp;&nbsp;&nbsp; (New York 12345 )</option>';
						echo '<option value="address-postal-comma" ' . selected( 'address-postal-comma', $options['address_format'], false ) . '>{city}, {zipcode} &nbsp;&nbsp;&nbsp;&nbsp; (New York, 12345 )</option>';
						echo '<option value="postal-address" ' . selected( 'postal-address', $options['address_format'], false ) . '>{zipcode} {city} &nbsp;&nbsp;&nbsp;&nbsp; (1234AB Amsterdam)</option>';
						echo '</select>';
						echo '<br class="clear">';
						echo '<p class="desc label" style="border:none;">' . __( 'A lot of countries have their own address format. Please choose one that matches yours. If you have something completely different, please let us know.', 'yoast-local-seo' ) . '<br>';
						echo '</div>';
						echo '<br class="clear">';

						echo '<div id="select-multiple-locations" style="">' . __( 'If you have more than one location, you can enable this feature. WordPress SEO will create a new Custom Post Type for you where you can manage your locations. If it\'s not enabled you can enter your address details below. These fields will be ignored when you enable this option.', 'yoast-local-seo' ) . '<br>';
						echo '<label for="use_multiple_locations" class="checkbox">' . __( 'Use multiple locations', 'yoast-local-seo' ) . ':</label>';
						echo '<input class="checkbox" id="use_multiple_locations" type="checkbox" name="wpseo_local[use_multiple_locations]" value="1" ' . checked( '1', $options['use_multiple_locations'], false ) . '> ';
						echo '</div>';

						echo '<div id="show-single-location" style="clear: both; ' . ( wpseo_has_multiple_locations() ? 'display: none;' : '' ) . '">';

						echo '<label for="location_name" class="textinput">' . __( 'Business name', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="location_name" class="textinput" type="text" name="wpseo_local[location_name]" value="' . $options['location_name'] . '"/>';
						echo '<label class="textinput" for="wpseo_business_type">' . __( 'Business type:', 'yoast-local-seo' ) . '</label>';
						echo '<select name="wpseo_local[business_type]" class="chzn-select" id="wpseo_business_type" style="float: left;  width: 200px; margin-top: 8px; " data-placeholder="Specify your Business Type">';
						echo '<option></option>';
						foreach ( $this->get_local_business_types() as $bt_label => $bt_option ) {
							$sel = '';
							if ( $options['business_type'] == $bt_option )
								$sel = 'selected="selected"';
							echo '<option ' . $sel . ' value="' . $bt_option . '">' . $bt_label . '</option>';
						}
						echo '</select><br class="clear">';
						echo '<label for="location_address" class="textinput">' . __( 'Business address', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="location_address" class="textinput" type="text" name="wpseo_local[location_address]" value="' . $options['location_address'] . '"/>';
						echo '<label for="location_city" class="textinput">' . __( 'Business city', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="location_city" class="textinput" type="text" name="wpseo_local[location_city]" value="' . $options['location_city'] . '"/>';
						echo '<label for="location_state" class="textinput">' . __( 'Business state', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="location_state" class="textinput" type="text" name="wpseo_local[location_state]" value="' . $options['location_state'] . '"/>';
						echo '<label for="location_zipcode" class="textinput">' . __( 'Business zipcode', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="location_zipcode" class="textinput" type="text" name="wpseo_local[location_zipcode]" value="' . $options['location_zipcode'] . '"/>';
						echo '<label for="location_country" class="textinput">' . __( 'Business country', 'yoast-local-seo' ) . ':</label>';
						echo '<select id="location_country" class="textinput chzn-select" data-placeholder="' . __( 'Choose your country', 'yoast-local-seo' ) . '" name="wpseo_local[location_country]" style="float: left; width: 200px; margin-top: 8px; ">';
						echo '<option></option>';
						$countries = WPSEO_Frontend_Local::get_country_array();
						foreach ( $countries as $key => $val ) {
							echo '<option value="' . $key . '"' . selected( $options['location_country'], $key, false ) . '>' . $countries[$key] . '</option>';
						}
						echo '</select><br class="clear">';
						echo '<label for="location_phone" class="textinput">' . __( 'Business phone number', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="location_phone" class="textinput" type="text" name="wpseo_local[location_phone]" value="' . $options['location_phone'] . '"/>';

						echo '<label for="location_fax" class="textinput">' . __( 'Business fax', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="location_fax" class="textinput" type="text" name="wpseo_local[location_fax]" value="' . $options['location_fax'] . '"/>';

						echo '<label for="location_email" class="textinput">' . __( 'Business email', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="location_email" class="textinput" type="text" name="wpseo_local[location_email]" value="' . $options['location_email'] . '"/>';

						// Calculate lat/long coordinates when address is entered.
						if ( $options['location_coords_lat'] == '' || $options['location_coords_long'] == '' ) {
							$location_coordinates = $this->get_geo_data( array(
								'_wpseo_business_address' => $options['location_address'],
								'_wpseo_business_city'    => $options['location_city'],
								'_wpseo_business_state'   => $options['location_state'],
								'_wpseo_business_zipcode' => $options['location_zipcode'],
								'_wpseo_business_country' => $options['location_country']
							), true );
							if ( !empty( $location_coordinates['coords'] ) ) {
								$options['location_coords_lat']  = $location_coordinates['coords']['lat'];
								$options['location_coords_long'] = $location_coordinates['coords']['long'];
								update_option( 'wpseo_local', $options );
							}
						}

						echo '<input id="location_coords_lat" type="hidden" name="wpseo_local[location_coords_lat]" value="' . $options['location_coords_lat'] . '"/>';
						echo '<input id="location_coords_long" type="hidden" name="wpseo_local[location_coords_long]" value="' . $options['location_coords_long'] . '"/>';

						echo '<br class="clear">';
						echo '</div><!-- #show-single-location -->';

						echo '<div id="show-multiple-locations" style="clear: both; ' . ( wpseo_has_multiple_locations() ? '' : 'display: none;' ) . '">';
						echo '<label for="locations_slug" class="textinput">' . __( 'Locations slug', 'yoast-local-seo' ) . ':</label>';
						echo '<input id="locations_slug" class="textinput" type="text" name="wpseo_local[locations_slug]" value="' . $options['locations_slug'] . '"/>';
						echo '<br class="clear">';
						echo '<p class="desc label" style="border: 0; margin-bottom: 0; padding-bottom: 0;">' . __( 'The slug for your location pages. Default slug is <em>locations</em>.', 'yoast-local-seo' ) . '<br>';
						echo '<a href="' . get_post_type_archive_link( 'wpseo_locations' ) . '" target="_blank">' . __( 'View them all', 'yoast-local-seo' ) . '</a> ' . __( 'or', 'yoast-local-seo' ) . ' <a href="' . admin_url( 'edit.php?post_type=wpseo_locations' ) . '">' . __( 'edit them', 'yoast-local-seo' ) . '</a>';
						echo '</p>';
						echo '</div>';

						echo '<h3>' . __( 'Opening hours', 'yoast-local-seo' ) . '</h3>';

						echo '<div>';
						echo '<label for="opening_hours_24h" class="checkbox">' . __( 'Use 24h format', 'yoast-local-seo' ) . ':</label>';
						echo '<input class="checkbox" id="opening_hours_24h" type="checkbox" name="wpseo_local[opening_hours_24h]" value="1" ' . checked( '1', $options['opening_hours_24h'], false ) . '> ';
						echo '</div>';
						echo '<br class="clear">';

						echo '<div id="show-opening-hours" ' . ( wpseo_has_multiple_locations() ? ' class="hidden"' : '' ) . '>';

						echo '<div id="opening-hours-multiple">';
						echo '<label for="multiple_opening_hours" class="checkbox">' . __( 'I have two sets of opening hours per day', 'yoast-local-seo' ) . ':</label>';
						echo '<input class="checkbox" id="multiple_opening_hours" type="checkbox" name="wpseo_local[multiple_opening_hours]" value="1" ' . checked( '1', $options['multiple_opening_hours'], false ) . '> ';
						echo '</div>';
						echo '<br class="clear">';

						if ( !isset( $options['opening_hours_24h'] ) )
							$options['opening_hours_24h'] = false;

						foreach ( $this->days as $key => $day ) {
							$field_name        = 'opening_hours_' . $key;
							$value_from        = isset( $options[$field_name . '_from'] ) ? esc_attr( $options[$field_name . '_from'] ) : '09:00';
							$value_to          = isset( $options[$field_name . '_to'] ) ? esc_attr( $options[$field_name . '_to'] ) : '17:00';
							$value_second_from = isset( $options[$field_name . '_second_from'] ) ? esc_attr( $options[$field_name . '_second_from'] ) : '09:00';
							$value_second_to   = isset( $options[$field_name . '_second_to'] ) ? esc_attr( $options[$field_name . '_second_to'] ) : '17:00';

							echo '<div class="clear opening-hours">';

							echo '<label class="textinput">' . $day . ':</label>';
							echo '<select class="openinghours_from" style="width: 100px;" id="' . $field_name . '_from" name="wpseo_local[' . $field_name . '_from]">';
							echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_from );
							echo '</select><span id="' . $field_name . '_to_wrapper"> - ';
							echo '<select class="openinghours_to" style="width: 100px;" id="' . $field_name . '_to" name="wpseo_local[' . $field_name . '_to]">';
							echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_to );
							echo '</select>';

							echo '<div class="clear opening-hour-second ' . ( $options['multiple_opening_hours'] != '1' ? 'hidden' : '' ) . '">';
							echo '<label class="textinput">&nbsp;</label>';
							echo '<select class="openinghours_from_second" style="width: 100px;" id="' . $field_name . '_second_from" name="wpseo_local[' . $field_name . '_second_from]">';
							echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_second_from );
							echo '</select><span id="' . $field_name . '_second_to_wrapper"> - ';
							echo '<select class="openinghours_to_second" style="width: 100px;" id="' . $field_name . '_second_to" name="wpseo_local[' . $field_name . '_second_to]">';
							echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_second_to );
							echo '</select>';
							echo '</div>';

							echo '</div>';
						}

						echo '</div><!-- #show-opening-hours -->';

					}

					echo '<div class="submit"><input type="submit" class="button-primary" name="submit" value="' . __( "Save Settings", 'yoast-local-seo' ) . '"/></div>';

					if ( $license_active ) {
						echo '<br class="clear"/>';

						echo '<h2>' . __( 'Geo Sitemap & KML File', 'yoast-local-seo' ) . '</h2>';

						echo '<p>' . sprintf( __( 'You can find your Geo Sitemap here: %sGeo Sitemap%s', 'yoast-local-seo' ), '<a target="_blank" class="button-secondary" href="' . home_url( 'geo_sitemap.xml' ) . '">', '</a>' ) . '<br /><br />';
						echo sprintf( __( 'You can find your KML file here: %sKML file%s', 'yoast-local-seo' ), '<a target="_blank" class="button-secondary" href="' . home_url( 'locations.kml' ) . '">', '</a>' ) . '</p>';

						echo '<p>' . __( 'PS: You do <strong>not</strong> need to generate the Geo sitemap or KML file, nor will it take up time to generate after publishing a post.', 'yoast-local-seo' ) . '</p>';

						do_action( 'wpseo_local', $this );
					}
					?>
				</form>
			</div>

		<?php
		}

		/**
		 * Generates the import panel for importing locations via CSV
		 */
		function import_panel() {
			global $wpseo_admin_pages;

			$upload_dir       = wp_upload_dir();
			$wpseo_upload_dir = $upload_dir["basedir"] . '/wpseo/import/';

			$content = '<p>' . sprintf( __( 'Upload your CSV file. Make sure the %s directory is writeable.', 'yoast-local-seo' ), '<code>"' . $wpseo_upload_dir . '"</code>' ) . '</p>';
			$content .= '<form action="" method="post" enctype="multipart/form-data">';
			$content .= $wpseo_admin_pages->file_upload( 'csvuploadlocations', __( 'Upload CSV', 'yoast-local-seo' ) );
			$content .= $wpseo_admin_pages->radio( 'csv_separator', array( 'comma' => __( 'Comma', 'yoast-local-seo' ), 'semicolon' => __( 'Semicolon', 'yoast-local-seo' ) ), __( 'Column separator', 'yoast-local-seo' ) );
			$content .= '<br/>';
			$content .= '<input type="submit" class="button-primary" name="csv-import" value="Import" />';
			$content .= '</form>';

			if ( !empty( $_POST["csv-import"] ) ) {
				$csv_path = $wpseo_upload_dir . basename( $_FILES['wpseo']['name']['csvuploadlocations'] );
				if ( !empty( $_FILES['wpseo'] ) && !move_uploaded_file( $_FILES['wpseo']['tmp_name']['csvuploadlocations'], $csv_path ) ) {
					$content .= '<p class="error">' . __( 'Sorry, there was an error while uploading the CSV file.<br>Please make sure the ' . $wpseo_upload_dir . ' directory is writable (chmod 777).', 'yoast-local-seo' ) . '</p>';
				} else {
					$separator = ",";
					if ( !empty( $_POST['csv_separator'] ) && $_POST['csv_separator'] == "semicolon" ) {
						$separator = ";";
					}

					// Get location data from CSV
					$column_names = array( "name", "address", "city", "zipcode", "state", "country", "phone", "description", "image" );
					$handle       = fopen( $csv_path, "r" );
					$locations    = array();
					$row          = 0;
					while ( ( $csvdata = fgetcsv( $handle, 1000, $separator ) ) !== FALSE ) {
						if ( $row > 0 ) {
							$tmp_location = array();
							for ( $i = 0; $i < count( $column_names ); $i++ ) {
								if ( isset( $csvdata[$i] ) ) {
									$tmp_location[$column_names[$i]] = addslashes( $csvdata[$i] );
								}
							}
							array_push( $locations, $tmp_location );
						}
						$row++;
					}
					fclose( $handle );

					$debug = false;

					// Create WordPress posts in custom post type
					foreach ( $locations as $location ) {
						// Create standard post data
						$current_post['ID']           = '';
						$current_post['post_title']   = $location["name"];
						$current_post['post_content'] = $location["description"];
						$current_post['post_status']  = "publish";
						$current_post['post_date']    = date( "Y-m-d H:i:s", time() );
						$current_post['post_type']    = 'wpseo_locations';

						if ( !$debug ) {
							$post_id = wp_insert_post( $current_post );

							// Insert custom fields for location details
							if ( !empty( $post_id ) ) {
								add_post_meta( $post_id, "_wpseo_business_name", $location["name"], true );
								add_post_meta( $post_id, '_wpseo_business_address', $location["address"], true );
								add_post_meta( $post_id, '_wpseo_business_city', $location["city"], true );
								add_post_meta( $post_id, '_wpseo_business_state', $location["state"], true );
								add_post_meta( $post_id, '_wpseo_business_zipcode', $location["zipcode"], true );
								add_post_meta( $post_id, '_wpseo_business_country', $location["country"], true );
								add_post_meta( $post_id, '_wpseo_business_phone', $location["phone"], true );
								add_post_meta( $post_id, '_wpseo_business_fax', $location["fax"], true );
								add_post_meta( $post_id, '_wpseo_business_email', $location["email"], true );
							}

							// Add image as post thumbnail
							if ( !empty( $location["image"] ) ) {
								$upload_dir = wp_upload_dir();
								$filepath   = $upload_dir["basedir"] . '/wpseo/import/images/' . $location["image"];

								$wpseo_admin_pages->insert_attachment( $post_id, $filepath, true );
							}
						}
					}

					$msg = '';
					if ( count( $locations ) > 0 ) {
						$msg .= count( $locations ) . ' locations found and succesfully imported.<br/>';
					}
					if ( $msg != '' ) {
						echo '<div id="message" class="message updated" style="width:94%;"><p>' . $msg . '</p></div>';
					}
				}
			}

			$wpseo_admin_pages->postbox( 'xmlgeositemaps', __( 'CSV import of locations for Local Search', 'yoast-local-seo' ), $content );
		}

		/**
		 * Creates the wpseo_locations Custom Post Type
		 */
		function create_custom_post_type() {
			/* Locations as Custom Post Type */
			$labels = array(
				'name'               => __( 'Locations', 'yoast-local-seo' ),
				'singular_name'      => __( 'Location', 'yoast-local-seo' ),
				'add_new'            => __( 'New Location', 'yoast-local-seo' ),
				'new_item'           => __( 'New Location', 'yoast-local-seo' ),
				'add_new_item'       => __( 'Add New Location', 'yoast-local-seo' ),
				'edit_item'          => __( 'Edit Location', 'yoast-local-seo' ),
				'view_item'          => __( 'View Location', 'yoast-local-seo' ),
				'search_items'       => __( 'Search Locations', 'yoast-local-seo' ),
				'not_found'          => __( 'No locations found', 'yoast-local-seo' ),
				'not_found_in_trash' => __( 'No locations found in trash', 'yoast-local-seo' ),
			);

			$slug = !empty( $this->options['locations_slug'] ) ? $this->options['locations_slug'] : 'locations';

			register_post_type( 'wpseo_locations', array(
				'labels'               => $labels,
				'public'               => true,
				'show_ui'              => true,
				'capability_type'      => 'post',
				'hierarchical'         => false,
				'rewrite'              => array( 'slug' => $slug ),
				'has_archive'          => $slug,
				'query_var'            => true,
				'register_meta_box_cb' => array( &$this, 'add_location_metaboxes' ),
				'supports'             => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', )
			) );
		}

		/**
		 * Adds metabox for editing screen of the wpseo_locations Custom Post Type
		 */
		function add_location_metaboxes() {
			add_meta_box( 'wpseo_locations', __( 'Business address details' ), array( &$this, 'metabox_locations' ), 'wpseo_locations', 'normal', 'high' );
		}

		/**
		 * Builds the metabox for editing screen of the wpseo_locations Custom Post Type
		 */
		function metabox_locations() {
			$post_id = get_the_ID();

			$options = $this->options;

			echo '<style type="text/css">label.textinput { float: left; width: 150px; }</style>';

			echo '<div style="overflow: hidden;">';

			// Noncename needed to verify where the data originated
			echo '<input type="hidden" name="locationsmeta_noncename" id="locationsmeta_noncename" value="' . wp_create_nonce( plugin_basename( __FILE__ ) ) . '" />';

			// Get the location data if its already been entered
			$business_type          = get_post_meta( $post_id, '_wpseo_business_type', true );
			$business_address       = get_post_meta( $post_id, '_wpseo_business_address', true );
			$business_city          = get_post_meta( $post_id, '_wpseo_business_city', true );
			$business_state         = get_post_meta( $post_id, '_wpseo_business_state', true );
			$business_zipcode       = get_post_meta( $post_id, '_wpseo_business_zipcode', true );
			$business_country       = get_post_meta( $post_id, '_wpseo_business_country', true );
			$business_phone         = get_post_meta( $post_id, '_wpseo_business_phone', true );
			$business_fax           = get_post_meta( $post_id, '_wpseo_business_fax', true );
			$business_email         = get_post_meta( $post_id, '_wpseo_business_email', true );
			$coordinates_lat        = get_post_meta( $post_id, '_wpseo_coordinates_lat', true );
			$coordinates_long       = get_post_meta( $post_id, '_wpseo_coordinates_long', true );
			$is_postal_address      = get_post_meta( $post_id, '_wpseo_is_postal_address', true );
			$multiple_opening_hours = get_post_meta( $post_id, '_wpseo_multiple_opening_hours', true );
			$multiple_opening_hours = $multiple_opening_hours == '1';

			// Echo out the field
			echo '<p><label class="textinput" for="wpseo_business_type">Business type:</label>';
			echo '<select class="chzn-select" name="_wpseo_business_type" id="wpseo_business_type" style="width: 200px; margin-top: 8px;" data-placeholder="' . __( 'Choose your business type', 'yoast-local-seo' ) . '">';
			echo '<option></option>';
			foreach ( $this->get_local_business_types() as $bt_label => $bt_option ) {
				$sel = '';
				if ( $business_type == $bt_option )
					$sel = 'selected="selected"';
				echo '<option ' . $sel . ' value="' . $bt_option . '">' . $bt_label . '</option>';
			}
			echo '</select></p>';

			echo '<p><label class="textinput" for="wpseo_business_address">' . __( 'Business address:', 'yoast-local-seo' ) . '</label>';
			echo '<input type="text" name="_wpseo_business_address" id="wpseo_business_address" value="' . $business_address . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_city">' . __( 'Business city', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_city" id="wpseo_business_city" value="' . $business_city . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_state">' . __( 'Business state', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_state" id="wpseo_business_state" value="' . $business_state . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_zipcode">' . __( 'Business zipcode', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_zipcode" id="wpseo_business_zipcode" value="' . $business_zipcode . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_country">' . __( 'Business country', 'yoast-local-seo' ) . ':</label>';
			echo '<select class="chzn-select" name="_wpseo_business_country" id="wpseo_business_country" style="width: 200px; margin-top: 8px;" data-placeholder="' . __( 'Choose your country', 'yoast-local-seo' ) . '">';
			echo '<option></option>';
			$countries = WPSEO_Frontend_Local::get_country_array();
			foreach ( $countries as $key => $val ) {
				echo '<option value="' . $key . '"' . ( $business_country == $key ? ' selected="selected"' : '' ) . '>' . $countries[$key] . '</option>';
			}
			echo '</select></p>';
			echo '<p><label class="textinput" for="wpseo_business_phone">' . __( 'Main phone number', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_phone" id="wpseo_business_phone" value="' . $business_phone . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_fax">' . __( 'Fax number', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_fax" id="wpseo_business_fax" value="' . $business_fax . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_email">' . __( 'Email address', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_email" id="wpseo_business_email" value="' . $business_email . '" /></p>';

			echo '<p>' . __( 'You can enter the lat/long coordinates yourself. If you leave them empty they will be calculated automatically. If you want to re-calculate these fields, please make them blank before saving this location.', 'yoast-local-seo' ) . '</p>';
			echo '<p><label class="textinput" for="wpseo_coordinates_lat">' . __( 'Latitude', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_coordinates_lat" id="wpseo_coordinates_lat" value="' . $coordinates_lat . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_coordinates_long">' . __( 'Longitude', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_coordinates_long" id="wpseo_coordinates_long" value="' . $coordinates_long . '" /></p>';

			echo '<p>';
			echo '<label class="textinput" for="wpseo_is_postal_address">' . __( 'This address is a postal address (not a physical location)', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="checkbox" class="checkbox" name="_wpseo_is_postal_address" id="wpseo_is_postal_address" value="1" ' . checked( $is_postal_address, 1, false ) . ' />';
			echo '</p>';

			// Opening hours
			echo '<br class="clear">';
			echo '<h4>' . __( 'Opening hours', 'yoast-local-seo' ) . '</h4>';

			echo '<div id="opening-hours-multiple">';
			echo '<label for="wpseo_multiple_opening_hours" class="textinput">' . __( 'I have two sets of opening hours per day', 'yoast-local-seo' ) . ':</label>';
			echo '<input class="checkbox" id="wpseo_multiple_opening_hours" type="checkbox" name="_wpseo_multiple_opening_hours" value="1" ' . checked( '1', $multiple_opening_hours, false ) . '> ';
			echo '</div>';
			echo '<br class="clear">';

			foreach ( $this->days as $key => $day ) {
				$field_name = '_wpseo_opening_hours_' . $key;
				$value_from = get_post_meta( $post_id, $field_name . '_from', true );
				if ( !$value_from )
					$value_from = '09:00';
				$value_to = get_post_meta( $post_id, $field_name . '_to', true );
				if ( !$value_to )
					$value_to = '17:00';
				$value_second_from = get_post_meta( $post_id, $field_name . '_second_from', true );
				if ( !$value_second_from )
					$value_second_from = '09:00';
				$value_second_to = get_post_meta( $post_id, $field_name . '_second_to', true );
				if ( !$value_second_to )
					$value_second_to = '17:00';

				echo '<div class="clear opening-hours">';

				if ( !isset( $options['opening_hours_24h'] ) )
					$options['opening_hours_24h'] = false;

				echo '<label class="textinput">' . $day . ':</label>';
				echo '<select class="openinghours_from" style="width: 100px;" id="' . $field_name . '_from" name="' . $field_name . '_from">';
				echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_from );
				echo '</select><span id="' . $field_name . '_to_wrapper"> - ';
				echo '<select class="openinghours_to" style="width: 100px;" id="' . $field_name . '_to" name="' . $field_name . '_to">';
				echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_to );
				echo '</select></span>';

				echo '<div class="clear opening-hour-second ' . ( !$multiple_opening_hours ? 'hidden' : '' ) . '">';
				echo '<div id="' . $field_name . '_second">';
				echo '<label class="textinput">&nbsp;</label>';
				echo '<select class="openinghours_from_second" style="width: 100px;" id="' . $field_name . '_second_from" name="' . $field_name . '_second_from">';
				echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_second_from );
				echo '</select><span id="' . $field_name . '_second_to_wrapper"> - ';
				echo '<select class="openinghours_to_second" style="width: 100px;" id="' . $field_name . '_second_to" name="' . $field_name . '_second_to">';
				echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_second_to );
				echo '</select>';
				echo '</div>';
				echo '</div>';

				echo '</div>';
			}

			echo '<br class="clear" />';
			echo '</div>';
		}

		/**
		 * Handles and saves the data entered in the wpseo_locations metabox
		 */
		function wpseo_locations_save_meta( $post_id, $post ) {
			// First check if post type is wpseo_locations
			if ( $post->post_type == "wpseo_locations" ) {

				// verify this came from the our screen and with proper authorization,
				// because save_post can be triggered at other times
				if ( isset( $_POST['locationsmeta_noncename'] ) && !wp_verify_nonce( $_POST['locationsmeta_noncename'], plugin_basename( __FILE__ ) ) ) {
					return $post_id;
				}

				// Is the user allowed to edit the post or page?
				if ( !current_user_can( 'edit_post', $post_id ) ) {
					return $post_id;
				}

				// OK, we're authenticated: we need to find and save the data
				// We'll put it into an array to make it easier to loop though.
				$locations_meta['_wpseo_business_type']          = isset( $_POST['_wpseo_business_type'] ) ? $_POST['_wpseo_business_type'] : 'LocalBusiness';
				$locations_meta['_wpseo_business_address']       = isset( $_POST['_wpseo_business_address'] ) ? $_POST['_wpseo_business_address'] : '';
				$locations_meta['_wpseo_business_city']          = isset( $_POST['_wpseo_business_city'] ) ? $_POST['_wpseo_business_city'] : '';
				$locations_meta['_wpseo_business_state']         = isset( $_POST['_wpseo_business_state'] ) ? $_POST['_wpseo_business_state'] : '';
				$locations_meta['_wpseo_business_zipcode']       = isset( $_POST['_wpseo_business_zipcode'] ) ? $_POST['_wpseo_business_zipcode'] : '';
				$locations_meta['_wpseo_business_country']       = isset( $_POST['_wpseo_business_country'] ) ? $_POST['_wpseo_business_country'] : '';
				$locations_meta['_wpseo_business_phone']         = isset( $_POST['_wpseo_business_phone'] ) ? $_POST['_wpseo_business_phone'] : '';
				$locations_meta['_wpseo_business_fax']           = isset( $_POST['_wpseo_business_fax'] ) ? $_POST['_wpseo_business_fax'] : '';
				$locations_meta['_wpseo_business_email']         = isset( $_POST['_wpseo_business_email'] ) ? $_POST['_wpseo_business_email'] : '';
				$locations_meta['_wpseo_coordinates_lat']        = isset( $_POST['_wpseo_coordinates_lat'] ) ? $_POST['_wpseo_coordinates_lat'] : '';
				$locations_meta['_wpseo_coordinates_long']       = isset( $_POST['_wpseo_coordinates_long'] ) ? $_POST['_wpseo_coordinates_long'] : '';
				$locations_meta['_wpseo_is_postal_address']      = isset( $_POST['_wpseo_is_postal_address'] ) ? $_POST['_wpseo_is_postal_address'] : '';
				$locations_meta['_wpseo_multiple_opening_hours'] = isset( $_POST['_wpseo_multiple_opening_hours'] ) ? $_POST['_wpseo_multiple_opening_hours'] : '';
				foreach ( $this->days as $key => $day ) {
					$field_name                                   = '_wpseo_opening_hours_' . $key;
					$locations_meta[$field_name . '_from']        = isset( $_POST[$field_name . '_from'] ) ? $_POST[$field_name . '_from'] : '';
					$locations_meta[$field_name . '_to']          = isset( $_POST[$field_name . '_to'] ) ? $_POST[$field_name . '_to'] : '';
					$locations_meta[$field_name . '_second_from'] = isset( $_POST[$field_name . '_second_from'] ) ? $_POST[$field_name . '_second_from'] : '';
					$locations_meta[$field_name . '_second_to']   = isset( $_POST[$field_name . '_second_to'] ) ? $_POST[$field_name . '_second_to'] : '';
				}
				$locations_meta['post_id']						= $post_id;

				// Add values of $locations_meta as custom fields
				foreach ( $locations_meta as $key => $value ) { // Cycle through the $locations_meta array
					if ( $post->post_type == 'revision' )
						return; // Don't store custom data twice

					if ( !empty( $value ) )
						update_post_meta( $post_id, $key, $value );
					else
						delete_post_meta( $post_id, $key ); // Delete if blank
				}

				// If lat/long fields are empty calculate them
				if ( empty( $_POST['_wpseo_coordinates_lat'] ) || empty( $_POST['_wpseo_coordinates_long'] ) ) {
					$location_coordinates = $this->get_geo_data( $locations_meta, true );
					if ( !empty( $location_coordinates['coords'] ) ) {
						update_post_meta( $post_id, '_wpseo_coordinates_lat', $location_coordinates['coords']['lat'] );
						update_post_meta( $post_id, '_wpseo_coordinates_long', $location_coordinates['coords']['long'] );
					}
				}

				// Re-ping the new sitemap
				$this->update_sitemap();
			}

			return true;
		}

		/**
		 * Inserts attachment in WordPress. Used by import panel
		 *
		 * @param int    $post_id  The post ID where the attachment belongs to
		 * @param string $filepath Filepath of the file which has to be uploaded
		 * @param bool   $setthumb If there's an image in the import file, then set is as a Featured Image
		 * @return int|WP_Error attachment ID. Returns WP_Error when upload goes wrong
		 */
		function insert_attachment( $post_id, $filepath, $setthumb = false ) {
			$wp_filetype = wp_check_filetype( basename( $filepath ), null );

			$file_arr["name"]     = basename( $filepath );
			$file_arr["type"]     = $wp_filetype;
			$file_arr["tmp_name"] = $filepath;
			$file_title           = preg_replace( '/\.[^.]+$/', '', basename( $filepath ) );

			$attach_id = $this->media_handle_sideload( $file_arr, $post_id, $file_title );

			if ( $setthumb ) {
				update_post_meta( $post_id, '_thumbnail_id', $attach_id );
			}

			return $attach_id;
		}

		/**
		 * Handles the file upload and puts it in WordPress. Copied from media.php, because there's a fat bug in the last lines: it returns $url instead of $id;
		 *
		 * @since 2.6.0
		 * @param array  $file_array Array similar to a {@link $_FILES} upload array
		 * @param int    $post_id    The post ID the media is associated with
		 * @param string $desc       Description of the sideloaded file
		 * @param array  $post_data  allows you to overwrite some of the attachment
		 * @return int|object The ID of the attachment or a WP_Error on failure
		 */
		function media_handle_sideload( $file_array, $post_id, $desc = null, $post_data = array() ) {
			$overrides = array( 'test_form' => false );

			$file = wp_handle_sideload( $file_array, $overrides );
			if ( isset( $file['error'] ) )
				return new WP_Error( 'upload_error', $file['error'] );

			$url     = $file['url'];
			$type    = $file['type'];
			$file    = $file['file'];
			$title   = preg_replace( '/\.[^.]+$/', '', basename( $file ) );
			$content = '';

			// use image exif/iptc data for title and caption defaults if possible
			if ( $image_meta = @wp_read_image_metadata( $file ) ) {
				if ( trim( $image_meta['title'] ) && !is_numeric( sanitize_title( $image_meta['title'] ) ) )
					$title = $image_meta['title'];
				if ( trim( $image_meta['caption'] ) )
					$content = $image_meta['caption'];
			}

			$title = @$desc;

			// Construct the attachment array
			$attachment = array_merge( array(
				'post_mime_type' => $type,
				'guid'           => $url,
				'post_parent'    => $post_id,
				'post_title'     => $title,
				'post_content'   => $content,
			), $post_data );

			// Save the attachment metadata
			$id = wp_insert_attachment( $attachment, $file, $post_id );
			if ( !is_wp_error( $id ) ) {
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
			}
			return $id;
		}

		/**
		 * Filter the Page Analysis results to make sure we're giving the correct hints.
		 *
		 * @since 0.2
		 *
		 * @param array  $results The results array to filter and update.
		 * @param array  $job     The current jobs variables.
		 * @param object $post    The post object for the current page.
		 *
		 * @return array $results
		 */
		function filter_linkdex_results( $results, $job, $post ) {

			// @todo dit moet nog gaan werken voor single implementaties, first pass enzo.

			if ( $post->post_type != 'wpseo_locations' )
				return $results;

			$custom = get_post_custom();

			if ( strpos( $job['title'], $custom['_wpseo_business_city'][0] ) === false ) {
				$results['local-title'] = array(
					'val' => 4,
					'msg' => __( 'Your title does not contain your location\'s city, you should really add that.', 'yoast-local-seo' )
				);
			} else {
				$results['local-title'] = array(
					'val' => 9,
					'msg' => __( 'Your title contains your location\'s city, well done!', 'yoast-local-seo' )
				);
			}

			if ( strpos( $job['pageUrl'], $custom['_wpseo_business_city'][0] ) === false ) {
				$results['local-url'] = array(
					'val' => 4,
					'msg' => __( 'Your URL does not contain your location\'s city, you should really add that.', 'yoast-local-seo' )
				);
			} else {
				$results['local-url'] = array(
					'val' => 9,
					'msg' => __( 'Your URL contains your location\'s city, well done!', 'yoast-local-seo' )
				);
			}
			return $results;
		}

		/**
		 * Returns the valid local business types currently shown on Schema.org
		 *
		 * @link http://schema.org/docs/full.html In the bottom of this page is a list of Local Business types.
		 * @return array
		 */
		function get_local_business_types() {
			return array(
				"Animal Shelter"                 => "AnimalShelter",
				"Automotive Business"            => "AutomotiveBusiness",
				"&mdash; Auto Body Shop"         => "AutoBodyShop",
				"&mdash; Auto Dealer"            => "AutoDealer",
				"&mdash; Auto Parts Store"       => "AutoPartsStore",
				"&mdash; Auto Rental"            => "AutoRental",
				"&mdash; Auto Repair"            => "AutoRepair",
				"&mdash; Auto Wash"              => "AutoWash",
				"&mdash; Gas Station"            => "GasStation",
				"&mdash; Motorcycle Dealer"      => "MotorcycleDealer",
				"&mdash; Motorcycle Repair"      => "MotorcycleRepair",
				"Child Care"                     => "ChildCare",
				"Dry Cleaning or Laundry"        => "DryCleaningOrLaundry",
				"Emergency Service"              => "EmergencyService",
				"&mdash; Fire Station"           => "FireStation",
				"&mdash; Hospital"               => "Hospital",
				"&mdash; Police Station"         => "PoliceStation",
				"Employment Agency"              => "EmploymentAgency",
				"Entertainment Business"         => "EntertainmentBusiness",
				"&mdash; Adult Entertainment"    => "AdultEntertainment",
				"&mdash; Amusement Park"         => "AmusementPark",
				"&mdash; Art Gallery"            => "ArtGallery",
				"&mdash; Casino"                 => "Casino",
				"&mdash; Comedy Club"            => "ComedyClub",
				"&mdash; Movie Theater"          => "MovieTheater",
				"&mdash; Night Club"             => "NightClub",
				"Financial Service"              => "FinancialService",
				"&mdash; Accounting Service"     => "AccountingService",
				"&mdash; Automated Teller"       => "AutomatedTeller",
				"&mdash; Bank or Credit Union"   => "BankOrCreditUnion",
				"&mdash; Insurance Agency"       => "InsuranceAgency",
				"Food Establishment"             => "FoodEstablishment",
				"&mdash; Bakery"                 => "Bakery",
				"&mdash; Bar or Pub"             => "BarOrPub",
				"&mdash; Brewery"                => "Brewery",
				"&mdash; Cafe or Coffee Shop"    => "CafeOrCoffeeShop",
				"&mdash; Fast Food Restaurant"   => "FastFoodRestaurant",
				"&mdash; Ice Cream Shop"         => "IceCreamShop",
				"&mdash; Restaurant"             => "Restaurant",
				"&mdash; Winery"                 => "Winery",
				"Government Office"              => "GovernmentOffice",
				"&mdash; Post Office"            => "PostOffice",
				"Health And Beauty Business"     => "HealthAndBeautyBusiness",
				"&mdash; Beauty Salon"           => "BeautySalon",
				"&mdash; Day Spa"                => "DaySpa",
				"&mdash; Hair Salon"             => "HairSalon",
				"&mdash; Health Club"            => "HealthClub",
				"&mdash; Nail Salon"             => "NailSalon",
				"&mdash; Tattoo Parlor"          => "TattooParlor",
				"Home And Construction Business" => "HomeAndConstructionBusiness",
				"&mdash; Electrician"            => "Electrician",
				"&mdash; General Contractor"     => "GeneralContractor",
				"&mdash; HVAC Business"          => "HVACBusiness",
				"&mdash; House Painter"          => "HousePainter",
				"&mdash; Locksmith"              => "Locksmith",
				"&mdash; Moving Company"         => "MovingCompany",
				"&mdash; Plumber"                => "Plumber",
				"&mdash; Roofing Contractor"     => "RoofingContractor",
				"Internet Cafe"                  => "InternetCafe",
				" Library"                       => "Library",
				"Lodging Business"               => "LodgingBusiness",
				"&mdash; Bed And Breakfast"      => "BedAndBreakfast",
				"&mdash; Hostel"                 => "Hostel",
				"&mdash; Hotel"                  => "Hotel",
				"&mdash; Motel"                  => "Motel",
				"Medical Organization"           => "MedicalOrganization",
				"&mdash; Dentist"                => "Dentist",
				"&mdash; Diagnostic Lab"         => "DiagnosticLab",
				"&mdash; Hospital"               => "Hospital",
				"&mdash; Medical Clinic"         => "MedicalClinic",
				"&mdash; Optician"               => "Optician",
				"&mdash; Pharmacy"               => "Pharmacy",
				"&mdash; Physician"              => "Physician",
				"&mdash; Veterinary Care"        => "VeterinaryCare",
				"Professional Service"           => "ProfessionalService",
				"&mdash; Accounting Service"     => "AccountingService",
				"&mdash; Attorney"               => "Attorney",
				"&mdash; Dentist"                => "Dentist",
				"&mdash; Electrician"            => "Electrician",
				"&mdash; General Contractor"     => "GeneralContractor",
				"&mdash; House Painter"          => "HousePainter",
				"&mdash; Locksmith"              => "Locksmith",
				"&mdash; Notary"                 => "Notary",
				"&mdash; Plumber"                => "Plumber",
				"&mdash; Roofing Contractor"     => "RoofingContractor",
				"Radio Station"                  => "RadioStation",
				"Real Estate Agent"              => "RealEstateAgent",
				"Recycling Center"               => "RecyclingCenter",
				"Self Storage"                   => "SelfStorage",
				"Shopping Center"                => "ShoppingCenter",
				"Sports Activity Location"       => "SportsActivityLocation",
				"&mdash; Bowling Alley"          => "BowlingAlley",
				"&mdash; Exercise Gym"           => "ExerciseGym",
				"&mdash; Golf Course"            => "GolfCourse",
				"&mdash; Health Club"            => "HealthClub",
				"&mdash; Public Swimming Pool"   => "PublicSwimmingPool",
				"&mdash; Ski Resort"             => "SkiResort",
				"&mdash; Sports Club"            => "SportsClub",
				"&mdash; Stadium or Arena"       => "StadiumOrArena",
				"&mdash; Tennis Complex"         => "TennisComplex",
				" Store"                         => "Store",
				"&mdash; Auto Parts Store"       => "AutoPartsStore",
				"&mdash; Bike Store"             => "BikeStore",
				"&mdash; Book Store"             => "BookStore",
				"&mdash; Clothing Store"         => "ClothingStore",
				"&mdash; Computer Store"         => "ComputerStore",
				"&mdash; Convenience Store"      => "ConvenienceStore",
				"&mdash; Department Store"       => "DepartmentStore",
				"&mdash; Electronics Store"      => "ElectronicsStore",
				"&mdash; Florist"                => "Florist",
				"&mdash; Furniture Store"        => "FurnitureStore",
				"&mdash; Garden Store"           => "GardenStore",
				"&mdash; Grocery Store"          => "GroceryStore",
				"&mdash; Hardware Store"         => "HardwareStore",
				"&mdash; Hobby Shop"             => "HobbyShop",
				"&mdash; HomeGoods Store"        => "HomeGoodsStore",
				"&mdash; Jewelry Store"          => "JewelryStore",
				"&mdash; Liquor Store"           => "LiquorStore",
				"&mdash; Mens Clothing Store"    => "MensClothingStore",
				"&mdash; Mobile Phone Store"     => "MobilePhoneStore",
				"&mdash; Movie Rental Store"     => "MovieRentalStore",
				"&mdash; Music Store"            => "MusicStore",
				"&mdash; Office Equipment Store" => "OfficeEquipmentStore",
				"&mdash; Outlet Store"           => "OutletStore",
				"&mdash; Pawn Shop"              => "PawnShop",
				"&mdash; Pet Store"              => "PetStore",
				"&mdash; Shoe Store"             => "ShoeStore",
				"&mdash; Sporting Goods Store"   => "SportingGoodsStore",
				"&mdash; Tire Shop"              => "TireShop",
				"&mdash; Toy Store"              => "ToyStore",
				"&mdash; Wholesale Store"        => "WholesaleStore",
				"Television Station"             => "TelevisionStation",
				"Tourist Information Center"     => "TouristInformationCenter",
				"TravelAgency"                   => "Travel Agency"
			);
		}
	}
}
