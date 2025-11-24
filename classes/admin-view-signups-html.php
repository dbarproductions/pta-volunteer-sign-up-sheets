<?php
	/**
	 * Created by PhpStorm.
	 * User: Stephen
	 * Date: 7/30/2017
	 * Time: 4:01 PM
     *
     * @var object $sheet
     * @var array $tasks
     * @var PTA_SUS_Admin $this
	 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$all_task_dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet((int)$sheet->id);
// Allow extensions to add columns
$columns = apply_filters( 'pta_sus_admin_view_signups_columns', array(
	'date'        => __( 'Date', 'pta-volunteer-sign-up-sheets' ),
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
    'ts'          => __( 'Signup Time', 'pta-volunteer-sign-up-sheets' ),
	'validated'   => __( 'Validated', 'pta-volunteer-sign-up-sheets' ),
	'actions'     => __( 'Actions', 'pta-volunteer-sign-up-sheets' )
), $sheet );
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
            $task_title = apply_filters('pta_sus_admin_signup_display_task_title', esc_html($task->title), $task);
            $start = apply_filters( 'pta_sus_admin_signup_display_start', ("" == $task->time_start) ? '' : pta_datetime(get_option("time_format"), strtotime($task->time_start)), $task );
			$end = apply_filters( 'pta_sus_admin_signup_display_end', ("" == $task->time_end) ? '' : pta_datetime(get_option("time_format"), strtotime($task->time_end)), $task );
			$add_url = '?page='.$this->admin_settings_slug.'_sheets&amp;sheet_id='.$sheet->id.'&amp;task_id='.$task->id.'&amp;date='.$tdate.'&amp;action=edit_signup';
			$nonced_add_url = wp_nonce_url( $add_url, 'edit_signup', '_sus_nonce' );
			for ($x=$i+1; $x<=$task->qty; $x++):
			?>
            <tr class="remaining">
                <?php foreach ($columns as $slug => $label):
                    if('slot' === $slug) {
                        ?>
                        <td class="remaining" ><strong><?php echo '#'.$x; ?></strong></td>
                        <?php
                    } elseif ('actions' === $slug) {
                        ?>
                        <td class="add-signup"><a href="<?php echo esc_url($nonced_add_url); ?>" title="<?php echo esc_attr(__('Add Signup','pta-volunteer-sign-up-sheets')); ?>"><span class="dashicons dashicons-plus"></span></a></td>
                        <?php
                    } else {
                        ?>
                        <td class="<?php echo esc_attr($slug); ?>"><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, false, $tdate); ?></td>
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