<?php

/**
 * Address shortcode handler
 *
 * @since 0.1
 * @param array $atts Array of shortcode parameters
 * @return string
 */
function wpseo_local_show_address( $atts ) {
	global $post;
	$atts = wpseo_check_falses( shortcode_atts( array(
		'id'                 => '',
		'show_state'         => true,
		'show_country'       => true,
		'show_phone'         => true,
		'show_fax'			 => true,
		'show_email'		 => true,
		'show_opening_hours' => false,
		'hide_closed'		 => false,
		'oneline'            => false,
		'from_widget'        => false,
		'widget_title'       => '',
		'before_title'       => '',
		'after_title'        => ''
	), $atts ) ) ;

	$options           = get_option( 'wpseo_local' );
	$is_postal_address = false;

	if ( wpseo_has_multiple_locations() ) {
		if ( get_post_type() == 'wpseo_locations' ) {
			if ( $atts['id'] == '' && !is_post_type_archive() )
				$atts['id'] = $post->ID;

			if ( is_post_type_archive() && $atts['id'] == '' )
				return '';
		} else if ( $atts['id'] == '' ) {
			return is_singular() ? __( 'Please provide a post ID if you want to show an address outside a Locations singular page', 'yoast-local-seo' ) : '';
		}

		// Get the location data if its already been entered
		$business_name     = get_the_title( $atts['id'] );
		$business_type     = get_post_meta( $atts['id'], '_wpseo_business_type', true );
		$business_address  = get_post_meta( $atts['id'], '_wpseo_business_address', true );
		$business_city     = get_post_meta( $atts['id'], '_wpseo_business_city', true );
		$business_state    = get_post_meta( $atts['id'], '_wpseo_business_state', true );
		$business_zipcode  = get_post_meta( $atts['id'], '_wpseo_business_zipcode', true );
		$business_country  = get_post_meta( $atts['id'], '_wpseo_business_country', true );
		$business_phone    = get_post_meta( $atts['id'], '_wpseo_business_phone', true );
		$business_fax      = get_post_meta( $atts['id'], '_wpseo_business_fax', true );
		$business_email    = get_post_meta( $atts['id'], '_wpseo_business_email', true );
		$is_postal_address = get_post_meta( $atts['id'], '_wpseo_is_postal_address', true );
		$is_postal_address = $is_postal_address == '1';
	} else {
		$business_name    = $options['location_name'];
		$business_type    = $options['business_type'];
		$business_address = $options['location_address'];
		$business_city    = $options['location_city'];
		$business_state   = $options['location_state'];
		$business_zipcode = $options['location_zipcode'];
		$business_country = $options['location_country'];
		$business_phone   = $options['location_phone'];
		$business_fax	  = $options['location_fax'];
		$business_email	  = $options['location_email'];
	}

	$tag_title_open  = '';
	$tag_title_close = '';
	if ( !$atts['from_widget'] ) {
		$tag_title_open  = '<h3>';
		$tag_title_close = '</h3>';
	} else if ( $atts['from_widget'] && $atts['widget_title'] == '' ) {
		$tag_title_open  = $atts['before_title'];
		$tag_title_close = $atts['after_title'];
	}

	$output = '<' . ( $atts['oneline'] ? 'span' : 'div' ) . ' id="wpseo_location-' . $atts['id'] . '" class="wpseo-location" itemscope itemtype="http://schema.org/' . ( $is_postal_address ? 'PostalAddress' : $business_type ) . '">';
	$output .= $tag_title_open . '<span itemprop="name">' . $business_name . '</span>' . $tag_title_close . ( $atts['oneline'] ? ', ' : '' );
	$output .= '<' . ( $atts['oneline'] ? 'span' : 'div' ) . ' ' . ( $is_postal_address ? '' : 'itemprop="address" itemscope itemtype="http://schema.org/PostalAddress"' ) . '>';
	if ( !empty( $business_address ) ) {
		$output .= '<' . ( $atts['oneline'] ? 'span' : 'div' ) . ' class="street-address" itemprop="streetAddress">' . $business_address . '</' . ( $atts['oneline'] ? 'span' : 'div' ) . '>' . ( $atts['oneline'] ? ', ' : '' );
	}

	// Output city/state/zipcode in right format
	$output .= wpseo_local_get_address_format( $business_zipcode, $business_city, $business_state, $atts['show_state'] );

	$output .= $atts['oneline'] ? ', ' : ' ';


	if ( $atts['show_country'] && !empty( $business_country ) ) {
		$output .= '<' . ( $atts['oneline'] ? 'span' : 'div' ) . '  class="country-name" itemprop="addressCountry">' . WPSEO_Frontend_Local::get_country( $business_country ) . '</' . ( $atts['oneline'] ? 'span' : 'div' ) . '>';
	}
	$output .= '</' . ( $atts['oneline'] ? 'span' : 'div' ) . '>' . ( $atts['oneline'] ? ' - ' : '<br/>' );

	if ( $atts['show_phone'] && !empty( $business_phone ) ) {
		$output .= __( 'Phone', 'yoast-local-seo' ) . ': <a href="tel:' . $business_phone . '" class="tel" itemprop="telephone">' . $business_phone . '</a><br/>';
	}

	if ( $atts['show_fax'] && !empty( $business_fax ) ) {
		$output .= __( 'Fax', 'yoast-local-seo' ) . ': <span class="tel" itemprop="faxNumber">' . $business_fax . '</span><br/>';
	}

	if ( $atts['show_email'] && !empty( $business_email ) ) {
		$output .= __( 'Email', 'yoast-local-seo' ) . ': <a href="mailto:' . $business_email .'" itemprop="email">' . $business_email . '</a><br/>';
	}

	if ( $atts['show_opening_hours'] ) {
		$args = array(
			'id' => wpseo_has_multiple_locations() ? $atts['id'] : '',
			'hide_closed' => $atts['hide_closed']
		);
		$output .= '<br/>'.wpseo_local_show_opening_hours( $args ).'<br/>';
	}
	$output .= '</' . ( $atts['oneline'] ? 'span' : 'div' ) . '>';

	return $output;
}

