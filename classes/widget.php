<?php
/**
* Volunteer sign-up-sheets Widget class
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Widget extends WP_Widget
{
	private $data;
	private $main_options;

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
	 		'pta_sus_widget', // Base ID
			'PTA Volunteer Sign-up Sheet List', // Name
			array( 'description' => __( 'PTA Volunteer Sign-up Sheet list Widget.', 'pta-volunteer-sign-up-sheets' ), ) // Args
		);
        global $pta_sus;
		$this->data = $pta_sus->data;
		$this->main_options = get_option('pta_volunteer_sus_main_options');
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		// Check for test mode
		if(isset($this->main_options['enable_test_mode']) && true === $this->main_options['enable_test_mode'] ) {
			// don't show anything in the widget area while in test mode
            if (!current_user_can( 'manage_options' ) && !current_user_can( 'manage_signup_sheets' )) {
                return;
            }
        }
        $show_hidden = false;
        $hidden = '';
        // Allow admin or volunteer managers to view hidden sign up sheets
        if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
            $show_hidden = true;
            $hidden = '<br/><span style="color:red;"><strong>(--'.__('Hidden!', 'pta-volunteer-sign-up-sheets').'--)</strong></span>';
        }
        
        $sort_by = isset($instance['sort_by']) && in_array($instance['sort_by'], array('first_date', 'last_date', 'title', 'id')) ? $instance['sort_by'] : 'first_date';
        $order = isset($instance['order']) && in_array($instance['order'], array('ASC', 'DESC')) ? $instance['order'] : 'ASC';

		// Check if there are sheets first, if not, we won't show anything
		$sheets = $this->data->get_sheets(false, true, $show_hidden, $sort_by, $order);
        
        if (empty($sheets)) {
            return;
        }
        if ($this->main_options['show_ongoing_last']) {
        	// Move ongoing events to end of our sheets array
        	foreach ($sheets as $key => $sheet) {
        		if ('Ongoing' == $sheet->type) {
        			$move_me = $sheet;
        			unset($sheets[$key]);
        			$sheets[] = $move_me;
        		}
        	}
        }
		extract( $args );
		$title = apply_filters( 'pta_sus_widget_title', $instance['title'] );
		$num_items = apply_filters( 'pta_sus_widget_num_items', (int)$instance['num_items'] );
		$list_class = (isset($instance['list_class'])) ? apply_filters( 'pta_sus_widget_list_class', $instance['list_class'] ) : '';
		$permalink = apply_filters( 'pta_sus_widget_permalink', get_permalink( $this->main_options['volunteer_page_id'], $leavename = false ) );
		if(isset($instance['show_what'])) {
			$show_what = in_array($instance['show_what'], array('both','signups','events')) ? $instance['show_what'] : 'both';
		} else {
			$show_what = 'both';
		}


		// For themes
		echo $before_widget;
		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;

		// Sheet link list
		if($num_items > 0) {
			$sheets = array_slice($sheets, 0, $num_items);
		}
		echo '<ul';
		if (!empty($list_class)) {
			echo 'class="'.esc_attr($list_class).'"';
		} 
		echo '>';
		$single = false;
		foreach ($sheets as $sheet) {
			if ( '1' == $sheet->visible) {
                $is_hidden = '';
            } else {
                $is_hidden = $hidden;
            }
            if ( !$this->main_options['show_ongoing_in_widget'] && "Ongoing" == $sheet->type ) continue;

			if('signups' === $show_what && $sheet->no_signups) continue;
			if('events' === $show_what && !$sheet->no_signups) continue;

        	$sheet_url = $permalink.'?sheet_id='.$sheet->id;
        	$first_date = ($sheet->first_date == '0000-00-00') ? '' : date('M d', strtotime($sheet->first_date));
        	$last_date = ($sheet->last_date == '0000-00-00') ? '' : date('M d', strtotime($sheet->last_date));
        	if ($first_date == $last_date) $single = true;

        	$open_spots = ($this->data->get_sheet_total_spots($sheet->id) - $this->data->get_sheet_signup_count($sheet->id));

			echo '<li><strong><a href="'.esc_url($sheet_url).'">'.esc_html($sheet->title).'</a></strong>'.$is_hidden.'<br/>';
        	if ($single) {
        		echo esc_html($first_date);
        	} else {
        		echo esc_html($first_date). ' - '.esc_html($last_date);
        	}
			if(!$sheet->no_signups) {
				echo ' &ndash; <em>'.(int)$open_spots.' '.__('Open Spots', 'pta-volunteer-sign-up-sheets').'</em></li>';
			}

		}
		echo '</ul>';

		// For themes
		echo $after_widget;
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
     *
     * @return string
	 */
	public function form( $instance ) {
		/* Set up default widget settings. */
		$defaults = array( 'title' => __('Current Volunteer Opportunities', 'pta-volunteer-sign-up-sheets'), 'num_items' => 10, 'permalink' => 'volunteer-sign-ups', 'show_what' => 'both', 'sort_by' => 'first_date', 'order' => 'ASC', 'list_class' => '');
		$instance = wp_parse_args( (array) $instance, $defaults );
		?>
		<p>
		<label for="<?php echo $this->get_field_name( 'title' ); ?>"><?php _e( 'Title:', 'pta-volunteer-sign-up-sheets' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_name( 'num_items' ); ?>"><?php _e( '# of items to show (-1 for all):', 'pta-volunteer-sign-up-sheets' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'num_items' ); ?>" name="<?php echo $this->get_field_name( 'num_items' ); ?>" type="text" value="<?php echo esc_attr( $instance['num_items'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_name( 'show_what' ); ?>"><?php _e( 'What to show?', 'pta-volunteer-sign-up-sheets' ); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'show_what' ); ?>" name="<?php echo $this->get_field_name( 'show_what' ); ?>">
				<option value="both" <?php selected($instance['show_what'], 'both' ); ?>><?php _e( 'Both', 'pta-volunteer-sign-up-sheets' ); ?></option>
				<option value="signups" <?php selected($instance['show_what'], 'signups' ); ?>><?php _e( 'Volunteer Events (with sign-ups)', 'pta-volunteer-sign-up-sheets' ); ?></option>
				<option value="events" <?php selected($instance['show_what'], 'events' ); ?>><?php _e( 'No Sign-Up Events (display events only)', 'pta-volunteer-sign-up-sheets' ); ?></option>
			</select>
		</p>
        <p>
            <label for="<?php echo $this->get_field_name( 'sort_by' ); ?>"><?php _e( 'Sort By:', 'pta-volunteer-sign-up-sheets' ); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id( 'sort_by' ); ?>" name="<?php echo $this->get_field_name( 'sort_by' ); ?>">
                <option value="first_date" <?php selected($instance['sort_by'], 'first_date' ); ?>><?php _e( 'First Date', 'pta-volunteer-sign-up-sheets' ); ?></option>
                <option value="last_date" <?php selected($instance['sort_by'], 'last_date' ); ?>><?php _e( 'Last Date', 'pta-volunteer-sign-up-sheets' ); ?></option>
                <option value="title" <?php selected($instance['sort_by'], 'title' ); ?>><?php _e( 'Title', 'pta-volunteer-sign-up-sheets' ); ?></option>
                <option value="id" <?php selected($instance['sort_by'], 'id' ); ?>><?php _e( 'Sheet ID', 'pta-volunteer-sign-up-sheets' ); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_name( 'order' ); ?>"><?php _e( 'Sort Order:', 'pta-volunteer-sign-up-sheets' ); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id( 'order' ); ?>" name="<?php echo $this->get_field_name( 'order' ); ?>">
                <option value="ASC" <?php selected($instance['order'], 'ASC' ); ?>><?php _e( 'Ascending', 'pta-volunteer-sign-up-sheets' ); ?></option>
                <option value="DESC" <?php selected($instance['order'], 'DESC' ); ?>><?php _e( 'Descending', 'pta-volunteer-sign-up-sheets' ); ?></option>
            </select>
        </p>
		<p>
		<label for="<?php echo $this->get_field_name( 'list_class' ); ?>"><?php _e( 'CSS Class for ul list of signups', 'pta-volunteer-sign-up-sheets' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'list_class' ); ?>" name="<?php echo $this->get_field_name( 'list_class' ); ?>" type="text" value="<?php echo esc_attr( $instance['list_class'] ); ?>" />
		</p>
		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( !empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['num_items'] = ( !empty( $new_instance['num_items'] ) ) ? (int)strip_tags( $new_instance['num_items'] ) : '';
		$instance['list_class'] = ( !empty( $new_instance['list_class'] ) ) ? sanitize_text_field( $new_instance['list_class'] ) : '';
		$instance['show_what'] = ( !empty( $new_instance['show_what'] ) ) ? sanitize_key( $new_instance['show_what'] ) : 'both';
		$instance['sort_by'] = ( !empty( $new_instance['sort_by'] ) && in_array($new_instance['sort_by'], array('first_date', 'last_date', 'title', 'id')) ) ?$new_instance['sort_by'] : 'first_date';
		$instance['order'] = ( !empty( $new_instance['order'] ) && in_array($new_instance['order'], array('ASC', 'DESC')) ) ? $new_instance['order'] : 'ASC';
		return $instance;
	}

} // End of class
/*EOF*/