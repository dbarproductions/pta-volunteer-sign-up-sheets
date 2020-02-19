<?php
	/**
	 * Created by PhpStorm.
	 * User: Stephen
	 * Date: 7/30/2017
	 * Time: 4:01 PM
	 */

$sheets = $this->data->get_sheets($show_trash = false, $active_only = false, $show_hidden = true);
if(empty($sheets)) {
	echo '<div class="error"><p>'.__('No data to show.', 'pta_volunteer_sus').'</p></div>';
}
// Allow extensions to add columns
$columns = apply_filters('pta_sus_admin_view_all_data_columns', array(
    'slot'      => '#',
    'sheet'     => __('Sheet', 'pta_volunteer_sus'),
	'task'      => __('Task/Item', 'pta_volunteer_sus'),
	'date'      => __('Date', 'pta_volunteer_sus'),
	'start'     => __('Start Time', 'pta_volunteer_sus'),
	'end'       => __('End Time', 'pta_volunteer_sus'),
	'name'      => __('Name', 'pta_volunteer_sus'),
	'email'     => __('E-mail', 'pta_volunteer_sus'),
	'phone'     => __('Phone', 'pta_volunteer_sus'),
	'details'   => __('Item Details', 'pta_volunteer_sus'),
	'qty'       => __('Item Qty', 'pta_volunteer_sus'),
	'actions'   => __('Actions', 'pta_volunteer_sus')
));
$num_cols = count($columns);

?>
<table id="pta-all-data" class="pta-signups-table widefat">
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
    <?php foreach ($sheets as $sheet):
        $all_task_dates = $this->data->get_all_task_dates((int)$sheet->id);
        $tasks=$this->data->get_tasks($sheet->id);
        if(empty($all_task_dates)) continue;
        ?>
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
                    ?>
                    <tr class="remaining">
                        <td><strong><?php echo esc_html($task_title); ?></strong></td>
                        <td><strong><?php echo esc_html($show_date); ?></strong></td>
                        <td><?php echo wp_kses_post($start); ?></td>
                        <td><?php echo wp_kses_post($end); ?></td>
                        <td class="remaining" ><strong><?php echo esc_html($remaining_text); ?></strong></td>
                        <?php for ($j = 1; $j <= ($num_cols - 5); $j++): ?>
                            <td></td>
                        <?php endfor; ?>
                    </tr>
                <?php endif; ?>
	        <?php endforeach; ?>
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