/**
 * Maps shortcode handler
 *
 * @since 0.1
 * @param array $atts Array of shortcode parameters
 * @return string
 */
function wpseo_local_show_map( $atts ) {
	global $post, $map_counter;

	$atts = wpseo_check_falses( shortcode_atts( array(
		'id'         => '',
		'width'      => 400,
		'height'     => 300,
		'zoom'       => -1,
		'show_route' => true,
		'show_state' => true,
		'show_country' => false
	), $atts ) );

	if ( !isset( $map_counter ) )
		$map_counter = 0;
	else
		$map_counter++;

	$business_name = $business_address = $business_city = $business_zipcode = $business_state = $business_country = $business_phone = '';

	if ( wpseo_has_multiple_locations() ) {
		if ( get_post_type() == 'wpseo_locations' ) {
			if ( $atts['id'] == '' && !is_post_type_archive() )
				$atts['id'] = $post->ID;

			if ( is_post_type_archive() && $atts['id'] == '' )
				return '';
		} else if ( $atts['id'] != 'all' && empty( $atts['id'] ) ) {
			return is_singular() ? __( 'Please provide a post ID when using this shortcode outside a Locations singular page', 'yoast-local-seo' ) : '';
		}
	} else {
		$atts['id'] = '';

		$options          = get_option( 'wpseo_local' );
		$business_name    = $options['location_name'];
		$business_address = $options['location_address'];
		$business_city    = $options['location_city'];
		$business_state   = $options['location_state'];
		$business_zipcode = $options['location_zipcode'];
		$business_country = $options['location_country'];
		$business_phone = $options['location_phone'];
	}

	$map           = '';
	$full_address  = '';
	$location_array = '';
	$marker_image  = ""; //$upload_dir["baseurl"] . "/wpseo/beachflag.png";
	$locale        = get_locale();
	$language      = substr( $locale, 0, strpos( $locale, '_' ) );

	if ( $atts['id'] != 'all' && wpseo_has_multiple_locations() ) {
		$business_name = get_the_title( $atts['id'] );
		$business_address = get_post_meta( $atts['id'], '_wpseo_business_address', true );
		$business_city    = get_post_meta( $atts['id'], '_wpseo_business_city', true );
		$business_state   = get_post_meta( $atts['id'], '_wpseo_business_state', true );
		$business_zipcode = get_post_meta( $atts['id'], '_wpseo_business_zipcode', true );
		$business_country = get_post_meta( $atts['id'], '_wpseo_business_country', true );
		$business_phone = get_post_meta( $atts['id'], '_wpseo_business_phone', true );
	}

	if ( $atts['id'] != 'all' )
		$full_address = $business_address . ', ' . $business_city . ( strtolower( $business_country ) == 'us' ? ', ' . $business_state : '' ) . ', ' . $business_zipcode . ', ' . WPSEO_Frontend_Local::get_country( $business_country );

	if ( $map_counter == 0 )
		$map .= '<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false' . ( !empty( $language ) ? '&language=' . $language : '' ) . '"></script>';

	$map .= '<script type="text/javascript">';
	$map .= 'function wpseo_map_init' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '() {
		var geocoder = new google.maps.Geocoder();
		var bounds = new google.maps.LatLngBounds();
		var endLocation' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ' = "";

		' . ( $atts['show_route'] && $atts['id'] != 'all' ? '
		var directionsDisplay' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ' = "";
		' : '' ) . '

		var wpseo_map_options' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ' = {
			zoom: 4,
			mapTypeControl: true,
			zoomControl: true,
			streetViewControl: true,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		}
		';

	if ( strtolower( $atts['id'] ) == "all" ) {
		$args      = array(
			'post_type'      => 'wpseo_locations',
			'posts_per_page' => -1
		);
		$locations = get_posts( $args );

		$lats = $longs = array();

		foreach ( $locations as $location ) {
			$postal_address = get_post_meta( $location->ID, '_wpseo_is_postal_address', true );
			if ( $postal_address ) {
				continue;
			}

			$business_name = get_the_title( $location->ID );
			$business_url = get_permalink( $location->ID );
			$business_address = get_post_meta( $location->ID, '_wpseo_business_address', true );
			$business_city    = get_post_meta( $location->ID, '_wpseo_business_city', true );
			$business_state   = get_post_meta( $location->ID, '_wpseo_business_state', true );
			$business_zipcode = get_post_meta( $location->ID, '_wpseo_business_zipcode', true );
			$business_country = get_post_meta( $location->ID, '_wpseo_business_country', true );
			$business_phone = get_post_meta( $location->ID, '_wpseo_business_phone', true );
			$coords_lat  = get_post_meta( $location->ID, '_wpseo_coordinates_lat', true );
			$coords_long = get_post_meta( $location->ID, '_wpseo_coordinates_long', true );
			$location_array .= "\t\t\tlocation_data.push( {
				'name': '$business_name',
				'url': '$business_url',
				'address': '$business_address',
				'zip_city': '" . wpseo_local_get_address_format( $business_zipcode, $business_city, $business_state, $atts['show_state'], true ) . "',
				'country': '" . WPSEO_Frontend_Local::get_country( $business_country ) . "',
				'show_country': " . ( $atts['show_country'] ? 'true' : 'false' ) . ",
				'phone': '$business_phone',
				'lat': $coords_lat,
				'long': $coords_long
			} );\n";
			$lats[] = $coords_lat;
			$longs[] = $coords_long;
		}

		$center_lat = min( $lats ) + ( ( max( $lats ) - min( $lats ) ) / 2 );
		$center_long = min( $longs ) + ( ( max( $longs ) - min( $longs ) ) / 2 );

		$map .= '
			var center = new google.maps.LatLng( '. $center_lat .', '. $center_long .' );
			var location_data = new Array();
			' . $location_array;

		if ( $atts['zoom'] == -1 ) {
			$map .= '
				for(var i=0; i<location_data.length; i++) {
					var latLong = new google.maps.LatLng( location_data[i]["lat"], location_data[i]["long"] );
					bounds.extend(latLong);
				}

				center = bounds.getCenter();
				';
		}

		$map .= '
			wpseo_map_options' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '.center = center;
			';
	}

	$map .= '
		var map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ' = new google.maps.Map(document.getElementById("map_canvas' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '"), wpseo_map_options' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ');
		';

	$map .= 'var styleOptions = { name: "Yoast" };
