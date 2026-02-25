<?php
	/**
	 * Created by PhpStorm.
	 * User: Stephen
	 * Date: 7/30/2017
	 * Time: 4:01 PM
	 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$dt_mode     = $this->main_options['admin_dt_server_side'] ?? 'off';
$server_side = ( 'on' === $dt_mode || 'auto' === $dt_mode );

// Filter sheets by author for Signup Sheet Authors (respect author permissions).
$can_manage_others = current_user_can( 'manage_others_signup_sheets' );
$author_id         = $can_manage_others ? null : get_current_user_id();

// Report Builder: read GET filter params submitted by the filter panel form.
$rf_submitted    = isset( $_GET['rf_submitted'] );
$rf_sheet_ids    = ( $rf_submitted && isset( $_GET['rf_sheet_ids'] ) ) ? array_map( 'absint', (array) $_GET['rf_sheet_ids'] ) : array();
$rf_start_date   = ( $rf_submitted && isset( $_GET['rf_start_date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['rf_start_date'] ) ) : '';
$rf_end_date     = ( $rf_submitted && isset( $_GET['rf_end_date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['rf_end_date'] ) ) : '';
$rf_show_expired = $rf_submitted ? ! empty( $_GET['rf_show_expired'] ) : ! empty( $this->main_options['show_expired_tasks'] );
$rf_show_empty   = !$rf_submitted || !empty($_GET['rf_show_empty']);

// Fetch all sheets once — used for the filter panel select options AND the data loop.
$sheet_fetch_args = array( 'trash' => false, 'active_only' => false, 'show_hidden' => true, 'author_id' => $author_id );
$all_sheets       = PTA_SUS_Sheet_Functions::get_sheets_by_args( $sheet_fetch_args );
$all_sheets       = apply_filters( 'pta_sus_admin_view_all_data_sheets', $all_sheets );

if ( empty( $all_sheets ) ) {
	echo '<div class="error"><p>' . __( 'No data to show.', 'pta-volunteer-sign-up-sheets' ) . '</p></div>';
}

// Client-side mode: apply sheet-ID filter from report builder if specific sheets were chosen.
// Server-side mode: sheet filtering is handled in the AJAX endpoint.
if ( ! $server_side && ! empty( $rf_sheet_ids ) ) {
	$sheets = array_values( array_filter( $all_sheets, function( $s ) use ( $rf_sheet_ids ) {
		return in_array( (int) $s->id, $rf_sheet_ids, true );
	} ) );
} else {
	$sheets = $all_sheets;
}

// Allow extensions to add columns
$_vad_columns = array(
	'date'        => __( 'Date', 'pta-volunteer-sign-up-sheets' ),
	'sheet'       => __( 'Sheet', 'pta-volunteer-sign-up-sheets' ),
);
if ( $can_manage_others ) {
	$_vad_columns['author'] = __( 'Author', 'pta-volunteer-sign-up-sheets' );
}
$_vad_columns = array_merge( $_vad_columns, array(
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
$columns  = apply_filters( 'pta_sus_admin_view_all_data_columns', $_vad_columns );
$num_cols = count($columns);

// Build reset URL (view_all with nonce, no rf_ params).
$rf_page      = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
$rf_reset_url = wp_nonce_url( '?page=' . rawurlencode( $rf_page ) . '&action=view_all', 'view_all', '_sus_nonce' );
// Open filter panel if filters were applied (client-side) or always in server-side mode.
$rf_panel_open = $rf_submitted || $server_side;

?>
<style>
#pta-report-filters-wrap { margin-bottom: 12px; }
#pta-rf-toggle { display: inline-flex; align-items: center; gap: 4px; }
#pta-rf-toggle .dashicons { font-size: 16px; width: 16px; height: 16px; }
.pta-rf-body { background: #fff; border: 1px solid #c3c4c7; padding: 12px 16px; margin-top: 6px; }
.pta-rf-body .form-table th { width: 130px; padding: 10px 0; }
.pta-rf-body .form-table td { padding: 8px 0; }
.pta-rf-body .submit { margin: 0; padding: 10px 0 2px; }
<?php if ( $server_side ) : ?>
#pta-dt-loading-overlay {
	position: fixed;
	top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0,0,0,0.5);
	z-index: 99998;
	display: flex;
	align-items: center;
	justify-content: center;
}
#pta-dt-loading-box {
	background: #fff;
	padding: 36px 52px;
	border-radius: 4px;
	box-shadow: 0 8px 32px rgba(0,0,0,0.3);
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 16px;
	text-align: center;
	min-width: 280px;
}
#pta-dt-loading-box .spinner {
	float: none;
	margin: 0;
	width: 40px;
	height: 40px;
	background-size: 40px 40px;
}
#pta-dt-loading-box .pta-dt-loading-text {
	font-size: 16px;
	font-weight: 600;
	color: #1d2327;
}
#pta-dt-loading-box .pta-dt-loading-sub {
	font-size: 12px;
	color: #646970;
}
<?php endif; ?>
</style>

<div id="pta-report-filters-wrap">
	<button type="button" id="pta-rf-toggle" class="button button-secondary">
		<span class="dashicons <?php echo $rf_panel_open ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'; ?>"></span>
		<?php _e( 'Report Filters', 'pta-volunteer-sign-up-sheets' ); ?>
	</button>
	<div id="pta-rf-body" class="pta-rf-body" <?php if ( ! $rf_panel_open ) echo 'style="display:none;"'; ?>>
		<form id="pta-rf-form" method="get" action="">
			<input type="hidden" name="page" value="<?php echo esc_attr( $rf_page ); ?>">
			<input type="hidden" name="action" value="view_all">
			<input type="hidden" name="_sus_nonce" value="<?php echo esc_attr( wp_create_nonce( 'view_all' ) ); ?>">
			<input type="hidden" name="rf_submitted" value="1">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="pta-rf-sheet-ids"><?php _e( 'Sheets', 'pta-volunteer-sign-up-sheets' ); ?></label></th>
					<td>
						<select id="pta-rf-sheet-ids" name="rf_sheet_ids[]" multiple="multiple" style="min-width:350px;max-width:600px;">
							<?php foreach ( $all_sheets as $s ) : ?>
								<option value="<?php echo esc_attr( $s->id ); ?>"<?php if ( in_array( (int) $s->id, $rf_sheet_ids, true ) ) echo ' selected'; ?>>
									<?php echo esc_html( $s->title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php _e( 'Leave blank to include all sheets.', 'pta-volunteer-sign-up-sheets' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Date Range', 'pta-volunteer-sign-up-sheets' ); ?></th>
					<td>
						<label for="pta-rf-start-date"><?php _e( 'From:', 'pta-volunteer-sign-up-sheets' ); ?></label>
						<input type="date" id="pta-rf-start-date" name="rf_start_date" value="<?php echo esc_attr( $rf_start_date ); ?>">
						<label for="pta-rf-end-date" style="margin-left:10px;"><?php _e( 'To:', 'pta-volunteer-sign-up-sheets' ); ?></label>
						<input type="date" id="pta-rf-end-date" name="rf_end_date" value="<?php echo esc_attr( $rf_end_date ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Options', 'pta-volunteer-sign-up-sheets' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="pta-rf-show-expired" name="rf_show_expired" value="1"<?php checked( $rf_show_expired ); ?>>
							<?php _e( 'Include expired dates', 'pta-volunteer-sign-up-sheets' ); ?>
						</label>
						&nbsp;&nbsp;
						<label>
							<input type="checkbox" id="pta-rf-show-empty" name="rf_show_empty" value="1"<?php checked( $rf_show_empty ); ?>>
							<?php _e( 'Show empty slots', 'pta-volunteer-sign-up-sheets' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary" id="pta-rf-apply"><?php _e( 'Apply Filters', 'pta-volunteer-sign-up-sheets' ); ?></button>
				<button type="button" class="button" id="pta-rf-reset" data-reset-url="<?php echo esc_attr( $rf_reset_url ); ?>"><?php _e( 'Reset', 'pta-volunteer-sign-up-sheets' ); ?></button>
			</p>
		</form>
	</div>
</div>

<?php if ( $server_side ) : ?>
<div id="pta-dt-loading-overlay">
	<div id="pta-dt-loading-box">
		<span class="spinner is-active"></span>
		<span class="pta-dt-loading-text"><?php _e( 'Loading signup data&hellip;', 'pta-volunteer-sign-up-sheets' ); ?></span>
		<span class="pta-dt-loading-sub"><?php _e( 'This may take a moment for large datasets.', 'pta-volunteer-sign-up-sheets' ); ?></span>
	</div>
</div>
<?php endif; ?>

<table id="pta-all-data" class="pta-signups-table widefat" data-column-slugs="<?php echo esc_attr( implode( ',', array_keys( $columns ) ) ); ?>">
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
    <?php if ( ! $server_side ) : ?>
    <?php foreach ($sheets as $sheet):
        $all_task_dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet((int)$sheet->id);
        $tasks=PTA_SUS_Task_Functions::get_tasks($sheet->id);
        if(empty($all_task_dates)) continue;
        ?>
	    <?php foreach ($all_task_dates as $tdate):
            // Skip expired dates unless the report filter allows them.
            if ( ! $rf_show_expired ) {
                if ($tdate < date("Y-m-d") && "0000-00-00" !== $tdate) continue;
            }
            // Apply report builder date range.
            if ( '0000-00-00' !== $tdate ) {
                if ( '' !== $rf_start_date && $tdate < $rf_start_date ) continue;
                if ( '' !== $rf_end_date   && $tdate > $rf_end_date )   continue;
            }
            if ("0000-00-00" === $tdate) {
                $show_date = '';
            } else {
                $show_date = mysql2date( get_option('date_format'), $tdate, $translate = true );
            }
            foreach ($tasks as $task):
                $task_dates = explode(',', $task->dates);
                if(!in_array($tdate, $task_dates, true)) continue;
                $i=0;
                $signups = PTA_SUS_Signup_Functions::get_signups_for_task($task->id, $tdate);
                ?>

                <?php foreach ($signups AS $signup): ?>
                    <tr>
                        <?php foreach ($columns as $slug => $label): ?>
                            <td class="<?php echo esc_attr($slug); ?>"<?php
								if ('date' === $slug && '0000-00-00' !== $tdate) echo ' data-order="' . esc_attr(strtotime($tdate)) . '"';
								if ('ts' === $slug && !empty($signup->ts)) echo ' data-order="' . esc_attr($signup->ts) . '"';
							?>><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, $signup, $tdate); ?></td>
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

                <?php if( $rf_show_empty && $i < $task->qty ):
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
                                    <td class="<?php echo esc_attr($slug); ?>"<?php if ('date' === $slug && '0000-00-00' !== $tdate) echo ' data-order="' . esc_attr(strtotime($tdate)) . '"'; ?>><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, false, $tdate); ?></td>
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
                                    <td class="<?php echo esc_attr($slug); ?>"<?php if ('date' === $slug && '0000-00-00' !== $tdate) echo ' data-order="' . esc_attr(strtotime($tdate)) . '"'; ?>><?php $this->output_signup_column_data($slug, $i+1, $sheet, $task, false, $tdate); ?></td>
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
    <?php endif; // ! $server_side ?>

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
