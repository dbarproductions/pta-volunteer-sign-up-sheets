<?php
/**
 * @var array $column_data
 *
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<?php foreach ($column_data as $row): ?>
<tr class="pta-sus-tasks-row">
    <?php foreach($row as $class => $value): ?>
        <td class="<?php echo esc_attr($class); ?>">
            <?php echo wp_kses_post($value); ?>
        </td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>