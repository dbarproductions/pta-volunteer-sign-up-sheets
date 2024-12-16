<?php
/**
 * @var array $column_data
 * @var array $columns
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
    <tr class="pta-sus-table-row <?php echo esc_attr($row_class); ?>">
        <?php foreach($columns as $class => $label):
            $value = !empty($row[ $class ]) ? $row[$class] : '&nbsp;';
            ?>
            <td class="pta-sus <?php echo esc_attr($class); ?>" data-label="<?php echo esc_attr($label); ?>">
                <?php echo wp_kses_post($value); ?>
            </td>
        <?php endforeach; ?>
    </tr>
<?php endforeach; ?>