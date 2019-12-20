<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<tr class="pta-sus-sheets-row">
    <?php foreach($columns as $class => $label): ?>
        <th class="<?php echo esc_attr($class); ?> head">
            <?php echo esc_html($label); ?>
        </th>
    <?php endforeach; ?>
</tr>