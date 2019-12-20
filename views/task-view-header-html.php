<?php
/**
 * @var object $task
 * @var string $date
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="pta-sus task-info-header">
    <div class="pta-sus title-header"><?php echo esc_html($task->title); ?></div>
    <?php if(!empty($date)): ?>
    <div class="pta-sus date-header"><?php echo mysql2date( get_option('date_format'), $date, $translate = true ); ?></div>
    <?php endif; ?>
    <?php if($this->show_time && '' !== $task->time_start): ?>
        <div class="pta-sus time-header start"><?php echo esc_html($this->start_time_header).': '. date_i18n(get_option("time_format"), strtotime($task->time_start)); ?></div>
    <?php endif; ?>
	<?php if($this->show_time && '' !== $task->time_end): ?>
        <div class="pta-sus time-header end"><?php echo esc_html($this->end_time_header).': '. date_i18n(get_option("time_format"), strtotime($task->time_end)); ?></div>
	<?php endif; ?>
    <?php if(!empty($task->description)): ?>
    <div class="pta-sus task-description"><?php echo wp_kses_post(wpautop($task->description)); ?></div>
    <?php endif; ?>
</div>
