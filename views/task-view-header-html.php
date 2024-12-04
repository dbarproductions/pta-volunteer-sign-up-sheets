<?php
/**
 * @var object $task
 * @var string $date
 * @var bool $show_date
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="pta-sus task-info-header">
    <?php if($show_date && !empty($date) && '0000-00-00' != $date): ?>
    <div class="pta-sus date-header"><?php echo mysql2date( get_option('date_format'), $date, $translate = true ); ?></div>
    <?php endif; ?>
    <div class="pta-sus title-header"><?php echo esc_html($task->title); ?></div>
	<?php do_action('pta_sus_task_header_info_after_task_title', $task); ?>
    <?php if($this->show_time && '' !== $task->time_start): ?>
        <div class="pta-sus time-header start"><?php echo esc_html($this->start_time_header).': '. pta_datetime(get_option("time_format"), strtotime($task->time_start)); ?></div>
    <?php endif; ?>
	<?php if($this->show_time && '' !== $task->time_end): ?>
        <div class="pta-sus time-header end"><?php echo esc_html($this->end_time_header).': '. pta_datetime(get_option("time_format"), strtotime($task->time_end)); ?></div>
	<?php endif; ?>
	<?php do_action('pta_sus_task_header_info_before_task_description', $task); ?>
    <?php if(!empty($task->description)): ?>
    <div class="pta-sus task-description"><?php echo wp_kses_post(wpautop($task->description)); ?></div>
    <?php endif; ?>
</div>
