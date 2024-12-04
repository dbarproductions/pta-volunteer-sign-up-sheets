<?php
/**
 * @var array $row_data
 * @var int $open_spots
 * @var array $columns
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$row_class = ($open_spots === 0) ? 'filled' : '';
?>
<tr class="pta-sus-table-row pta-sus-sheets-row <?php echo $row_class; ?>">
    <?php foreach($columns as $class => $label):
	    $value = !empty($row_data[ $class ]) ? $row_data[$class] : '&nbsp;';
        ?>
        <td class="pta-sus <?php echo esc_attr($class); ?>" data-label="<?php echo esc_attr($label); ?>">
            <?php echo wp_kses_post($value); ?>
        </td>
    <?php endforeach; ?>
</tr>