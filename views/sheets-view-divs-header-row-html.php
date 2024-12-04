<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * @var array $columns
 */
?>
<div class="pta-sus-sheets-row pta-sus-header-row">
    <?php foreach($columns as $class => $label): ?>
        <div class="<?php echo esc_attr($class); ?> head">
            <?php echo esc_html($label); ?>
        </div>
    <?php endforeach; ?>
</div>