var MAP_STYLE = [ { featureType: "road", elementType: "all", stylers: [ { visibility: "on" } ] } ];
var mapType = new google.maps.StyledMapType(MAP_STYLE, styleOptions);
map.mapTypes.set("Yoast", mapType);
map.setMapTypeId("Yoast");';

	if ( strtolower( $atts['id'] ) == "all" ) {
		$map .= '

			var markers = new Array();
			for(var i=0; i<location_data.length; i++) {
				// Create info window HTML
				var infoWindowHTML = getInfoBubbleText' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '( location_data[i]["name"], location_data[i]["address"], location_data[i]["zip_city"], location_data[i]["business_country"], location_data[i]["show_country"], location_data[i]["phone"], location_data[i]["url"] );

				var latLong = new google.maps.LatLng( location_data[i]["lat"], location_data[i]["long"] );
				markers[i] = new google.maps.Marker({
					position: latLong,
					map: map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ',
					html: infoWindowHTML
				});
			}

			var infoWindow = new google.maps.InfoWindow({
			    content: infoWindowHTML
			});

			for( var i = 0; i < markers.length; i++ ) {
				var marker = markers[i];
				google.maps.event.addListener(marker, "click", function() {
					infoWindow.setContent( this.html );
					console.log( this.html );
					infoWindow.open( map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ', this );
				});
			}


			map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '.fitBounds( bounds );

			';
	} else {
		$map .= 'codeAddress' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '("' . $full_address . '", geocoder, bounds, map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ');';
	}

	$map .= '
	}

	function codeAddress' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '(address, geocoder, bounds, map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ') {
		geocoder.geocode( { "address": address}, function(results, status) {
			if (status == google.maps.GeocoderStatus.OK) {
				endLocation' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ' = results[0].geometry.location
				var markerImage = "' . $marker_image . '";
				var marker = new google.maps.Marker({
					map: map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ',
					position: endLocation' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ',
					' . ( !empty( $marker_image ) ? 'icon: "' . $marker_image . '"' : '' ) . '
				});

				// Create info window HTML
				var infoWindowHTML = getInfoBubbleText' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '( "' . $business_name . '", "' . $business_address . '", "' . wpseo_local_get_address_format( $business_zipcode, $business_city, $business_state, $atts['show_state'], true ) . '", "' . $business_country . '", ' . ( $atts['show_country'] ? 'true' : 'false' ) . ', "' . $business_phone . '" );
				var infoWindow = new google.maps.InfoWindow({
				    content: infoWindowHTML
				});

				google.maps.event.addListener(marker, "click", function() {
					infoWindow.open( map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ', marker );
				});

				bounds.extend(endLocation' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ');
				map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '.fitBounds(bounds);
				map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '.setZoom(' . ( $atts['zoom'] != -1 ? $atts['zoom'] : 12 ) . ');

				' . ( $atts['show_route'] && $atts['id'] != 'all' ? '

				directionsDisplay' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ' = new google.maps.DirectionsRenderer();
				directionsDisplay' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '.setMap(map' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ');
				directionsDisplay' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '.setPanel(document.getElementById("directions' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '"));

				' : '' ) . '
			}
		});
	}

	function getInfoBubbleText' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '( business_name, business_address, business_city_zip, business_country, show_country, business_phone, business_url ) {
		var infoWindowHTML = "<div class=\"wpseo-info-window-wrapper\">";
		if ( business_url != undefined )
			infoWindowHTML += "<a href=\'"+business_url+"\'>";
		infoWindowHTML += "<strong>" + business_name + "</strong>";
		if ( business_url != undefined )
			infoWindowHTML += "</a>";
		infoWindowHTML += "<br>";
		infoWindowHTML += business_address + "<br>";
		infoWindowHTML += business_city_zip + "<br>";
		if( show_country && business_country != "" )
			infoWindowHTML += business_country + "<br>";

		if( business_phone != "" )
			infoWindowHTML += "<a href=\'tel:" + business_phone + "\'>" + business_phone + "<br>";

		infoWindowHTML += "</div>";

		return infoWindowHTML;
	}

	jQuery(document).ready(function($) {
		wpseo_map_init' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '();
	});
	';

	if ( $atts['show_route'] && strtolower( $atts['id'] ) != 'all' && $map_counter == 0 ) {
		$map .= '
			function wpseo_calculate_route( dirDisplay, end, counter ) {
				var start = jQuery("#origin" + ( counter != 0 ? "_" + counter : "" ) ).val();

				var request = {
					origin: start,
					destination: end,
					provideRouteAlternatives: true,
					optimizeWaypoints: true,
					travelMode: google.maps.DirectionsTravelMode.DRIVING
				};

				var directionsService = new google.maps.DirectionsService();

				directionsService.route(request, function(response, status) {
					if (status == google.maps.DirectionsStatus.OK) {
						dirDisplay' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '.setDirections(response);
					}
				});
			}
		';
	}
	$map .= '</script>';

	// Override(reset) the setting for images inside the map
	$map .= '<div id="map_canvas' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '" style="max-width: none !important; width: ' . $atts['width'] . 'px; height: ' . $atts['height'] . 'px;"></div>';

	if ( $atts['show_route'] && $atts['id'] != 'all' ) {
		$map .= '<br/>';
		$map .= '<h2 id="route">' . __( 'Route', 'yoast-local-seo' ) . '</h2>';
		$map .= '<form action="" method="post" id="wpseo-directions-form' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '" onsubmit="wpseo_calculate_route( directionsDisplay' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ', endLocation' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . ',  \'' . $map_counter . '\'); return false;">';
		$map .= '<p>';
		$map .= __( 'Your location', 'yoast-local-seo' ) . ': <input type="text" size="20" id="origin' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '"/>';
		$map .= '<input type="submit" class="wpseo-route-submit" value="' . __( 'Show route', 'yoast-local-seo' ) . '">';
		$map .= '</p>';
		$map .= '</form>';
		$map .= '<div id="directions' . ( $map_counter != 0 ? '_' . $map_counter : '' ) . '"></div>';
	}

	return $map;
}

