<?php
/**
 * @var array $row_data
 * @var int $open_spots
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$row_class = ($open_spots === 0) ? 'filled' : '';
?>
<tr class="pta-sus-tasks-row <?php echo $row_class; ?>">
    <?php foreach($columns as $class => $label):
        $value = isset($row_data[$class]) ? $row_data[$class] : '';
        ?>
        <td class="<?php echo esc_attr($class); ?>">
            <?php echo wp_kses_post($value); ?>
        </td>
    <?php endforeach; ?>
</tr>