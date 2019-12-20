<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="pta-sus-tasks-row">
    <?php foreach($columns as $class => $label): ?>
        <div class="<?php echo esc_attr($class); ?> head">
            <?php echo esc_html($label); ?>
        </div>
    <?php endforeach; ?>
</div>