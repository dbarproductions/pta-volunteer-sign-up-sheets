<?php
/**
* Class to create admin list tables
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH.'wp-admin/includes/screen.php';
    require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
}
if (!class_exists('PTA_SUS_Data')) require_once 'data.php';

class PTA_SUS_List_Table extends WP_List_Table
{

    private $rows = array();
    private $show_trash;
    
    /**
    * construct
    * 
    * @param    bool    show trash?
    */
    function __construct()
    {
        global $status, $page;
                
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'sheet',
            'plural'    => 'sheets',
            'ajax'      => false,
            'screen'    => null
        ) );
        
    }
    
    /**
    * Set data and convert an object into an array if neccessary
    * 
    * @param    mixed   object or array of data
    * @return   array   data
    */
    function set_data($list_data)
    {
        return (array)$list_data;
    }
    
    /**
    * Process columns if not defined in a specific column like column_title
    * 
    * @param    array   $item one row of data
    * @param    string   $column_name name of column to be processed
    * @return   string  text that will go in the column's TD
    */
    function column_default($item, $column_name) {
        switch($column_name){
            case 'id':
                return $item[$column_name];
            case 'first_date':
            case 'last_date':
                return ($item[$column_name] === '0000-00-00') ? __("N/A", 'pta-volunteer-sign-up-sheets') : mysql2date( get_option('date_format'), $item[$column_name], $translate = true );
            case 'num_dates':
                $dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($item['id']);
                if(!$dates) {
                    return '0';
                }
                $count = 0;
                foreach ($dates as $date) {
                    if("0000-00-00" !== $date) {
                        $count++;
                    }
                }
                if ($count > 0) {
                    return $count;
                }
                return __("N/A", 'pta-volunteer-sign-up-sheets');
            case 'task_num':
                return count(PTA_SUS_Task_Functions::get_tasks($item['id']));
            case 'spot_num':
                return PTA_SUS_Sheet_Functions::get_sheet_total_spots($item['id'], '');
            case 'filled_spot_num':
                return PTA_SUS_Sheet_Functions::get_sheet_signup_count($item['id']);
            default:
                // Allow extensions to add column content
                return apply_filters( 'pta_sus_process_other_columns', '', $item, $column_name );
        }
    }
    
    /**
    * Custom column title processer
    * 
    * @see      WP_List_Table::::single_row_columns()
    * @param    array   one row of data
    * @return   string  text that will go in the title column's TD
    */
    function column_title($item)
    {
        // Set actions
        if ($this->show_trash) {
            $actions = array('untrash' => __('Restore', 'pta-volunteer-sign-up-sheets' ), 'delete' => __('Delete', 'pta-volunteer-sign-up-sheets'));
        } else {
            $actions = array(
                'view_signup' => __('View Sign-ups', 'pta-volunteer-sign-up-sheets'),
                'edit_sheet' => __('Edit Sheet', 'pta-volunteer-sign-up-sheets'),
                'edit_tasks' => __('Edit Tasks', 'pta-volunteer-sign-up-sheets'),
                'copy' => __('Copy', 'pta-volunteer-sign-up-sheets'),
                'reschedule' => __('Reschedule/Copy', 'pta-volunteer-sign-up-sheets'),
                'trash' => __('Trash', 'pta-volunteer-sign-up-sheets'),
            );
        }
        if('Ongoing' === $item['type'] || 'Recurring' === $item['type']) {
            unset($actions['reschedule']); // can't reschedule Ongoing or Recurring type sheets
        }
        // Check permissions for actions - only show edit/delete if user is author or can manage others
        $can_manage_others = current_user_can( 'manage_others_signup_sheets' );
        $current_user_id = get_current_user_id();
        $is_author = false;
        
        // Check if current user is the author (need to get sheet object to check author_id)
        if ( isset( $item['id'] ) ) {
            $sheet = pta_sus_get_sheet( $item['id'] );
            if ( $sheet && $sheet->author_id == $current_user_id ) {
                $is_author = true;
            }
        }
        
        $show_actions = array();
        foreach ($actions as $action_slug => $action_name) {
            // For edit/delete actions, check permissions
            if (!$can_manage_others && !$is_author && in_array($action_slug, array('edit_sheet', 'edit_tasks', 'trash', 'delete')) ) {
                continue; // Skip this action - user doesn't have permission
            }
            
            if ('edit_sheet' === $action_slug || 'edit_tasks' === $action_slug) {
                $page = 'pta-sus-settings_modify_sheet';
            } else {
                $page = $_GET['page'];
            }
            $url = sprintf('?page=%s&action=%s&sheet_id=%s', $page, $action_slug, $item['id']);
            $nonced_url = wp_nonce_url($url, $action_slug, '_sus_nonce');
            $show_actions[$action_slug] = sprintf('<a href="%s">%s</a>', $nonced_url, $action_name);
        }
        $view_url = sprintf('?page=%s&action=view_signup&sheet_id=%s', $_GET['page'], $item['id']);
        $nonced_view_url = wp_nonce_url( $view_url, 'view_signup', '_sus_nonce' );
        return sprintf('<strong><a href="%1$s">%2$s</a></strong>%3$s', 
            $nonced_view_url,  // %1$s
            $item['title'], // %2$s
            $this->row_actions($show_actions) // %3$s
        );
    }

    function column_visible($item) {
        if ($item['visible']) {
            $display = __("Yes", 'pta-volunteer-sign-up-sheets');
        } else {
            $display = '<strong><span style="color:red;">'.__("NO", "pta_volunteer_sus").'</span></strong>';
        }
        $toggle_url = sprintf('?page=%s&action=toggle_visibility&sheet_id=%s', $_GET['page'], $item['id']);
        $nonced_toggle_url = wp_nonce_url( $toggle_url, 'toggle_visibility', '_sus_nonce' );
        return sprintf('<strong><a href="%1$s">%2$s</a></strong>', 
            $nonced_toggle_url,  // %1$s
            $display // %2$s
        );
    }

    function column_type($item) {
        $sheet_types = apply_filters( 'pta_sus_sheet_form_sheet_types',
        array(
            'Single' => __('Single', 'pta-volunteer-sign-up-sheets'),
            'Recurring' => __('Recurring', 'pta-volunteer-sign-up-sheets'),
            'Multi-Day' => __('Multi-Day', 'pta-volunteer-sign-up-sheets'),
            'Ongoing' => __('Ongoing', 'pta-volunteer-sign-up-sheets')
            ));
        $type = $item['type'];
        return esc_html($sheet_types[$type]);
    }

    function column_author( $item ) {
        if ( empty( $item['author_email'] ) ) {
            return '&#8212;';
        }
        $user = get_user_by( 'email', $item['author_email'] );
        if ( $user ) {
            $first = trim( $user->first_name );
            $last  = trim( $user->last_name );
            if ( ! empty( $first ) || ! empty( $last ) ) {
                return esc_html( trim( $first . ' ' . $last ) );
            }
            return esc_html( $user->display_name );
        }
        return esc_html( $item['author_email'] );
    }

    /**
    * Checkbox column method
    * 
    * @see      WP_List_Table::::single_row_columns()
    * @param    array   one row of data
    * @return   string  text that will go in the column's TD
    */
    function column_cb($item)
    {
        return sprintf( '<input type="checkbox" name="sheets[]" value="%d" />', $item['id'] );
    }
    
    /**
    * All columns
    */
    function get_columns()
    {
        $columns = apply_filters( 'pta_sus_list_table_columns', array(
            'id'                => __('ID#', 'pta-volunteer-sign-up-sheets'),
            'title'             => __('Title', 'pta-volunteer-sign-up-sheets'),
            'visible'           => __('Visible', 'pta-volunteer-sign-up-sheets'),
            'type'              => __('Event Type', 'pta-volunteer-sign-up-sheets'),
            'first_date'        => __('First Date', 'pta-volunteer-sign-up-sheets'),
            'last_date'         => __('Last Date', 'pta-volunteer-sign-up-sheets'),
            'num_dates'         => __('# Dates', 'pta-volunteer-sign-up-sheets'),
            'task_num'          => __('# Tasks', 'pta-volunteer-sign-up-sheets'),
            'spot_num'          => __('Total Spots', 'pta-volunteer-sign-up-sheets'),
            'filled_spot_num'   => __('Filled Spots', 'pta-volunteer-sign-up-sheets'),
        ) );

        // Add Author column after Title for admins/managers who can view all sheets
        if ( current_user_can( 'manage_others_signup_sheets' ) ) {
            $new_columns = array();
            foreach ( $columns as $key => $value ) {
                $new_columns[ $key ] = $value;
                if ( 'title' === $key ) {
                    $new_columns['author'] = __( 'Author', 'pta-volunteer-sign-up-sheets' );
                }
            }
            $columns = $new_columns;
        }

        // Add checkbox if bulk actions is available
        if (count($this->get_bulk_actions()) > 0) {
            $columns = array_reverse($columns, true);
            $columns['cb'] = '<input type="checkbox" />';
            $columns = array_reverse($columns, true);
        }
        
        return $columns;
    }
    
    /**
    * All sortable columns
    */
    function get_sortable_columns()
    {
        $columns = apply_filters( 'pta_sus_list_table_sortable_columns', array(
            'id'    => array('id',false),
            'visible'    => array('visible',false),
            'title' => array('title',false),
            'first_date'  => array('first_date',true),
            'last_date'  => array('last_date',false),
        ) );
        if ( current_user_can( 'manage_others_signup_sheets' ) ) {
            $columns['author'] = array( 'author_email', false );
        }
        return $columns;
    }
    
    /**
    * All allowed bulk actions
    * 
    */
    function get_bulk_actions()
    {

        if ($this->show_trash) {
            $actions = array(
                'bulk_delete' => __('Delete', 'pta-volunteer-sign-up-sheets'),
                'bulk_restore' => __('Restore', 'pta-volunteer-sign-up-sheets')
            );
        } else {
            $actions = array(
                'bulk_trash' => __('Move to Trash', 'pta-volunteer-sign-up-sheets'),
                'bulk_toggle_visibility' => __('Toggle Visibility', 'pta-volunteer-sign-up-sheets')
            );
        }
        
        return $actions;
    }
    
    /**
    * Process bulk actions if called
    * 
    */
    function process_bulk_action() {
        $bulk_actions = array('bulk_trash', 'bulk_delete', 'bulk_restore', 'bulk_toggle_visibility' );
        if( !in_array($this->current_action(), $bulk_actions)) {
            return;
        }
        // security check!       
        if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {

            $nonce  = htmlspecialchars($_REQUEST['_wpnonce']);
            $action = 'bulk-' . $this->_args['plural'];

            if ( ! wp_verify_nonce( $nonce, $action ) )
                wp_die( 'Nope! Security check failed!' );
        }
        // Check user permissions for bulk actions
        $can_manage_others = current_user_can( 'manage_others_signup_sheets' );
        $current_user_id = get_current_user_id();
        
        // Process bulk actions
        if('bulk_trash' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $sheet = pta_sus_get_sheet($id);
                if ($sheet) {
                    // Check permission: user must be able to manage others OR be the author
                    if (!$can_manage_others && $sheet->author_id != $current_user_id) {
                        echo '<div class="error"><p>'.sprintf(__("Permission denied for sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                        continue;
                    }
                    $sheet->trash = true;
                    $result = $sheet->save();
                    if ($result !== false) {
                        $count++;
                    } else {
                        echo '<div class="error"><p>'.sprintf(__("Error moving sheet# %d to trash.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                    }
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error moving sheet# %d to trash.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("%d sheets have been moved to the trash.", 'pta-volunteer-sign-up-sheets'), $count).'</p></div>';
        } elseif ('bulk_delete' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $sheet = pta_sus_get_sheet($id);
                // Check permission: user must be able to manage others OR be the author
                if ($sheet && !$can_manage_others && $sheet->author_id != $current_user_id) {
                    echo '<div class="error"><p>'.sprintf(__("Permission denied for sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                    continue;
                }
                $deleted = PTA_SUS_Sheet_Functions::delete_sheet($id);
                if ($deleted) {
                    $count++;
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error deleting sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("%d sheets have been deleted.", 'pta-volunteer-sign-up-sheets'), $count).'</p></div>';
        } elseif('bulk_restore' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $sheet = pta_sus_get_sheet($id);
                if ($sheet) {
                    // Check permission: user must be able to manage others OR be the author
                    if (!$can_manage_others && $sheet->author_id != $current_user_id) {
                        echo '<div class="error"><p>'.sprintf(__("Permission denied for sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                        continue;
                    }
                    $sheet->trash = false;
                    $result = $sheet->save();
                    if ($result !== false) {
                        $count++;
                    } else {
                        echo '<div class="error"><p>'.sprintf(__("Error restoring sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                    }
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error restoring sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("%d sheets have been restored.", 'pta-volunteer-sign-up-sheets'), $count).'</p></div>';
        } elseif('bulk_toggle_visibility' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $sheet = pta_sus_get_sheet($id);
                if(empty($sheet)) {
                    continue;
                }
                // Check permission: user must be able to manage others OR be the author
                if (!$can_manage_others && $sheet->author_id != $current_user_id) {
                    echo '<div class="error"><p>'.sprintf(__("Permission denied for sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                    continue;
                }
                if ($sheet->toggle_visibility()) {
                    $count++;
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error toggling visibility for sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("Visibility toggled for %d sheets.", 'pta-volunteer-sign-up-sheets'), $count).'</p></div>';
        }
    }
    
    function set_show_trash($show_trash) {
        $this->show_trash = $show_trash;
    }

    /**
     * Get data and prepare for use
     */
    function prepare_items() {
        $this->process_bulk_action();
        
        // Check if we need to filter by author (for Signup Sheet Authors)
        $can_manage_others = current_user_can( 'manage_others_signup_sheets' );
        $author_id = $can_manage_others ? null : get_current_user_id();

        // Apply admin author filter when a manager selects a specific author
        $filter_author_email = '';
        if ( $can_manage_others && isset( $_REQUEST['pta-filter-submit'] ) && ! empty( $_REQUEST['pta-author-filter'] ) ) {
            $filter_author_email = sanitize_email( wp_unslash( $_REQUEST['pta-author-filter'] ) );
        }

        // Use get_sheets_by_args to support author filtering
        $args = array(
            'trash'        => $this->show_trash,
            'active_only'  => false,
            'show_hidden'  => true,
            'author_id'    => $author_id,
            'author_email' => $filter_author_email,
        );
        $rows = PTA_SUS_Sheet_Functions::get_sheets_by_args( $args );

        foreach ($rows AS $k => $v) {
            // if search is set, skip any that title doesn't match search string
            if (isset($_REQUEST['s']) && '' !== $_REQUEST['s']) {
                if (false === stripos($v->title, $_REQUEST['s'])) {
                    continue;
                }
            }

            if (isset($_REQUEST['pta-filter-submit']) && isset($_REQUEST['pta-visible-filter']) && in_array($_REQUEST['pta-visible-filter'], array('visible','hidden'))) {
                $compare = 'visible' === $_REQUEST['pta-visible-filter'] ? "1" : "0";
                if ($compare != $v->visible) {
                    continue;
                }
            }

            if (isset($_REQUEST['pta-filter-submit']) && isset($_REQUEST['pta-type-filter']) && in_array($_REQUEST['pta-type-filter'], array('Single','Multi-Day','Recurring','Ongoing'))) {
                if ($_REQUEST['pta-type-filter'] !== $v->type) {
                    continue;
                }
            }

            // Use to_array() method instead of casting
            $this->rows[$k] = $v->to_array();
        }

        $this->rows = apply_filters('pta_sus_list_table_prepare_items_filtered_rows', $this->rows);
        $per_page = $this->get_items_per_page('sheets_per_page', 20);

        $this->_column_headers = $this->get_column_info();

        // Sort Data
        function usort_reorder($a, $b) {
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'title';
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc';
            // Coerce to string: array-type columns (e.g. sus_group) are handled by the filter below
            $val_a = isset($a[$orderby]) && !is_array($a[$orderby]) ? (string)$a[$orderby] : '';
            $val_b = isset($b[$orderby]) && !is_array($b[$orderby]) ? (string)$b[$orderby] : '';
            $result = strcmp($val_a, $val_b);
            $result = apply_filters('pta_sus_list_table_usort', $result, $a, $b, $orderby);
            return ($order === 'asc') ? $result : -$result;
        }
        usort($this->rows, 'usort_reorder');

        $current_page = $this->get_pagenum();
        $total_items = count($this->rows);
        $this->rows = array_slice($this->rows, (($current_page - 1) * $per_page), $per_page);
        $this->items = $this->rows;

        // Register pagination calculations
        $this->set_pagination_args(array(
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => ceil($total_items / $per_page)
        ));
    }

	function extra_tablenav( $which ) {
		if ( $which === "top" ){
		    $visible = $_REQUEST['pta-visible-filter'] ?? '';
			$type = $_REQUEST['pta-type-filter'] ?? '';
			?>
			<div class="alignleft actions bulkactions">
                <select name="pta-visible-filter" class="pta-filter">
                    <option value=""><?php _e('Show All', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="visible" <?php selected('visible',$visible,true); ?>><?php _e('Show Only Visible', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="hidden" <?php selected('hidden',$visible,true); ?>><?php _e('Show Only Hidden', 'pta-volunteer-sign-up-sheets'); ?></option>
                </select>
                <select name="pta-type-filter" class="pta-filter">
                    <option value=""><?php _e('Show All Event Types', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="Single" <?php selected('Single',$type,true); ?>><?php _e('Show Only Single Events', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="Multi-Day" <?php selected('Multi-Day',$type,true); ?>><?php _e('Show Only Multi-Day Events', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="Recurring" <?php selected('Recurring',$type,true); ?>><?php _e('Show Only Recurring Events', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="Ongoing" <?php selected('Ongoing',$type,true); ?>><?php _e('Show Only Ongoing Events', 'pta-volunteer-sign-up-sheets'); ?></option>
                </select>
			</div>
			<?php
            do_action('pta_sus_sheets_list_table_after_filters');
			// Author filter — only visible to admins/managers who can view all sheets
			if ( current_user_can( 'manage_others_signup_sheets' ) ) :
				global $wpdb;
				$_sus_sheets_table  = $wpdb->prefix . 'pta_sus_sheets';
				$_sus_author_emails = $wpdb->get_col(
					"SELECT DISTINCT author_email FROM $_sus_sheets_table WHERE author_email IS NOT NULL AND author_email != '' AND trash = 0 ORDER BY author_email ASC"
				);
				if ( ! empty( $_sus_author_emails ) ) :
					$_sus_selected_author = isset( $_REQUEST['pta-author-filter'] ) ? sanitize_email( wp_unslash( $_REQUEST['pta-author-filter'] ) ) : '';
					?>
					<div class="alignleft actions bulkactions">
						<select name="pta-author-filter" class="pta-filter">
							<option value=""><?php _e( 'Show All Authors', 'pta-volunteer-sign-up-sheets' ); ?></option>
							<?php foreach ( $_sus_author_emails as $_sus_author_email ) :
								$_sus_display = $_sus_author_email;
								$_sus_user    = get_user_by( 'email', $_sus_author_email );
								if ( $_sus_user ) {
									$_sus_first = trim( $_sus_user->first_name );
									$_sus_last  = trim( $_sus_user->last_name );
									if ( ! empty( $_sus_first ) || ! empty( $_sus_last ) ) {
										$_sus_display = esc_html( trim( $_sus_first . ' ' . $_sus_last ) ) . ' (' . $_sus_author_email . ')';
									} else {
										$_sus_display = esc_html( $_sus_user->display_name ) . ' (' . $_sus_author_email . ')';
									}
								}
								?>
								<option value="<?php echo esc_attr( $_sus_author_email ); ?>" <?php selected( $_sus_author_email, $_sus_selected_author ); ?>><?php echo esc_html( $_sus_display ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php
				endif;
			endif;
			submit_button( __('Filter Sheets','pta-volunteer-sign-up-sheets'), '', 'pta-filter-submit', false, array( 'id' => 'filter-submit' ) );
		}
	}
    
}
/* EOF */