/**
 * Opening hours shortcode handler
 *
 * @since 0.1
 * @param array $atts Array of shortcode parameters
 * @return string
 */
function wpseo_local_show_opening_hours( $atts ) {
	$atts = wpseo_check_falses( shortcode_atts( array(
		'id' => '',
		'hide_closed' => false
	), $atts ) );

	if ( $atts['id'] == '' )
		$atts['id'] = get_the_ID();

	$options = get_option( 'wpseo_local' );

	$days = array(
		'monday'    => __( 'Monday' ),
		'tuesday'   => __( 'Tuesday' ),
		'wednesday' => __( 'Wednesday' ),
		'thursday'  => __( 'Thursday' ),
		'friday'    => __( 'Friday' ),
		'saturday'  => __( 'Saturday' ),
		'sunday'    => __( 'Sunday' ),
	);

	$output = '<table cellpadding="0" cellspacing="0" class="wpseo-opening-hours">';

	foreach ( $days as $key => $day ) {
		$multiple_opening_hours = isset( $options['multiple_opening_hours'] ) && $options['multiple_opening_hours'] == '1';
		$day_abbr               = ucfirst( substr( $key, 0, 2 ) );

		if ( wpseo_has_multiple_locations() ) {
			$field_name        = '_wpseo_opening_hours_' . $key;
			$value_from        = get_post_meta( $atts['id'], $field_name . '_from', true );
			$value_to          = get_post_meta( $atts['id'], $field_name . '_to', true );
			$value_second_from = get_post_meta( $atts['id'], $field_name . '_second_from', true );
			$value_second_to   = get_post_meta( $atts['id'], $field_name . '_second_to', true );

			$multiple_opening_hours = get_post_meta( $atts['id'], '_wpseo_multiple_opening_hours', true );
			$multiple_opening_hours = $multiple_opening_hours == 1;
		} else {
			$field_name        = 'opening_hours_' . $key;
			$value_from        = isset( $options[$field_name . '_from'] ) ? esc_attr( $options[$field_name . '_from'] ) : '';
			$value_to          = isset( $options[$field_name . '_to'] ) ? esc_attr( $options[$field_name . '_to'] ) : '';
			$value_second_from = isset( $options[$field_name . '_second_from'] ) ? esc_attr( $options[$field_name . '_second_from'] ) : '';
			$value_second_to   = isset( $options[$field_name . '_second_to'] ) ? esc_attr( $options[$field_name . '_second_to'] ) : '';
		}

		if ( $value_from == 'closed' && $atts['hide_closed'] )
			continue;

		$value_from_formatted        = $value_from;
		$value_to_formatted          = $value_to;
		$value_second_from_formatted = $value_second_from;
		$value_second_to_formatted   = $value_second_to;

		if ( !isset( $options['opening_hours_24h'] ) || $options['opening_hours_24h'] != '1' ) {
			$value_from_formatted        = date( 'g:i A', strtotime( $value_from ) );
			$value_to_formatted          = date( 'g:i A', strtotime( $value_to ) );
			$value_second_from_formatted = date( 'g:i A', strtotime( $value_second_from ) );
			$value_second_to_formatted   = date( 'g:i A', strtotime( $value_second_to ) );
		}

		$output .= '<tr>';
		$output .= '<td class="day">' . $day . '&nbsp;</td>';
		$output .= '<td class="time">';
		if ( $value_from != 'closed' && $value_to != 'closed' )
			$output .= '<time itemprop="openingHours" content="' . $day_abbr . ' ' . $value_from . '-' . $value_to . '">' . $value_from_formatted . ' - ' . $value_to_formatted . '</time>';
		else
			$output .= __( 'Closed', 'yoast-local-seo' );

		if ( $multiple_opening_hours ) {
			if ( ( $value_second_from != 'closed' && $value_second_to != 'closed' ) || $value_from != 'closed' && $value_to != 'closed' )
				$output .= '<span class="openingHoursAnd"> ' . __( 'and', 'yoast-local-seo' ) . ' </span><time itemprop="openingHours" content="' . $day_abbr . ' ' . $value_second_from . '-' . $value_second_to . '">' . $value_second_from_formatted . ' - ' . $value_second_to_formatted . '</time>';
		}
		$output .= '</td>';
		$output .= '</tr>';
	}

	$output .= '</table>';


	return $output;
}

