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
            // Strip table structural elements after wp_kses_post: if a cell value contains
            // <td>, <tr>, <table> etc. (e.g. pasted from Word), the browser parses them as
            // real cells in the surrounding table, creating a column count mismatch that
            // DataTables v2 rejects with a TypeError. wp_kses_post allows these tags for
            // post content, so we strip them explicitly here.
            $value = preg_replace( '/<\/?(table|thead|tbody|tfoot|tr|td|th)(\s[^>]*)?\s*>/i', '', wp_kses_post( $value ) );
            ?>
            <td class="pta-sus <?php echo esc_attr($class); ?>" data-label="<?php echo esc_attr($label); ?>">
                <?php echo $value; // already sanitized above ?>
            </td>
        <?php endforeach; ?>
    </tr>
<?php endforeach; ?>