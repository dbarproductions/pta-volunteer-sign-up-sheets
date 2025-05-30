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
    
    private $data;
    private $rows = array();
    private $show_trash;
    
    /**
    * construct
    * 
    * @param    bool    show trash?
    */
    function __construct()
    {
        global $status, $page, $pta_sus;
        
        // Set data and convert to array
        $this->data = $pta_sus->data;
                
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
    * @param    array   one row of data
    * @param    array   name of column to be processed
    * @return   string  text that will go in the column's TD
    */
    function column_default($item, $column_name) {
        switch($column_name){
            case 'id':
                return $item[$column_name];
            case 'first_date':
            case 'last_date':
                return ($item[$column_name] == '0000-00-00') ? __("N/A", 'pta-volunteer-sign-up-sheets') : mysql2date( get_option('date_format'), $item[$column_name], $translate = true );
            case 'num_dates':
                $dates = $this->data->get_all_task_dates($item['id']);
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
                } else {
                    return __("N/A", 'pta-volunteer-sign-up-sheets');
                }
            case 'task_num':
                return count($this->data->get_tasks($item['id']));
            case 'spot_num':
                return (int)$this->data->get_sheet_total_spots($item['id'], '');
            case 'filled_spot_num':
                return (int)$this->data->get_sheet_signup_count($item['id']).' '.(($this->data->get_sheet_total_spots($item['id'], '') == $this->data->get_sheet_signup_count($item['id'])) ? '&#10004;' : '');
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
        $show_actions = array();
        foreach ($actions as $action_slug => $action_name) {
            if ('edit_sheet' == $action_slug || 'edit_tasks' == $action_slug) {
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
        if (true == $item['visible']) {
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
        return apply_filters( 'pta_sus_list_table_sortable_columns', array(
            'id'    => array('id',false),
            'visible'    => array('visible',false),
            'title' => array('title',false),
            'first_date'  => array('first_date',true),
            'last_date'  => array('last_date',false),
        ) );
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
        // Process bulk actions
        if('bulk_trash' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $trashed = $this->data->update_sheet(array('sheet_trash'=>true), $id);
                if ($trashed) {
                    $count++;
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error moving sheet# %d to trash.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("%d sheets have been moved to the trash.", 'pta-volunteer-sign-up-sheets'), $count).'</p></div>';
        } elseif ('bulk_delete' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $deleted = $this->data->delete_sheet($id);
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
                $restored = $this->data->update_sheet(array('sheet_trash'=>false), $id);
                if ($restored) {
                    $count++;
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error restoring sheet# %d.", 'pta-volunteer-sign-up-sheets'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("%d sheets have been restored.", 'pta-volunteer-sign-up-sheets'), $count).'</p></div>';
        } elseif('bulk_toggle_visibility' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $toggled = $this->data->toggle_visibility($id);
                if ($toggled) {
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
    * 
    */
    function prepare_items() {
        $this->process_bulk_action();
        $rows = (array)$this->data->get_sheets($this->show_trash, $active_only = false, $show_hidden = true);
        foreach ($rows AS $k=>$v) {
        	// if search is set, skip any that title doesn't match search string
	        if(isset($_REQUEST['s']) && '' !== $_REQUEST['s']) {
	        	if(false === stripos($v->title, $_REQUEST['s'])) {
	        		continue;
		        }
	        }
	        if(isset($_REQUEST['pta-filter-submit']) && isset($_REQUEST['pta-visible-filter']) && in_array($_REQUEST['pta-visible-filter'], array('visible','hidden'))) {
	            $compare = 'visible' === $_REQUEST['pta-visible-filter'] ? "1" : "0";
	            if($compare != $v->visible) {
	                continue;
                }
            }
	        if(isset($_REQUEST['pta-filter-submit']) && isset($_REQUEST['pta-type-filter']) && in_array($_REQUEST['pta-type-filter'], array('Single','Multi-Day','Recurring','Ongoing'))) {
		        if($_REQUEST['pta-type-filter'] !== $v->type) {
			        continue;
		        }
	        }
            $this->rows[$k] = (array)$v;
        }
	    $this->rows = apply_filters( 'pta_sus_list_table_prepare_items_filtered_rows', $this->rows);
        $per_page     = $this->get_items_per_page( 'sheets_per_page', 20 );

        $this->_column_headers = $this->get_column_info();

        // Sort Data
        function usort_reorder($a,$b)
        {
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'title'; // If no sort, default to title
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; // If no order, default to asc
            $result = strcmp($a[$orderby], $b[$orderby]); // Determine sort order
            // Allow extensions to do custom sorting
            $result = apply_filters( 'pta_sus_list_table_usort', $result, $a, $b, $orderby );
            return ($order === 'asc') ? $result : -$result; // Send final sort direction to usort
        }
        usort($this->rows, 'usort_reorder');
        
        $current_page = $this->get_pagenum();
        $total_items = count($this->rows);
        $this->rows = array_slice($this->rows,(($current_page-1)*$per_page),$per_page);
        $this->items = $this->rows;
        
        // Register pagination calculations
        $this->set_pagination_args( array(
            'total_items'   => $total_items,
            'per_page'      => $per_page,
            'total_pages'   => ceil($total_items/$per_page)
        ) );
    }

	function extra_tablenav( $which ) {
		if ( $which == "top" ){
		    $visible = isset($_REQUEST['pta-visible-filter']) ? $_REQUEST['pta-visible-filter'] : '';
			$type = isset($_REQUEST['pta-type-filter']) ? $_REQUEST['pta-type-filter'] : '';
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
			submit_button( __('Filter Sheets','pta-volunteer-sign-up-sheets'), '', 'pta-filter-submit', false, array( 'id' => 'filter-submit' ) );
		}
	}
    
}
/* EOF */