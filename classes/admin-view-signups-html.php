<?php
	/**
	 * Created by PhpStorm.
	 * User: Stephen
	 * Date: 7/30/2017
	 * Time: 4:01 PM
     *
     * @var object $sheet
	 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
//$tasks = $this->data->get_tasks($sheet_id); // redundant - already got tasks
$all_task_dates = $this->data->get_all_task_dates((int)$sheet->id);
// Allow extensions to add columns
$columns = apply_filters('pta_sus_admin_view_signups_columns', array(
	'date'      => __('Date', 'pta_volunteer_sus'),
	'task'      => __('Task/Item', 'pta_volunteer_sus'),
	'start'     => __('Start Time', 'pta_volunteer_sus'),
	'end'       => __('End Time', 'pta_volunteer_sus'),
	'slot'      => '#',
	'name'      => __('Name', 'pta_volunteer_sus'),
	'email'     => __('E-mail', 'pta_volunteer_sus'),
	'phone'     => __('Phone', 'pta_volunteer_sus'),
	'details'   => __('Item Details', 'pta_volunteer_sus'),
	'qty'       => __('Item Qty', 'pta_volunteer_sus'),
	'actions'   => __('Actions', 'pta_volunteer_sus')
), $sheet);
$num_cols = count($columns);

?>
<table id="pta-sheet-signups" class="pta-signups-table widefat">
	<thead>
		<tr>
		<?php foreach ($columns as $slug => $label):
			$class = $slug;
			if('actions' !== $slug) {
				$class .= ' select-filter';
			}
			?>
			<th class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></th>
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
			$show_date = '';
		} else {
			$show_date = mysql2date( get_option('date_format'), $tdate, $translate = true );
		}
		foreach ($tasks as $task):
			$task_dates = explode(',', $task->dates);
			if(!in_array($tdate, $task_dates)) continue;
			$i=0;
			$signups = $this->data->get_signups($task->id, $tdate);
		?>
			
			<?php foreach ($signups AS $signup): ?>
			<tr>
				<?php foreach ($columns as $slug => $label): ?>
				<td class="<?php echo esc_attr($slug); ?>"><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, $signup, $show_date); ?></td>
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
			$remaining = $task->qty - $i;
            $task_title = apply_filters('pta_sus_admin_signup_display_task_title', esc_html($task->title), $task);
            $start = apply_filters( 'pta_sus_admin_signup_display_start', ("" == $task->time_start) ? '' : pta_datetime(get_option("time_format"), strtotime($task->time_start)), $task );
			$end = apply_filters( 'pta_sus_admin_signup_display_end', ("" == $task->time_end) ? '' : pta_datetime(get_option("time_format"), strtotime($task->time_end)), $task );
			$remaining_text = sprintf(__('%d remaining', 'pta_volunteer_sus'), (int)$remaining);
			$add_url = '?page='.$this->admin_settings_slug.'_sheets&amp;sheet_id='.$sheet->id.'&amp;task_id='.$task->id.'&amp;date='.$tdate.'&amp;action=edit_signup';
			$nonced_add_url = wp_nonce_url( $add_url, 'edit_signup', '_sus_nonce' );
			for ($x=$i+1; $x<=$task->qty; $x++):
			?>
            <tr class="remaining">
                <?php foreach ($columns as $slug => $label):
                    if('slot' === $slug) {
                        ?>
                        <td class="remaining" ><strong><?php echo esc_html($remaining_text); ?></strong></td>
                        <?php
                    } elseif ('actions' === $slug) {
                        ?>
                        <td class="add-signup"><a href="<?php echo esc_url($nonced_add_url); ?>" title="<?php echo esc_attr(__('Add Signup','pta_volunteer_sus')); ?>"><span class="dashicons dashicons-plus"></span></a></td>
                        <?php
                    } else {
                        ?>
                        <td class="<?php echo esc_attr($slug); ?>"><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, false, $show_date); ?></td>
                        <?php
                    }
                endforeach; ?>
            </tr>
			<?php endfor; ?>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endforeach; ?>
	</tbody>
	<tfoot>
	<tr>
		<?php foreach ($columns as $slug => $label):
			$class = $slug;
			if('actions' !== $slug) {
				$class .= ' select-filter';
			}
			?>
			<th class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></th>
		<?php endforeach; ?>
	</tr>
	</tfoot>
</table>