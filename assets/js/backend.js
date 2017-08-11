jQuery(document).ready(function($) {

    $(".tasks").sortable({
        distance: 5,
        opacity: 0.6,
        cursor: 'move',
        axis: 'y'
    });

	$('#multi999Picker').datepick({ 
    multiSelect: 999, monthsToShow: 2, dateFormat: 'yyyy-mm-dd',
    showTrigger: '#calImg'});

    $('.singlePicker').datepick({ 
    monthsToShow: 1, dateFormat: 'yyyy-mm-dd',
    showTrigger: '#calImg'});

    $('.timepicker').timepicker({
    showPeriod: true,
    showLeadingZero: true,
    defaultTime: '',
	});

    // Open details_text for checked values on page load
    $('input.details_checkbox', 'li').each(function() {
        if ($(this).is(':checked')) {
            $(this).closest('li').find('.pta_toggle').show();
        }
    });
    

    $('.details_checkbox').change(function() {
        $(this).closest('li').find('.pta_toggle').toggle(this.checked);
    });

	if ($('.tasks LI').is('*')) {
        var last_css_id = $(".tasks LI").last().attr('id');
        var row_key = last_css_id.substr(last_css_id.indexOf("-") + 1);
        var default_text = PTA_Backend_js.default_text;
        $(".add-task-after").on('click', function() {
            row_key++;
            // Clone the last row
            var new_row = $(".tasks LI").last().clone();
            // Change the id of the li element
            $(new_row).attr('id', "task-" + row_key);
            // Change name and id attributes to new row_key values
            new_row.find('input, select').each(function() {
                var currentNameAttr = $(this).attr('name'); // get the current name attribute
                var currentIdAttr = $(this).attr('id'); // get the current id attribute
                // construct new name & id attributes
                var newNameAttr = currentNameAttr.replace(/\d+/, row_key);
                var newIdAttr = currentIdAttr.replace(/\d+/, row_key);
                $(this).attr('name', newNameAttr);   // set the new name attribute
                $(this).attr('id', newIdAttr);   // set the new id attribute
                $(this).attr('value', ""); // clear the cloned values
                if ($(this).hasClass('details_text')) {
                    $(this).attr('value', default_text);
                    $(this).closest('span').hide();
                }
                if ($(this).is(':checkbox')) {
                    $(this).attr('checked', false);
                    $(this).attr('value', "YES");
                }
                if ($(this).hasClass('details_required')) {
                    $(this).attr('checked', true);
                    $(this).closest('span').hide();
                }
            });
            // Insert the new task row
            $(this).parent("LI").after(new_row);
            // Reset timepicker for the new row
            new_row.find(".timepicker").removeClass('hasTimepicker').timepicker({
            showPeriod: true,
            showLeadingZero: true,
            defaultTime: '',
            });
            // Reset datepick for the new row
            new_row.find(".singlePicker").removeClass('is-datepick').datepick({
            monthsToShow: 1, dateFormat: 'yyyy-mm-dd',
            showTrigger: '#calImg'});
            // Reset toggle for new row
            new_row.find('.details_checkbox').change(function() {
                $(this).closest('li').find('.pta_toggle').toggle(this.checked);
            });

            return false;
        });
        $(".remove-task").live('click', function() {
            if ($('.tasks LI').length == 1) {
                $(this).prev().trigger('click');
            }
            $(this).parent("LI").remove();
            return false;
        });
    }

});