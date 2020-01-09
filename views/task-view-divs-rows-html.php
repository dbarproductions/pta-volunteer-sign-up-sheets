<?php
/**
 * @var array $column_data
 * @var bool $show_names
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<?php foreach ($column_data as $row):
$row_class = 'pta-sus-tasks-row';
if(!$show_names && isset($row['column-available-spots'])) {
    $display_signup = apply_filters( 'pta_sus_public_output', __('Filled', 'pta_volunteer_sus'), 'task_spot_filled_message' );
    if (strpos($row['column-available-spots'], $display_signup) !== false) {
       $row_class .= ' filled';
    }
}
    ?>
<div class="<?php echo esc_attr($row_class); ?>">
    <?php foreach($row as $class => $value): ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php echo wp_kses_post($value); ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>