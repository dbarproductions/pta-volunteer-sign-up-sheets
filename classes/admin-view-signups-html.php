<?php
	/**
	 * Created by PhpStorm.
	 * User: Stephen
	 * Date: 7/30/2017
	 * Time: 4:01 PM
	 */
	
$tasks = $this->data->get_tasks($sheet_id);
$all_task_dates = $this->data->get_all_task_dates((int)$sheet->id);
// Allow extensions to add columns
$columns = apply_filters('pta_sus_admin_view_signups_columns', array(
	'task'      => __('Task/Item', 'pta_volunteer_sus'),
	'start'     => __('Start Time', 'pta_volunteer_sus'),
	'end'       => __('End Time', 'pta_volunteer_sus'),
	'name'      => __('Name', 'pta_volunteer_sus'),
	'email'     => __('E-mail', 'pta_volunteer_sus'),
	'phone'     => __('Phone', 'pta_volunteer_sus'),
	'details'   => __('Item Details', 'pta_volunteer_sus'),
	'qty'       => __('Item Qty', 'pta_volunteer_sus'),
	'actions'   => ''
), $sheet);
$col_span = count($columns);
$export_url = add_query_arg(array('pta-action' => 'export', 'sheet_id' => $sheet_id), $this->page_url);
$export_transposed_url = add_query_arg(array('pta-action' => 'export_transposed', 'sheet_id' => $sheet_id), $this->page_url);
$nonced_export_url = wp_nonce_url($export_url, 'pta-export');
$nonced_export_transposed_url = wp_nonce_url($export_transposed_url, 'pta-export');
if (!$tasks) {
	echo '<p class="error">'.__('No sign-up sheet found.', 'pta_volunteer_sus').'</p>';
	return;
}
?>
<table class="wp-list-table widefat" cellspacing="0">
	<thead>
		<tr>
		<?php foreach ($columns as $slug => $label): ?>
			<th class="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></th>
		<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($all_task_dates as $tdate):
		// check if we want to show expired tasks and Skip any task whose date has already passed
		if ( !$this->main_options['show_expired_tasks']) {
			if ($tdate < date("Y-m-d") && "0000-00-00" != $tdate) continue;
		}
		if ("0000-00-00" == $tdate) {
			$show_date = false;
		} else {
			$show_date = mysql2date( get_option('date_format'), $tdate, $translate = true );
			echo '<tr><th colspan="'.($col_span - 1).'"><strong>'.$show_date.'</strong></th></tr>';
		}
		foreach ($tasks as $task):
			$task_dates = explode(',', $task->dates);
			if(!in_array($tdate, $task_dates)) continue;
			$i=1;
			$signups = $this->data->get_signups($task->id, $tdate);
		?>
			
			<?php foreach ($signups AS $signup): ?>
			<tr>
				<?php foreach ($columns as $slug => $label): ?>
				<td class="<?php echo esc_attr($slug); ?>"><?php $this->output_signup_column_data($slug, $i, $sheet, $task, $signup); ?></td>
				<?php endforeach; ?>
				<?php
					if ('YES' === $task->enable_quantities) {
						$i += $signup->item_qty;
					} else {
						$i++;
					}
				?>
			</tr>
			<?php endforeach; ?>
			
			<?php if($i < $task->qty):
			$remaining = $task->qty - $i + 1;
			// Maybe add title
            $task_title = apply_filters('pta_sus_admin_signup_display_task_title', ($i === 1) ? esc_html($task->title) : '', $task);
            $start = apply_filters( 'pta_sus_admin_signup_display_start', ("" == $task->time_start) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_start)), $task );
			$end = apply_filters( 'pta_sus_admin_signup_display_end', ("" == $task->time_end) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_end)), $task );
			$remaining_text = sprintf(__('%d remaining', 'pta_volunteer_sus'), (int)$remaining);
			?>
			<tr>
				<td><strong><?php echo esc_html($task_title); ?></strong></td>
                <td><?php echo wp_kses_post($start); ?></td>
                <td><?php echo wp_kses_post($end); ?></td>
				<td class="remaining" colspan="<?php echo esc_attr($col_span - 5); ?>"><strong><?php echo esc_html($remaining_text); ?></strong></td>
			</tr>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endforeach; ?>
	</tbody>
</table>
<br/>
<a href="<?php echo esc_url($nonced_export_url); ?>" class="button-primary"><?php _e('Export Sheet as CSV', 'pta_volunteer_sus'); ?></a>
<a href="<?php echo esc_url($nonced_export_transposed_url); ?>" class="button-primary"><?php _e('Export Sheet as transposed simplified CSV', 'pta_volunteer_sus'); ?></a>