/**
 * Get the location details
 *
 * @param string $location_id Optional. Only use this when multiple locations are enabled in the website
 * @return array|bool Array with location details.
 */
function wpseo_get_location_details( $location_id = '' ) {
	$options          = get_option( 'wpseo_local' );
	$location_details = array();

	if ( $options['use_multiple_locations'] == '1' && $location_id == '' ) {
		return false;
	} else if ( $options['use_multiple_locations'] == '1' ) {
		if ( $location_id == null )
			return false;

		$location_details = array(
			'business_address'     => get_post_meta( $location_id, '_wpseo_business_address', true ),
			'business_city'        => get_post_meta( $location_id, '_wpseo_business_city', true ),
			'business_state'       => get_post_meta( $location_id, '_wpseo_business_state', true ),
			'business_zipcode'     => get_post_meta( $location_id, '_wpseo_business_zipcode', true ),
			'business_country'     => get_post_meta( $location_id, '_wpseo_business_country', true ),
			'business_phone'       => get_post_meta( $location_id, '_wpseo_business_phone', true ),
			'business_coords_lat'  => get_post_meta( $location_id, '_wpseo_coordinates_lat', true ),
			'business_coords_long' => get_post_meta( $location_id, '_wpseo_coordinates_long', true )
		);
	} else if ( $options['use_multiple_locations'] != '1' ) {
		$location_details = array(
			'business_address'     => $options['location_address'],
			'business_city'        => $options['location_city'],
			'business_state'       => $options['location_state'],
			'business_zipcode'     => $options['location_zipcode'],
			'business_country'     => $options['location_country'],
			'business_phone'       => $options['location_phone'],
			'business_coords_lat'  => $options['location_coords_lat'],
			'business_coords_long' => $options['location_coords_long']
		);
	}

	return $location_details;
}

