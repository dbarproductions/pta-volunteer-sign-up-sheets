<?php
/**
 * @var array $row_data
 * @var int $open_spots
 * @var array $columns
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$row_class = ($open_spots === 0) ? 'filled' : '';
?>
<div class="pta-sus-table-row pta-sus-sheets-row <?php echo $row_class; ?>">
    <?php foreach($columns as $class => $label):
        $value = $row_data[ $class ] ?? '';
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php echo wp_kses_post($value); ?>
        </div>
    <?php endforeach; ?>
</div>
