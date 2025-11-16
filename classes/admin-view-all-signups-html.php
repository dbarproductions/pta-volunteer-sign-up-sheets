<?php
	/**
	 * Created by PhpStorm.
	 * User: Stephen
	 * Date: 7/30/2017
	 * Time: 4:01 PM
	 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$sheets = $this->data->get_sheets($show_trash = false, $active_only = false, $show_hidden = true);
$sheets = apply_filters('pta_sus_admin_view_all_data_sheets', $sheets);
if(empty($sheets)) {
	echo '<div class="error"><p>'.__('No data to show.', 'pta-volunteer-sign-up-sheets').'</p></div>';
}
// Allow extensions to add columns
$columns = apply_filters( 'pta_sus_admin_view_all_data_columns', array(
	'date'        => __( 'Date', 'pta-volunteer-sign-up-sheets' ),
	'sheet'       => __( 'Sheet', 'pta-volunteer-sign-up-sheets' ),
	'task'        => __( 'Task/Item', 'pta-volunteer-sign-up-sheets' ),
	'description' => __( 'Task Description', 'pta-volunteer-sign-up-sheets' ),
	'start'       => __( 'Start Time', 'pta-volunteer-sign-up-sheets' ),
	'end'         => __( 'End Time', 'pta-volunteer-sign-up-sheets' ),
	'slot'        => '#',
	'name'        => __( 'Name', 'pta-volunteer-sign-up-sheets' ),
	'email'       => __( 'E-mail', 'pta-volunteer-sign-up-sheets' ),
	'phone'       => __( 'Phone', 'pta-volunteer-sign-up-sheets' ),
	'details'     => __( 'Item Details', 'pta-volunteer-sign-up-sheets' ),
	'qty'         => __( 'Item Qty', 'pta-volunteer-sign-up-sheets' ),
	'validated'   => __( 'Validated', 'pta-volunteer-sign-up-sheets' ),
	'ts'          => __( 'Signup Time', 'pta-volunteer-sign-up-sheets' ),
	'actions'     => __( 'Actions', 'pta-volunteer-sign-up-sheets' )
) );
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
                if ($tdate < date("Y-m-d") && "0000-00-00" !== $tdate) continue;
            }
            if ("0000-00-00" === $tdate) {
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
                            <td class="<?php echo esc_attr($slug); ?>"><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, $signup, $tdate); ?></td>
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
                    $sheet_title = apply_filters('pta_sus_admin_signup_display_sheet_title', esc_html($sheet->title), $sheet);
                    $task_title = apply_filters('pta_sus_admin_signup_display_task_title', esc_html($task->title), $task);
                    $start = apply_filters( 'pta_sus_admin_signup_display_start', ("" == $task->time_start) ? '' : pta_datetime(get_option("time_format"), strtotime($task->time_start)), $task );
                    $end = apply_filters( 'pta_sus_admin_signup_display_end', ("" == $task->time_end) ? '' : pta_datetime(get_option("time_format"), strtotime($task->time_end)), $task );
                    $remaining_text = sprintf(__('%d remaining', 'pta-volunteer-sign-up-sheets'), (int)$remaining);
                    $show_all_slots = isset($this->main_options['show_all_slots_for_all_data']) && true == $this->main_options['show_all_slots_for_all_data'];
                    if($show_all_slots) {
                        for ($x=$i+1; $x<=$task->qty; $x++) { ?>
                            <tr class="remaining">
                            <?php foreach ($columns as $slug => $label):
                                if('slot' === $slug) {
                                    ?>
                                    <td class="remaining" ><strong><?php echo '#'.$x; ?></strong></td>
                                    <?php
                                } else {
                                    ?>
                                    <td class="<?php echo esc_attr($slug); ?>"><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, false, $tdate); ?></td>
                                    <?php
                                }
                            endforeach; ?>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr class="remaining">
                            <?php foreach ($columns as $slug => $label):
                                if('slot' === $slug) {
                                    ?>
                                    <td class="remaining" ><strong><?php echo esc_html($remaining_text); ?></strong></td>
                                    <?php
                                } else {
                                    ?>
                                    <td class="<?php echo esc_attr($slug); ?>"><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, false, $tdate); ?></td>
                                    <?php
                                }
                            endforeach; ?>
                        </tr>
                        <?php
                    }
                endif; ?>
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