/**
 * Checks whether website uses multiple location (Custom Post Type) or not (info from options)
 *
 * @return bool Multiple locations enbaled or not
 */
function wpseo_has_multiple_locations() {
	$options = get_option( 'wpseo_local' );

	return isset( $options['use_multiple_locations'] ) && $options['use_multiple_locations'] == '1';
}

/**
 * @param bool $use_24h
 * @param int  $default
 * @return string
 */
function wpseo_show_hour_options( $use_24h = false, $default = 9 ) {
	$output = '<option value="closed">' . __( 'Closed', 'yoast-local-seo' ) . '</option>';

	for ( $i = 0; $i < 24; $i++ ) {
		$time       = strtotime( sprintf( '%1$02d', $i ) . ':00' );
		$time_half  = strtotime( sprintf( '%1$02d', $i ) . ':30' );
		$value      = date( 'H:i', $time );
		$value_half = date( 'H:i', $time_half );

		$time_value      = date( 'g:i A', $time );
		$time_half_value = date( 'g:i A', $time_half );

		if ( $use_24h ) {
			$time_value      = date( 'H:i', $time );
			$time_half_value = date( 'H:i', $time_half );
		}

		$output .= '<option value="' . $value . '"' . selected( $value, $default, false ) . '>' . $time_value . '</option>';
		$output .= '<option value="' . $value_half . '" ' . selected( $value_half, $default, false ) . '>' . $time_half_value . '</option>';
	}

	return $output;
}

