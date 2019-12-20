<?php
/**
 * @var array $row_data
 * @var int $open_spots
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$row_class = ($open_spots === 0) ? 'filled' : '';
?>
<div class="pta-sus-tasks-row <?php echo $row_class; ?>">
    <?php foreach($row_data as $class => $value): ?>
        <div class="<?php echo esc_attr($class); ?>">
            <?php echo wp_kses_post($value); ?>
        </div>
    <?php endforeach; ?>
</div>
