<?php
/**
 * @var array $column_data
 * @var bool $show_names
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<?php foreach ($column_data as $row):
$row_class = 'pta-sus-tasks-row';
if(!empty($row['extra-class'])) {
    $row_class .= ' '.$row['extra-class'];
}
?>
<div class="<?php echo esc_attr($row_class); ?>">
    <?php foreach($columns as $class => $label):
        $value = isset($row[$class]) ? $row[$class] : '';
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php echo wp_kses_post($value); ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>