/**
 * @param string $business_zipcode
 * @param string $business_city
 * @param string $business_state
 * @param bool   $show_state
 * @param bool   $escape_output
 * @param bool   $use_tags
 *
 * @return string
 */
function wpseo_local_get_address_format( $business_zipcode = '', $business_city = '', $business_state = '', $show_state = false, $escape_output = false, $use_tags = true ) {
	$output = '';
	$options = get_option( 'wpseo_local' );
	$address_format          = !empty( $options['address_format'] ) ? $options['address_format'] : 'address-state-postal';
	$business_city_string = $business_city;
	if( $use_tags )
		$business_city_string = '<span class="locality" itemprop="addressLocality"> ' . $business_city . '</span>';
	$business_state_string = $business_state;
	if( $use_tags )
		$business_state_string = '<span  class="region" itemprop="addressRegion">' . $business_state . '</span>';
	$business_zipcode_string = $business_zipcode;
	if( $use_tags )
		$business_zipcode_string = '<span class="postal-code" itemprop="postalCode">' . $business_zipcode . '</span>';

	if ( in_array( $address_format, array( '', 'address-state-postal', 'address-state-postal-comma', 'address-postal', 'address-postal-comma' ) ) ) {
		if ( !empty( $business_city ) ) {
			$output .= $business_city_string;

			if ( $address_format == 'address-state-postal' || $address_format == 'address-state-postal-comma' )
				$output .= ', ';
			else if ( $address_format != 'address-postal-comma' )
				$output .= ' ';
		}

		if ( $address_format == 'address-state-postal' || $address_format == 'address-state-postal-comma' ) {
			if ( $show_state && !empty( $business_state ) ) {
				$output .= $business_state_string;
				$output .= $address_format != 'address-state-postal-comma' ? ' ' : '';
			}
		}

		if ( !empty( $business_zipcode ) ) {
			if ( $address_format == 'address-state-postal-comma' || $address_format == 'address-postal-comma' )
				$output .= ', ';
			$output .= $business_zipcode_string;
		}
	} else {
		if ( !empty( $business_zipcode ) ) {
			$output .= $business_zipcode_string;
		}
		if ( !empty( $business_city ) ) {
			$output .= ' ' . $business_city_string;
		}
		if ( $show_state && !empty( $business_state ) ) {
			$output .= ' (' . $business_state_string . ')';
		}

	}

	if( $escape_output )
		$output = addslashes( $output );

	return $output;
}

/**
 * Checks whether array keys are meant to mean false but aren't set to false.
 *
 * @param $atts array Array to check
 * @return array
 */
function wpseo_check_falses( $atts ) {
	if ( !is_array( $atts ) )
		return $atts;

	foreach ( array_keys( $atts ) as $key ) {
		if ( $atts[ $key ] == 'off' || $atts[ $key ] == 'no' ) {
			$atts[ $key ] = false;
		}
	}

	return $atts;
}