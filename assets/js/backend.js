(function($) {

    $(".tasks").sortable({
        distance: 5,
        opacity: 0.6,
        cursor: 'move',
        axis: 'y'
    });

	$('#multi999Picker').datepick({
        pickerClass: 'pta',
        multiSelect: 999, monthsToShow: 2, dateFormat: 'yyyy-mm-dd',
        showTrigger: '#calImg'
	});

    $('.singlePicker').datepick({
        pickerClass: 'pta',
        monthsToShow: 1, dateFormat: 'yyyy-mm-dd',
        showTrigger: '#calImg'
    });

    $('.pta-timepicker').timepicker({
    showPeriod: true,
    showLeadingZero: true,
    defaultTime: ''
	});

    let $loading = $('#loadingDiv').hide();
    $(document)
        .ajaxStart(function () {
            $loading.show();
        })
        .ajaxStop(function () {
            $loading.hide();
        });

    /**
     * Populate form fields with user data (handles all field types including Custom Fields)
     * @param {Object} data - Object with field keys and values
     */
    function populateUserFields(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        $.each(data, function(key, value) {
            // Skip user_id - handled separately
            if (key === 'user_id') {
                return;
            }

            // Skip standard fields that are handled separately
            if (['firstname', 'lastname', 'email', 'phone'].includes(key)) {
                return;
            }

            // Try to find field - handle different element types and multi-select with []
            let $field = $('input[name="' + key + '"], select[name="' + key + '"], textarea[name="' + key + '"], select[name="' + key + '[]"]');
            
            if ($field.length) {
                const fieldType = $field[0].tagName.toLowerCase();
                const isMultiSelect = $field[0].multiple || $field[0].name.indexOf('[]') !== -1;
                
                if (fieldType === 'select' && isMultiSelect) {
                    // Handle multi-select (Select2)
                    // Value should be an array or comma-separated string
                    let values = [];
                    if (Array.isArray(value)) {
                        values = value.map(v => String(v).trim()).filter(v => v);
                    } else if (value) {
                        values = String(value).split(',').map(v => v.trim()).filter(v => v);
                    }
                    
                    // Set values on native select
                    $field.find('option').prop('selected', false);
                    values.forEach(function(val) {
                        $field.find('option[value="' + val + '"]').prop('selected', true);
                    });
                    
                    // Update Select2 if initialized
                    if ($field.data('select2')) {
                        $field.trigger('change');
                    } else {
                        // Select2 not initialized yet, try again after a short delay
                        setTimeout(function() {
                            if ($field.data('select2')) {
                                $field.trigger('change');
                            }
                        }, 100);
                    }
                } else if (fieldType === 'select') {
                    // Handle single select
                    $field.val(value);
                    // Update Select2 if initialized
                    if ($field.data('select2')) {
                        $field.trigger('change');
                    }
                } else if (fieldType === 'textarea') {
                    // Handle textarea
                    $field.val(value);
                } else if (fieldType === 'input' && $field.attr('type') === 'checkbox') {
                    // Handle checkbox
                    $field.prop('checked', value == 1 || value === true || value === '1');
                } else if (fieldType === 'input' && $field.attr('type') === 'radio') {
                    // Handle radio buttons
                    $('input[type="radio"][name="' + $field.attr('name') + '"][value="' + value + '"]').prop('checked', true);
                } else if (fieldType === 'input' && $field.attr('data-quill-field') === 'true') {
                    // Handle Quill editor fields (Custom Fields HTML fields)
                    // For HTML fields, use raw value (don't decode - it's already HTML)
                    // The value from the server should already be properly formatted HTML
                    const htmlContent = value || '';
                    
                    // First set the hidden input value (raw HTML, no decoding)
                    $field.val(htmlContent);
                    
                    const containerId = $field.attr('id') + '-quill-container';
                    const $container = $('#' + containerId);
                    
                    if ($container.length && typeof Quill !== 'undefined') {
                        // Try to get existing Quill instance
                        let quill = $container.data('quill-instance');
                        
                        if (quill && quill.root) {
                            // Quill already initialized, set content (raw HTML, no decoding)
                            if (htmlContent) {
                                const delta = quill.clipboard.convert({ html: htmlContent });
                                quill.setContents(delta, 'silent');
                            } else {
                                quill.setText('');
                            }
                        } else {
                            // Quill not initialized yet - initialize it now
                            // This matches the initialization in Custom Fields admin JS
                            var editorId = $field.attr('id');
                            var initialContent = htmlContent;
                            
                            quill = new Quill('#' + containerId, {
                                theme: 'snow',
                                modules: {
                                    toolbar: [
                                        [{ 'header': [1, 2, 3, false] }],
                                        ['bold', 'italic', 'underline', 'strike'],
                                        ['blockquote', 'code-block'],
                                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                        [{ 'script': 'sub'}, { 'script': 'super' }],
                                        [{ 'indent': '-1'}, { 'indent': '+1' }],
                                        [{ 'direction': 'rtl' }],
                                        [{ 'color': [] }, { 'background': [] }],
                                        [{ 'font': [] }],
                                        [{ 'align': [] }],
                                        ['clean'],
                                        ['link']
                                    ]
                                }
                            });
                            
                            // Set initial content if provided
                            if (initialContent) {
                                const delta = quill.clipboard.convert({ html: initialContent });
                                quill.setContents(delta, 'silent');
                            }
                            
                            // Store Quill instance on container
                            $container.data('quill-instance', quill);
                            
                            // Sync Quill content to hidden input on text change
                            quill.on('text-change', function() {
                                var html = quill.root.innerHTML;
                                // Only update if content actually changed (avoid infinite loops)
                                if ($field.val() !== html) {
                                    $field.val(html);
                                }
                            });
                        }
                    }
                } else {
                    // Handle regular input (text, email, etc.)
                    $field.val(value);
                }
            }
        });

        // Handle standard fields (firstname, lastname, email, phone)
        $.each(data, function(key, value) {
            if (!['firstname', 'lastname', 'email', 'phone'].includes(key)) {
                return;
            }

            let $input = $('input[name="' + key + '"]');
            if ($input.length) {
                $input.val(value);
            }
        });
    }

    $('#user_id').on('change', function(){
        let userID = $(this).val();
        if (!userID || userID === '0') {
            return; // No user selected
        }
        
        let data = {
            'action': 'pta_sus_get_user_data',
            'security': PTASUS.ptaNonce,
            'user_id': userID
        };

        $.post(ajaxurl, data, function(response) {
            if(response) {
                populateUserFields(response);
            }
        });
    });

    // Admin live search - uses shared ptaVolunteer code
    // Wait for both jQuery and ptaVolunteer to be available
    (function() {
        function initAdminLiveSearch() {
            const firstnameField = document.querySelector('#firstname');

            // Check if we're on a signup form page and field exists
            if (!firstnameField) {
                return; // Not on signup form page
            }

            // Check if ptaVolunteer is available
            if (typeof ptaVolunteer === 'undefined') {
                console.warn('ptaVolunteer not available for admin live search');
                return;
            }

            // Get ajaxurl - try multiple sources
            let ajaxUrl = '';
            if (typeof ptaSUS !== 'undefined' && ptaSUS.ajaxurl) {
                ajaxUrl = ptaSUS.ajaxurl;
            } else if (typeof ajaxurl !== 'undefined') {
                ajaxUrl = ajaxurl; // WordPress admin global
            } else {
                ajaxUrl = admin_url('admin-ajax.php'); // Fallback
            }

            // Get nonce - try multiple sources
            let nonce = '';
            if (typeof ptaSUS !== 'undefined' && ptaSUS.ptaNonce) {
                nonce = ptaSUS.ptaNonce;
            } else if (typeof ptaSUS !== 'undefined' && ptaSUS.ptanonce) {
                nonce = ptaSUS.ptanonce; // lowercase version
            } else if (typeof PTASUS !== 'undefined' && PTASUS.ptaNonce) {
                nonce = PTASUS.ptaNonce; // Backend script version
            } else {
                console.warn('Nonce not found for admin live search');
                return;
            }

            // Initialize live search for admin
            ptaVolunteer.init({
                ajaxUrl: ajaxUrl,
                extraData: {
                    action: 'pta_sus_live_search',
                    security: nonce,
                },
                fieldPrefix: '', // No prefix for admin fields
                updateUserDropdown: true // Also update user_id dropdown when selecting
            });
        }

        // Try to initialize when ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAdminLiveSearch);
        } else {
            // DOM already loaded, wait a bit for scripts
            setTimeout(function() {
                if (typeof ptaVolunteer !== 'undefined') {
                    initAdminLiveSearch();
                } else {
                    // Try again after a short delay
                    setTimeout(initAdminLiveSearch, 200);
                }
            }, 100);
        }
    })();

    // Open details_text for checked values on page load
    $('input.details_checkbox', 'li').each(function() {
        if ($(this).is(':checked')) {
            $(this).closest('li').find('.pta_toggle').show();
        }
    });

    function toggle_description(id) {
        $('#task_description_'+id).toggle();
        $('.pta_sus_task_description').not('#task_description_'+id).hide();
    }

    $('.task_description_trigger').on('click', function(e){
        e.preventDefault();
        let id = $(this).attr('id').split('_').pop();
        toggle_description(id);
    });

    function toggle_email_templates(id) {
        $('#task_email_templates_'+id).toggle();
        $('.pta_sus_task_email_templates').not('#task_email_templates_'+id).hide();
    }

    $('.task_email_templates_trigger').on('click', function(e){
        e.preventDefault();
        let id = $(this).attr('id').split('_').pop();
        toggle_email_templates(id);
    });

    $('.details_checkbox').change(function() {
        $(this).closest('li').find('.pta_toggle').toggle(this.checked);
    });

    if ($('.tasks LI').length) {
        const getNextRowKey = () => {
            let highestId = 0;
            $(".tasks LI").each(function() {
                const id = $(this).attr('id');
                if (id) {
                    const numericId = parseInt(id.split('-')[1]);
                    if (!isNaN(numericId) && numericId > highestId) {
                        highestId = numericId;
                    }
                }
            });
            return highestId + 1;
        };

        const resetInputValue = ($element, rowKey) => {
            const newId = $element.attr('id')?.replace(/\d+/, rowKey);
            const newName = $element.attr('name')?.replace(/\d+/, rowKey);

            $element.attr({
                'id': newId,
                'name': newName
            });

            // Handle different input types
            if ($element.is('input[type="text"]') || $element.is('input[type="number"]')) {
                $element.val('');
            }

            if ($element.hasClass('pta-timepicker')) {
                $element.val('').removeClass('hasTimepicker');
            }

            if ($element.is('textarea')) {
                $element.val('');
                $element.closest('.pta_sus_task_description').attr('id', `task_description_${rowKey}`);
            }

            if ($element.is('select')) {
                // Reset select to first option (0 = Use Sheet Template/System Default)
                $element.prop('selectedIndex', 0);
                // Update email templates div ID if this is an email template select
                if ($element.attr('name') && $element.attr('name').includes('email_template_id')) {
                    $element.closest('.pta_sus_task_email_templates').attr('id', `task_email_templates_${rowKey}`);
                }
            }

            if ($element.hasClass('details_text')) {
                $element.val(PTASUS.default_text).closest('span').hide();
            }

            if ($element.is(':checkbox')) {
                $element.prop('checked', false).val('YES');
            }

            if ($element.hasClass('details_required')) {
                $element.prop('checked', true).closest('span').hide();
            }

            if ($element.is('input[type="hidden"][name^="task_id"]')) {
                $element.val('');
            }

        };

        const initializePlugins = ($row) => {
            $row.find(".pta-timepicker")
                .removeClass('hasTimepicker')
                .timepicker({
                    showPeriod: true,
                    showLeadingZero: true,
                    defaultTime: ''
                });

            $row.find(".singlePicker")
                .removeClass('is-datepick')
                .datepick({
                    monthsToShow: 1,
                    dateFormat: 'yyyy-mm-dd',
                    showTrigger: '#calImg'
                });
        };

        const bindEventHandlers = ($row, rowKey) => {
            $row.find('.details_checkbox').on('change', function() {
                $(this).closest('li').find('.pta_toggle').toggle(this.checked);
            });

            $row.find('a.task_description_trigger').each(function() {
                const newId = $(this).attr('id').replace(/\d+/, rowKey);
                $(this)
                    .attr('id', newId)
                    .on('click', function(e) {
                        e.preventDefault();
                        const id = $(this).attr('id').split('_').pop();
                        toggle_description(id);
                    });
            });
            
            $row.find('a.task_email_templates_trigger').each(function() {
                const newId = $(this).attr('id').replace(/\d+/, rowKey);
                $(this)
                    .attr('id', newId)
                    .on('click', function(e) {
                        e.preventDefault();
                        const id = $(this).attr('id').split('_').pop();
                        toggle_email_templates(id);
                    });
            });
        };

        $(document).on('click', '.add-task-after', function(e) {
            e.preventDefault();
            $('.pta_sus_task_description').hide();
            $('.pta_sus_task_email_templates').hide();

            const rowKey = getNextRowKey();
            const $newRow = $(".tasks LI").last().clone();

            $newRow.attr('id', `task-${rowKey}`);

            // Reset all inputs in the new row
            $newRow.find('input, select, textarea').each(function() {
                resetInputValue($(this), rowKey);
            });

            $(this).parent("LI").after($newRow);

            initializePlugins($newRow);
            bindEventHandlers($newRow, rowKey);
        });

        $(document).on('click', '.remove-task', function(e) {
            e.preventDefault();
            const $tasks = $('.tasks LI');

            if ($tasks.length === 1) {
                $(this).prev().trigger('click');
            }
            $(this).parent("LI").remove();
        });
    }


    var sheetTitleSpan = $('span#sheet_title');
    if(sheetTitleSpan.length) {
        var sheetTitle = sheetTitleSpan.text();
    }
    var sheetInfoSpan = $('span#sheet-info');
    var sheetInfoText ='';
    if(sheetInfoSpan.length) {
        var sheetInfo = sheetInfoSpan.html();
        var regex = /<br\s*[\/]?>/gi;
        sheetInfoText = sheetInfo.replace(regex, "\n").replace('&nbsp;', " ").replace('&nbsp;', " ");
    }

    let ptaTableParams = {
        order: [],
        dom: '<B>lfrtip',
        colReorder: true,
        responsive: false,
        stateSave: false,
        pageLength: 100,
        lengthMenu: [[ 10, 25, 50, 100, 150, -1 ], [ 10, 25, 50, 100, 150, 'All']],
        buttons: [
            {
                extend: 'excel',
                text: PTASUS.excelExport,
                title: sheetTitle,
                message: sheetInfoText,
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function ( data, column, row ) {
                            // remove hidden timestamp for date sorting
                            if(data.match(/<span class="pta-sortdate">/)) {
                                data = data.substring(45);
                            }
                            var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace('&nbsp;', " ").replace('&nbsp;', " ");
                            var content = $('<div>' + a + '</div>');
                            content.find('a').replaceWith(function() { return this.childNodes; });
                            return content.text();
                        }
                    }
                }
            },
            {
                extend: 'csv',
                text: PTASUS.csvExport,
                title: sheetTitle,
                message: sheetInfoText,
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function ( data, column, row ) {
                            // remove hidden timestamp for date sorting
                            if(data.match(/<span class="pta-sortdate">/)) {
                                data = data.substring(45);
                            }
                            var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace('&nbsp;', " ").replace('&nbsp;', " ");
                            var content = $('<div>' + a + '</div>');
                            content.find('a').replaceWith(function() { return this.childNodes; });
                            return content.text();
                        }
                    }
                }
            },
            {
                extend: 'pdf',
                text: PTASUS.pdfSave,
                title: sheetTitle,
                message: sheetInfoText,
                orientation: 'landscape',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function ( data, column, row ) {
                            // remove hidden timestamp for date sorting
                            if(data.match(/<span class="pta-sortdate">/)) {
                                data = data.substring(45);
                            }
                            var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace('&nbsp;', " ").replace('&nbsp;', " ");
                            var content = $('<div>' + a + '</div>');
                            content.find('a').replaceWith(function() { return this.childNodes; });
                            return content.text();
                        }
                    }
                }
            },
            {
                extend: 'print',
                text: PTASUS.toPrint,
                title: sheetTitle,
                message: sheetInfo,
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function ( data, column, row ) {
                            // remove hidden timestamp for date sorting
                            if(data.match(/<span class="pta-sortdate">/)) {
                                data = data.substring(45);
                            }
                            var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace('&nbsp;', " ").replace('&nbsp;', " ");
                            var content = $('<div>' + a + '</div>');
                            content.find('a').replaceWith(function() { return this.childNodes; });
                            return content.text();
                        }
                    }
                },
                customize: function (win) {
                    $(win.document.body).find('table').addClass('display').css('font-size', '11px');
                }
            },
            { extend: 'colvis', text: PTASUS.colvisText },
            {
                text: PTASUS.hideRemaining,
                action: function ( e, dt, node, config ) {
                    ptaTable.rows('.remaining').remove().draw( false );
                    this.disable();
                }
            },
            'createState', 'savedStates'
        ]
    };

    let allTableParams = {
        order: [],
        dom: '<B>lfrtip',
        colReorder: true,
        responsive: false,
        stateSave: false,
        pageLength: 100,
        lengthMenu: [[ 10, 25, 50, 100, 150, -1 ], [ 10, 25, 50, 100, 150, 'All']],
        buttons: [
            {
                extend: 'excel',
                text: PTASUS.excelExport,
                title: sheetTitle,
                message: sheetInfoText,
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function ( data, column, row ) {
                            // remove hidden timestamp for date sorting
                            if(data.match(/<span class="pta-sortdate">/)) {
                                data = data.substring(45);
                            }
                            var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace('&nbsp;', " ").replace('&nbsp;', " ");
                            var content = $('<div>' + a + '</div>');
                            content.find('a').replaceWith(function() { return this.childNodes; });
                            return content.text();
                        }
                    }
                }
            },
            {
                extend: 'csv',
                text: PTASUS.csvExport,
                title: sheetTitle,
                message: sheetInfoText,
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function ( data, column, row ) {
                            // remove hidden timestamp for date sorting
                            if(data.match(/<span class="pta-sortdate">/)) {
                                data = data.substring(45);
                            }
                            var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace('&nbsp;', " ").replace('&nbsp;', " ");
                            var content = $('<div>' + a + '</div>');
                            content.find('a').replaceWith(function() { return this.childNodes; });
                            return content.text();
                        }
                    }
                }
            },
            {
                extend: 'pdf',
                text: PTASUS.pdfSave,
                title: sheetTitle,
                message: sheetInfoText,
                orientation: 'landscape',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function ( data, column, row ) {
                            // remove hidden timestamp for date sorting
                            if(data.match(/<span class="pta-sortdate">/)) {
                                data = data.substring(45);
                            }
                            var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace('&nbsp;', " ").replace('&nbsp;', " ");
                            var content = $('<div>' + a + '</div>');
                            content.find('a').replaceWith(function() { return this.childNodes; });
                            return content.text();
                        }
                    }
                }
            },
            {
                extend: 'print',
                text: PTASUS.toPrint,
                title: sheetTitle,
                message: sheetInfo,
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function ( data, column, row ) {
                            // remove hidden timestamp for date sorting
                            if(data.match(/<span class="pta-sortdate">/)) {
                                data = data.substring(45);
                            }
                            var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace('&nbsp;', " ").replace('&nbsp;', " ");
                            var content = $('<div>' + a + '</div>');
                            content.find('a').replaceWith(function() { return this.childNodes; });
                            return content.text();
                        }
                    }
                },
                customize: function (win) {
                    $(win.document.body).find('table').addClass('display').css('font-size', '11px');
                }
            },
            { extend: 'colvis', text: PTASUS.colvisText },
            {
                text: PTASUS.hideRemaining,
                action: function ( e, dt, node, config ) {
                    allTable.rows('.remaining').remove().draw( false );
                    this.disable();
                }
            },
            'createState', 'savedStates'
        ]
    };

    if(!PTASUS.disableAdminGrouping) {
        ptaTableParams.rowGroup = {
            dataSrc: [ 0, 1 ],
            startRender: function ( rows, group, level ) {
                return group;
            }
        };
        ptaTableParams.columnDefs = [{targets: [ 0, 1 ], visible: false}]
        ptaTableParams.buttons.push({
            text: PTASUS.disableGrouping,
            action: function ( e, dt, node, config ) {
                ptaTable.rowGroup().disable().draw();
                this.disable();
            }
        });
        allTableParams.rowGroup = {
            dataSrc: [ 0, 1, 2 ],
            startRender: function ( rows, group, level ) {
                return group;
            }
        };
        allTableParams.columnDefs = [{targets: [ 0, 1, 2 ], visible: false}]
        allTableParams.buttons.push({
            text: PTASUS.disableGrouping,
            action: function ( e, dt, node, config ) {
                allTable.rowGroup().disable().draw();
                this.disable();
            }
        });
    }

    function createSelectFilters(table) {
        table.draw(); // draw to update search cache values for columns
        // remove any existing select inputs first so that we don't get multiple select when move a column
        $('.pta-select-filter').remove();

        table.columns( '.select-filter' ).every( function () {
            var that = this;

            // Create the select list and search operation
            var select = $('<select class="pta-select-filter" />')
                .appendTo(
                    this.footer()
                )
                .on( 'change', function () {
                    var searchVal = $.fn.dataTable.util.escapeRegex(
                        $(this).val()
                    );
                    that.search( searchVal ? '^'+searchVal+'$' : '', true, false ).draw();
                } );


            // Get the search data for the first column and add to the select list
            select.append( $('<option value="">'+ PTASUS.showAll +'</option>') );
            this
                .cache('search')
                .sort()
                .unique()
                .each( function ( d ) {
                    if('' !== d) {
                        let showVal = d;
                        // remove hidden timestamp for date sorting
                        if(d.match(/\|/)) {
                            showVal = d.substring(11);
                        }
                        select.append( $('<option value="'+d+'">'+showVal+'</option>') );
                    }
                } );
        } );
    }

    var ptaTable = $('#pta-sheet-signups').DataTable( ptaTableParams );
    ptaTable.on( 'column-reorder', function ( e, settings, details ) {
        createSelectFilters(ptaTable);
    } );
    createSelectFilters(ptaTable);

    var allTable = $('#pta-all-data').DataTable( allTableParams );
    allTable.on( 'column-reorder', function ( e, settings, details ) {
        createSelectFilters(allTable);
    } );
    createSelectFilters(allTable);



    let methodSelect = $('#pta-reschedule-sheet-form [name="method"]');
    methodSelect.on('change', function (e){
        let value = $(this).val();
        if('multi-copy' === value) {
            $('.pta-hide-if-multi').hide();
            $('.pta-show-if-multi').show();
            $('.singlePicker').prop('required',false);
            $('.pta-multi-input').prop('required',true);
        } else {
            $('.pta-hide-if-multi').show();
            $('.pta-show-if-multi').hide();
            $('.singlePicker').prop('required',true);
            $('.pta-multi-input').prop('required',false);
        }
    }).trigger('change');

    // Move students form
    let sheetID = $('#pta_sheet_id');
    let taskSelectP = $('p.task_select');
    let taskSelect = $('#pta_task_id');
    let moveSubmitP = $('p.move-signup');
    let submitButton = $('#pta-move-signup-submit');
    let confirmP = $('p.admin_confirm');
    let confirm = $('#pta_admin_confirm');
    let signupQty = $('#signup_qty').val();

    // When task select, show submit button if selection not empty
    taskSelect.on('change', function(){

        confirmP.hide().prop('checked', false);
        let ptaMessage = $('#pta-ajax-messages');
        if(ptaMessage.length) {
            ptaMessage.remove();
        }
        let selectedTD =  $(this).val();

        if('' !== selectedTD) {
            confirmP.show().prop('checked', false);
        }
    });

    // Populate tasks when sheet selected
    sheetID.on('change', function(){
        taskSelectP.hide();
        taskSelect.empty();
        moveSubmitP.hide();
        confirmP.hide().prop('checked', false);
        let selectedID = $(this).val();
        let data = {
            'action': 'pta_sus_get_tasks_for_sheet',
            'security': PTASUS.ptaNonce,
            'sheet_id': selectedID,
            'old_task_id': $('#old_task_id').val(),
            'old_signup_id': $('#old_signup_id').val(),
            'qty': signupQty
        };
        $.post(ajaxurl, data, function(response) {
            if(response) {
                if(false === response.success && '' !== selectedID) {
                    $('#pta-ajax-messages').html('<div id="pta-message" class="pta-sus error"><span class="pta-message-close"><a href="">X</a></span><p>' + response.message+'</p></div>');
                    $('.pta-message-close').on('click', function(e){
                        e.preventDefault();
                        $('#pta-message').fadeOut('fast', function(){$('#pta-message').remove()});
                    });
                }
                if(true === response.success) {
                    let tasks = response.tasks;
                    console.log(Object.keys(tasks).length);
                    if($(tasks).length) {
                        if(Object.keys(tasks).length > 1) {
                            taskSelect.append($("<option></option>").attr("value",'').text('Please Select a Task'));
                        } else {
                            // show the submit button if only 1 task to select
                            moveSubmitP.show();
                        }
                        if(Object.keys(tasks).length > 0) {
                            taskSelectP.show();
                            $.each(tasks, function(key, value){
                                taskSelect.append($("<option></option>").attr("value",key).text(value));
                            });
                            if(1 === Object.keys(tasks).length) {
                                taskSelect.trigger('change');
                            }
                        } else {
                            $('#pta-ajax-messages').html('<div id="pta-message" class="pta-sus error"><span class="pta-message-close"><a href="">X</a></span><p>No Available Tasks for that event.</p></div>');
                        }

                    } else {
                        $('#pta-ajax-messages').html('<div id="pta-message" class="pta-sus error"><span class="pta-message-close"><a href="">X</a></span><p>No Available Tasks for that event.</p></div>');
                    }
                }
            }
        });
    }).trigger('change');

    confirm.on('click change', function () {
        if($(this).is(":checked")){
            moveSubmitP.show();
            submitButton.prop('disabled',false);
        } else {
            moveSubmitP.hide();
            submitButton.prop('disabled',true);
        }
    });

    // TEMPLATE TAG HELPER FUNCTIONS
    // Toggle panel
    $('#pta-sus-template-helper-toggle').on('click', function() {
        $('#pta-sus-template-helper-panel').toggleClass('active');
    });

    // Handle tag selection
    $('.tag-item').on('click', function() {
        const tag = $(this).data('tag');

        // Use fallback if Clipboard API not available
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(tag).then(() => {
                showAdminNotice('Template tag copied to clipboard!');
            });
        } else {
            // Fallback using temporary textarea
            const textarea = document.createElement('textarea');
            textarea.value = tag;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showAdminNotice('Template tag copied to clipboard!');
        }
    });

    // Function to show admin notice
    function showAdminNotice(message) {
        const notice = $('<div class="notice notice-success" style="position: fixed; top: 32px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"><p>' + message + '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></p></div>');
        $('body').append(notice);

        // Handle dismiss click
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(() => notice.remove());
        });

        setTimeout(() => {
            notice.fadeOut(() => notice.remove());
        }, 2000);
    }

    // Handle search
    $('.pta-sus-helper-search input').on('input', function() {
        const search = $(this).val().toLowerCase();
        $('.tag-item').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(search));
        });
    });

    // Add escape key handler
    $(document).on('keyup', function(e) {
        if (e.key === "Escape") {
            $('#pta-sus-template-helper-panel').removeClass('active');
        }
    });

    // Add close button
    $('.pta-sus-helper-search').append('<button type="button" class="pta-helper-close" aria-label="Close template helper"><span class="dashicons dashicons-no-alt"></span></button>');

    // Handle close button click
    $('.pta-helper-close').on('click', function() {
        $('#pta-sus-template-helper-panel').removeClass('active');
    });




})(jQuery);