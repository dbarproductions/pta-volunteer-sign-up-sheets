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
    * @return   PTA_SUS_List_Table
    */
    function __construct()
    {
        global $status, $page;
        
        // Set data and convert to array
        $this->data = new PTA_SUS_Data();
                
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
                return ($item[$column_name] == '0000-00-00') ? __("N/A", 'pta_volunteer_sus') : mysql2date( get_option('date_format'), $item[$column_name], $translate = true );
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
                    return __("N/A", 'pta_volunteer_sus');
                }
                return count($dates);
            case 'task_num':
                return count($this->data->get_tasks($item['id']));
            case 'spot_num':
                return (int)$this->data->get_sheet_total_spots($item['id'], '');
            case 'filled_spot_num':
                return (int)$this->data->get_sheet_signup_count($item['id']).' '.(($this->data->get_sheet_total_spots($item['id'], '') == $this->data->get_sheet_signup_count($item['id'])) ? '&#10004;' : '');
            default:
                // Allow extensions to add column content
                $return = apply_filters( 'pta_sus_process_other_columns', '', $item, $column_name );
                return $return;
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
            $actions = array('untrash' => __('Restore', 'pta_volunteer_sus' ), 'delete' => __('Delete', 'pta_volunteer_sus'));
        } else {
            $actions = array('view_signup' => __('View Sign-ups', 'pta_volunteer_sus'),
                             'edit_sheet' => __('Edit Sheet', 'pta_volunteer_sus'),
                             'edit_tasks' => __('Edit Tasks', 'pta_volunteer_sus'), 
                             'copy' => __('Copy', 'pta_volunteer_sus'),
                             'trash' => __('Trash', 'pta_volunteer_sus'));
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
        $page = $_GET['page'];
        if (true == $item['visible']) {
            $display = __("Yes", 'pta_volunteer_sus');
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
            'Single' => __('Single', 'pta_volunteer_sus'), 
            'Recurring' => __('Recurring', 'pta_volunteer_sus'), 
            'Multi-Day' => __('Multi-Day', 'pta_volunteer_sus'), 
            'Ongoing' => __('Ongoing', 'pta_volunteer_sus')
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
    * @todo     finish
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
            'id'                => __('ID#', 'pta_volunteer_sus'),
            'title'             => __('Title', 'pta_volunteer_sus'),
            'visible'           => __('Visible', 'pta_volunteer_sus'),
            'type'              => __('Event Type', 'pta_volunteer_sus'),
            'first_date'        => __('First Date', 'pta_volunteer_sus'),
            'last_date'         => __('Last Date', 'pta_volunteer_sus'),
            'num_dates'         => __('# Dates', 'pta_volunteer_sus'),
            'task_num'          => __('# Tasks', 'pta_volunteer_sus'),
            'spot_num'          => __('Total Spots', 'pta_volunteer_sus'),
            'filled_spot_num'   => __('Filled Spots', 'pta_volunteer_sus'),
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
        $sortable_columns = apply_filters( 'pta_sus_list_table_sortable_columns', array(
            'id'    => array('id',false),
            'visible'    => array('visible',false),
            'title' => array('title',false),
            'first_date'  => array('first_date',true),
            'last_date'  => array('last_date',false),
        ) );
        return $sortable_columns;
    }
    
    /**
    * All allowed bulk actions
    * 
    */
    function get_bulk_actions()
    {
        $actions = array();

        if ($this->show_trash) {
            $actions = array(
                'bulk_delete' => __('Delete', 'pta_volunteer_sus'),
                'bulk_restore' => __('Restore', 'pta_volunteer_sus')
            );
        } else {
            $actions = array(
                'bulk_trash' => __('Move to Trash', 'pta_volunteer_sus'),
                'bulk_toggle_visibility' => __('Toggle Visibility', 'pta_volunteer_sus')
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

            $nonce  = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );
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
                    echo '<div class="error"><p>'.sprintf(__("Error moving sheet# %d to trash.", 'pta_volunteer_sus'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("%d sheets have been moved to the trash.", 'pta_volunteer_sus'), $count).'</p></div>';
        } elseif ('bulk_delete' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $deleted = $this->data->delete_sheet($id);
                if ($deleted) {
                    $count++;
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error deleting sheet# %d.", 'pta_volunteer_sus'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("%d sheets have been deleted.", 'pta_volunteer_sus'), $count).'</p></div>';
        } elseif('bulk_restore' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $restored = $this->data->update_sheet(array('sheet_trash'=>false), $id);
                if ($restored) {
                    $count++;
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error restoring sheet# %d.", 'pta_volunteer_sus'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("%d sheets have been restored.", 'pta_volunteer_sus'), $count).'</p></div>';
        } elseif('bulk_toggle_visibility' === $this->current_action()) {
            $count = 0;
            foreach ($_REQUEST['sheets'] as $key => $id) {
                $toggled = $this->data->toggle_visibility($id);
                if ($toggled) {
                    $count++;
                } else {
                    echo '<div class="error"><p>'.sprintf(__("Error toggling visibility for sheet# %d.", 'pta_volunteer_sus'), $id).'</p></div>';
                }
            }
            echo '<div class="updated"><p>'.sprintf(__("Visibility toggled for %d sheets.", 'pta_volunteer_sus'), $count).'</p></div>';
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
            $this->rows[$k] = (array)$v;
        }
        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
        

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
    
}
/* EOF */