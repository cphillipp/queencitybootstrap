<?php

add_action( 'widgets_init', create_function( '', 'return register_widget("WPSEO_Show_OpeningHours");' ) );

class WPSEO_Show_OpeningHours extends WP_Widget {
	/** constructor */
	function WPSEO_Show_OpeningHours() {
		$widget_options = array(
			'classname'   => 'WPSEO_Show_OpeningHours',
			'description' => __( 'Shows opening hours of locations in Schema.org standards.', 'yoast-local-seo' )
		);
		parent::WP_Widget( false, $name = __( 'WP SEO - Show Opening hours', 'yoast-local-seo' ), $widget_options );
	}

	/** @see WP_Widget::widget */
	function widget( $args, $instance ) {
		$title       = apply_filters( 'widget_title', $instance['title'] );
		$location_id = !empty( $instance['location_id'] ) ? $instance['location_id'] : '';

		if ( isset( $args['before_widget'] ) )
			echo $args['before_widget'];

		if ( !empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];

		$args = array(
			'id'           => $location_id,
			'from_widget'  => true,
			'widget_title' => $title,
			'before_title' => $args['before_title'],
			'after_title'  => $args['after_title'],
			'hide_closed'  => isset( $instance['hide_closed'] ) ? 1 : 0,
		);

		echo wpseo_local_show_opening_hours( $args );

		if ( isset( $args['after_widget'] ) )
			echo $args['after_widget'];
	}

	/** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {
		$instance                = $old_instance;
		$instance['title']       = esc_attr( $new_instance['title'] );
		$instance['location_id'] = esc_attr( $new_instance['location_id'] );
		$instance['hide_closed'] = esc_attr( $new_instance['hide_closed'] );
		return $instance;
	}

	/** @see WP_Widget::form */
	function form( $instance ) {
		$title       = !empty( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$location_id = !empty( $instance['location_id'] ) ? esc_attr( $instance['location_id'] ) : '';
		$hide_closed = !empty( $instance['hide_closed'] ) && esc_attr( $instance['hide_closed'] ) == '1';
		?>
		<p>
			<label
				for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'yoast-local-seo' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>"/>
		</p>

		<?php if ( wpseo_has_multiple_locations() ) { ?>
			<p>
				<label
					for="<?php echo $this->get_field_id( 'location_id' ); ?>"><?php _e( 'Location:', 'yoast-local-seo' ); ?></label>
				<?php
				$args = array(
					'post_type'      => 'wpseo_locations',
					'orderby'        => 'name',
					'order'          => 'ASC',
					'posts_per_page' => -1
				);
				$locations = get_posts( $args );
				?>
				<select name="<?php echo $this->get_field_name( 'location_id' ); ?>"
						id="<?php echo $this->get_field_id( 'location_id' ); ?>">
					<option value=""><?php _e( 'Select a location', 'yoast-local-seo' ); ?></option>
					<?php foreach ( $locations as $location ) { ?>
						<option
							value="<?php echo $location->ID; ?>" <?php selected( $location_id, $location->ID ); ?>><?php echo get_the_title( $location->ID ); ?></option>
					<?php } ?>
				</select>
			</p>
		<?php } ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'hide_closed' ); ?>">
				<input id="<?php echo $this->get_field_id( 'hide_closed' ); ?>"
					   name="<?php echo $this->get_field_name( 'hide_closed' ); ?>" type="checkbox"
					   value="1" <?php echo !empty( $hide_closed ) ? ' checked="checked"' : ''; ?> />
				<?php _e( 'Hide closed days', 'yoast-local-seo' ); ?>
			</label>
		</p>

	<?php
	}

}
