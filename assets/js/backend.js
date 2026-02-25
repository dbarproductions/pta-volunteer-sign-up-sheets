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

    // Shared export body formatter - strips HTML for clean export data
    function ptaExportBodyFormatter( data, column, row ) {
        var a = data.replace( /<br\s*\/?>/ig, "\n" ).replace(/&nbsp;/g, " ");
        var content = $('<div>' + a + '</div>');
        content.find('a').replaceWith(function() { return this.childNodes; });
        return content.text();
    }

    // State variables for server-side mode.
    var ptaHideRemaining     = false;
    var allHideRemaining     = false;
    var ptaLastFilterOptions = {};
    var allLastFilterOptions = {};
    var ptaRowClasses        = [];
    var allRowClasses        = [];

    // Report Builder filter state (all-data view only).
    var rfSheetIds    = [];
    var rfStartDate   = '';
    var rfEndDate     = '';
    var rfShowExpired = PTASUS.rfShowExpired ? 1 : 0;
    var rfShowEmpty   = PTASUS.rfShowEmpty   ? 1 : 0;

    let ptaTableParams = {
        layout: {
            topStart: ['buttons', 'pageLength'],
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        order: [],
        colReorder: true,
        responsive: false,
        stateSave: false,
        pageLength: 100,
        lengthMenu: [[ 10, 25, 50, 100, 150, -1 ], [ 10, 25, 50, 100, 150, 'All']],
        columnDefs: [
            { orderSequence: ['asc', 'desc'], targets: '_all' }
        ],
        buttons: [
            {
                extend: 'excel',
                text: PTASUS.excelExport,
                title: sheetTitle,
                message: sheetInfoText,
                exportOptions: {
                    columns: ':visible',
                    format: { body: ptaExportBodyFormatter }
                }
            },
            {
                extend: 'csv',
                text: PTASUS.csvExport,
                title: sheetTitle,
                message: sheetInfoText,
                exportOptions: {
                    columns: ':visible',
                    format: { body: ptaExportBodyFormatter }
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
                    format: { body: ptaExportBodyFormatter }
                }
            },
            {
                extend: 'print',
                text: PTASUS.toPrint,
                title: sheetTitle,
                message: sheetInfo,
                exportOptions: {
                    columns: ':visible',
                    format: { body: ptaExportBodyFormatter }
                },
                customize: function (win) {
                    $(win.document.body).find('table').addClass('display').css('font-size', '11px');
                }
            },
            { extend: 'colvis', text: PTASUS.colvisText },
            {
                text: PTASUS.hideRemaining,
                action: function ( e, dt, node, config ) {
                    if ( PTASUS.serverSide ) {
                        ptaHideRemaining = true;
                        dt.ajax.reload();
                    } else {
                        ptaTable.rows('.remaining').remove().draw( false );
                    }
                    this.disable();
                }
            },
            'createState', 'savedStates'
        ]
    };

    let allTableParams = {
        layout: {
            topStart: ['buttons', 'pageLength'],
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        order: [],
        colReorder: true,
        responsive: false,
        stateSave: false,
        pageLength: 100,
        lengthMenu: [[ 10, 25, 50, 100, 150, -1 ], [ 10, 25, 50, 100, 150, 'All']],
        columnDefs: [
            { orderSequence: ['asc', 'desc'], targets: '_all' }
        ],
        buttons: [
            {
                extend: 'excel',
                text: PTASUS.excelExport,
                title: sheetTitle,
                message: sheetInfoText,
                exportOptions: {
                    columns: ':visible',
                    format: { body: ptaExportBodyFormatter }
                }
            },
            {
                extend: 'csv',
                text: PTASUS.csvExport,
                title: sheetTitle,
                message: sheetInfoText,
                exportOptions: {
                    columns: ':visible',
                    format: { body: ptaExportBodyFormatter }
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
                    format: { body: ptaExportBodyFormatter }
                }
            },
            {
                extend: 'print',
                text: PTASUS.toPrint,
                title: sheetTitle,
                message: sheetInfo,
                exportOptions: {
                    columns: ':visible',
                    format: { body: ptaExportBodyFormatter }
                },
                customize: function (win) {
                    $(win.document.body).find('table').addClass('display').css('font-size', '11px');
                }
            },
            { extend: 'colvis', text: PTASUS.colvisText },
            {
                text: PTASUS.hideRemaining,
                action: function ( e, dt, node, config ) {
                    if ( PTASUS.serverSide ) {
                        allHideRemaining = true;
                        dt.ajax.reload();
                    } else {
                        allTable.rows('.remaining').remove().draw( false );
                    }
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
        ptaTableParams.columnDefs.push({targets: [ 0, 1 ], visible: false});
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
        allTableParams.columnDefs.push({targets: [ 0, 1, 2 ], visible: false});
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
        // remove any existing select inputs first so that we don't get multiple selects when moving a column
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

            // Get the search data for the column and add to the select list
            select.append( $('<option value="">'+ PTASUS.showAll +'</option>') );
            this
                .cache('search')
                .sort()
                .unique()
                .each( function ( d ) {
                    if('' !== d) {
                        select.append( $('<option value="'+d+'">'+d+'</option>') );
                    }
                } );
        } );
    }

    /**
     * Rebuild column select filters from server-provided distinct values.
     *
     * Called after every xhr.dt response in server-side mode instead of the
     * client-side createSelectFilters() which relies on .cache('search').
     *
     * @param {DataTable} table         DataTable instance.
     * @param {Object}    filterOptions Map of column data-index => string[] from the server.
     */
    function createSelectFiltersServerSide( table, filterOptions ) {
        table.columns( '.select-filter' ).every( function () {
            var that      = this;
            var colIdx    = this.index();
            var $footer   = $( this.footer() );

            // Preserve any active selection before rebuilding.
            var currentVal = $footer.find( 'select.pta-select-filter' ).val() || '';
            $footer.find( 'select.pta-select-filter' ).remove();

            var select = $( '<select class="pta-select-filter" />' )
                .appendTo( $footer )
                .on( 'change', function () {
                    // Plain search — the PHP handler does exact-match; no regex needed.
                    that.search( $( this ).val() ).draw();
                } );

            select.append( '<option value="">' + PTASUS.showAll + '</option>' );

            var values = ( filterOptions[ colIdx ] || [] ).slice().sort();
            values.forEach( function ( v ) {
                if ( '' !== v ) {
                    select.append( $( '<option>' ).val( v ).text( v ) );
                }
            } );

            // Restore previous selection if still present in the new options.
            if ( currentVal ) {
                select.val( currentVal );
            }
        } );
    }

    // -------------------------------------------------------------------------
    // Server-Side Mode: inject serverSide + ajax config when PTASUS.serverSide
    // is truthy (set via wp_localize_script in Phase 5). Defaults to off so all
    // existing client-side behavior is preserved when the flag is absent.
    // -------------------------------------------------------------------------
    if ( PTASUS.serverSide ) {
        // colReorder conflicts with server-side numeric column indexing.
        ptaTableParams.colReorder = false;
        allTableParams.colReorder = false;

        // Read column slugs from the data attribute stamped on each table at page-render
        // time (when all extension filters were active). Sending these to the AJAX handler
        // lets PHP build cells in the correct order even if extension filter hooks don't
        // fire during wp_ajax_ requests (e.g. hooks registered inside admin_menu/admin_init).
        var ptaColSlugs = ( $( '#pta-sheet-signups' ).data( 'column-slugs' ) || '' ).toString().split( ',' ).filter( Boolean );
        var allColSlugs = ( $( '#pta-all-data' ).data( 'column-slugs' ) || '' ).toString().split( ',' ).filter( Boolean );

        ptaTableParams.serverSide = true;
        ptaTableParams.ajax = {
            url: ajaxurl,
            type: 'POST',
            data: function ( d ) {
                d.action         = 'PTA_SUS_ADMIN_DT_DATA';
                d.ptaNonce       = PTASUS.ptaNonce;
                d.view_type      = 'single_sheet';
                d.sheet_id       = PTASUS.sheetId || 0;
                d.hide_remaining = ptaHideRemaining ? 1 : 0;
                d.column_slugs   = ptaColSlugs.join( ',' );
            }
        };
        ptaTableParams.createdRow = function ( row, data, dataIndex ) {
            if ( ptaRowClasses[ dataIndex ] ) {
                $( row ).addClass( ptaRowClasses[ dataIndex ] );
            }
        };

        allTableParams.serverSide = true;
        allTableParams.ajax = {
            url: ajaxurl,
            type: 'POST',
            data: function ( d ) {
                d.action         = 'PTA_SUS_ADMIN_DT_DATA';
                d.ptaNonce       = PTASUS.ptaNonce;
                d.view_type      = 'all_data';
                d.hide_remaining = allHideRemaining ? 1 : 0;
                d.column_slugs   = allColSlugs.join( ',' );
                // Report Builder params.
                if ( rfSheetIds.length ) { d.sheet_ids = rfSheetIds; }
                d.start_date   = rfStartDate;
                d.end_date     = rfEndDate;
                d.show_expired = rfShowExpired;
                d.show_empty   = rfShowEmpty;
            }
        };
        allTableParams.createdRow = function ( row, data, dataIndex ) {
            if ( allRowClasses[ dataIndex ] ) {
                $( row ).addClass( allRowClasses[ dataIndex ] );
            }
        };

        // ── Server-side export helpers ────────────────────────────────────────────────
        //
        // In server-side mode the DataTables export buttons (excel/csv/pdf/print) cannot
        // export the full dataset because they only see the current page. Instead we POST
        // to our own wp-admin AJAX endpoint which fetches the full filtered/sorted dataset
        // and returns a CSV download or a printable HTML page.

        /**
         * Collect the current DT state (search, sort, column filters, report builder) into
         * a flat params object that can be serialised into a hidden form for the export POST.
         *
         * @param  {DataTables.Api} dt             DataTables instance.
         * @param  {string}         viewType        'single_sheet' or 'all_data'.
         * @param  {string[]}       colSlugs        Authoritative column slug array.
         * @param  {Function}       getHideRemaining Returns current hide-remaining boolean.
         * @return {Object}
         */
        function getExportParams( dt, viewType, colSlugs, getHideRemaining ) {
            var params = {
                action:         'PTA_SUS_ADMIN_EXPORT',
                ptaNonce:       PTASUS.ptaNonce,
                view_type:      viewType,
                hide_remaining: getHideRemaining() ? 1 : 0,
                column_slugs:   colSlugs.join( ',' ),
                search:         dt.search(),
                order_col:      ( dt.order()[0] ? dt.order()[0][0] : 0 ),
                order_dir:      ( dt.order()[0] ? dt.order()[0][1] : 'asc' )
            };

            // Per-column select-filter values encoded as JSON: { "colIdx": "value", ... }.
            var colFilters = {};
            dt.columns( '.select-filter' ).every( function ( colIdx ) {
                var sv = this.search();
                if ( sv ) { colFilters[ colIdx ] = sv; }
            } );
            params.col_search = JSON.stringify( colFilters );

            if ( 'single_sheet' === viewType ) {
                params.sheet_id = PTASUS.sheetId || 0;
            } else {
                // Report builder params.
                if ( rfSheetIds.length ) { params.sheet_ids = rfSheetIds; }
                params.start_date   = rfStartDate;
                params.end_date     = rfEndDate;
                params.show_expired = rfShowExpired;
                params.show_empty   = rfShowEmpty;
            }

            return params;
        }

        /**
         * Create a temporary hidden form, populate it with the export params, and submit it.
         * CSV/Excel downloads target the same window; print/PDF open in a new tab so that
         * the auto-print script can run without navigating away.
         *
         * @param {string} format One of 'csv', 'excel', 'pdf', 'print'.
         * @param {Object} params Key/value pairs to POST. Array values become key[]=val entries.
         */
        function doServerExport( format, params ) {
            var isPrint = ( format === 'print' || format === 'pdf' );
            var form = document.createElement( 'form' );
            form.method = 'POST';
            form.action = ajaxurl;
            form.target = isPrint ? '_blank' : '_self';

            params.format = format;

            $.each( params, function ( key, val ) {
                if ( $.isArray( val ) ) {
                    $.each( val, function ( i, v ) {
                        var inp = document.createElement( 'input' );
                        inp.type  = 'hidden';
                        inp.name  = key + '[]';
                        inp.value = v;
                        form.appendChild( inp );
                    } );
                } else {
                    var inp = document.createElement( 'input' );
                    inp.type  = 'hidden';
                    inp.name  = key;
                    inp.value = ( val === undefined || val === null ) ? '' : val;
                    form.appendChild( inp );
                }
            } );

            document.body.appendChild( form );
            form.submit();
            document.body.removeChild( form );
        }

        /**
         * Replace the standard DT export buttons (excel/csv/pdf/print) in tableParams.buttons
         * with custom action functions that POST to the server-side export endpoint.
         * All other button definitions (colvis, hide-remaining, etc.) are left unchanged.
         *
         * @param {Object}   tableParams      DT init params object (mutated in place).
         * @param {string}   viewType         'single_sheet' or 'all_data'.
         * @param {string[]} colSlugs         Authoritative column slug array.
         * @param {Function} getHideRemaining Returns current hide-remaining boolean.
         */
        function replaceExportButtons( tableParams, viewType, colSlugs, getHideRemaining ) {
            var exportFormats = [ 'excel', 'csv', 'pdf', 'print' ];
            tableParams.buttons = tableParams.buttons.map( function ( btn ) {
                // Pass through string shorthand buttons ('createState', 'savedStates', etc.)
                // and non-export objects (colvis, hide-remaining, group-toggle, etc.).
                if ( typeof btn !== 'object' || ! btn.extend ) { return btn; }
                if ( exportFormats.indexOf( btn.extend ) === -1 ) { return btn; }

                var format  = btn.extend;
                var btnText = btn.text;
                return {
                    text: btnText,
                    action: function ( e, dt ) {
                        var params = getExportParams( dt, viewType, colSlugs, getHideRemaining );
                        doServerExport( format, params );
                    }
                };
            } );
        }

        replaceExportButtons( ptaTableParams, 'single_sheet', ptaColSlugs, function () { return ptaHideRemaining; } );
        replaceExportButtons( allTableParams, 'all_data',     allColSlugs, function () { return allHideRemaining; } );

        // ── Hide Remaining button adjustments for server-side mode ────────────────────
        //
        // All-data view: The filter panel's "Show empty slots" checkbox replaces the DT
        // button, so remove the button to avoid two conflicting controls.
        allTableParams.buttons = allTableParams.buttons.filter( function ( btn ) {
            return typeof btn !== 'object' || btn.text !== PTASUS.hideRemaining;
        } );

        // Single-sheet view: No filter panel exists here, so convert the one-way button
        // into a proper toggle (Hide Remaining ↔ Show Remaining).
        ptaTableParams.buttons = ptaTableParams.buttons.map( function ( btn ) {
            if ( typeof btn !== 'object' || btn.text !== PTASUS.hideRemaining ) { return btn; }
            return {
                text: PTASUS.hideRemaining,
                action: function ( e, dt, node ) {
                    ptaHideRemaining = ! ptaHideRemaining;
                    $( node ).text( ptaHideRemaining ? PTASUS.showRemaining : PTASUS.hideRemaining );
                    dt.ajax.reload();
                }
            };
        } );
    }

    var ptaTable = $('#pta-sheet-signups').DataTable( ptaTableParams );
    ptaTable.on( 'column-reorder', function ( e, settings, details ) {
        if ( PTASUS.serverSide ) {
            createSelectFiltersServerSide( ptaTable, ptaLastFilterOptions );
        } else {
            createSelectFilters( ptaTable );
        }
    } );
    if ( PTASUS.serverSide ) {
        // Rebuild column-filter selects from each AJAX response (includes initial load).
        ptaTable.on( 'xhr.dt', function ( e, settings, json ) {
            if ( json ) {
                if ( json.rowClasses )    { ptaRowClasses = json.rowClasses; }
                if ( json.filterOptions ) {
                    ptaLastFilterOptions = json.filterOptions;
                    createSelectFiltersServerSide( ptaTable, json.filterOptions );
                }
            }
        } );
    } else {
        createSelectFilters( ptaTable );
    }

    var allTable = $('#pta-all-data').DataTable( allTableParams );
    allTable.on( 'column-reorder', function ( e, settings, details ) {
        if ( PTASUS.serverSide ) {
            createSelectFiltersServerSide( allTable, allLastFilterOptions );
        } else {
            createSelectFilters( allTable );
        }
    } );
    if ( PTASUS.serverSide ) {
        // Dismiss the full-screen loading overlay after the initial AJAX response.
        var $allLoadingOverlay = $( '#pta-dt-loading-overlay' );
        if ( $allLoadingOverlay.length ) {
            allTable.one( 'xhr.dt', function () {
                $allLoadingOverlay.fadeOut( 300, function () {
                    $allLoadingOverlay.remove();
                } );
            } );
        }

        allTable.on( 'xhr.dt', function ( e, settings, json ) {
            if ( json ) {
                if ( json.rowClasses )    { allRowClasses = json.rowClasses; }
                if ( json.filterOptions ) {
                    allLastFilterOptions = json.filterOptions;
                    createSelectFiltersServerSide( allTable, json.filterOptions );
                }
            }
        } );
    } else {
        createSelectFilters( allTable );
    }

    // -------------------------------------------------------------------------
    // Report Builder filter panel (#pta-report-filters-wrap)
    // -------------------------------------------------------------------------
    if ( $( '#pta-report-filters-wrap' ).length ) {

        // Toggle collapse / expand.
        $( '#pta-rf-toggle' ).on( 'click', function () {
            var $caret = $( this ).find( '.dashicons' );
            $( '#pta-rf-body' ).slideToggle( 150, function () {
                $caret.toggleClass( 'dashicons-arrow-down-alt2 dashicons-arrow-up-alt2' );
            } );
        } );

        // Enhance sheet multi-select with Select2 if available.
        if ( $.fn && $.fn.select2 ) {
            $( '#pta-rf-sheet-ids' ).select2( {
                placeholder: PTASUS.rfAllSheets || 'All Sheets',
                allowClear: true,
                width: 'element'
            } );
        }

        if ( PTASUS.serverSide ) {
            // Show a loading state on the Apply button while the AJAX request is in flight.
            // DataTables fires processing.dt(true) at the start of every request and
            // processing.dt(false) when the response arrives.
            var $rfApplyBtn      = $( '#pta-rf-apply' );
            var rfApplyBtnLabel  = $rfApplyBtn.text();
            allTable.on( 'processing.dt', function ( e, settings, processing ) {
                $rfApplyBtn
                    .prop( 'disabled', processing )
                    .toggleClass( 'pta-rf-loading', processing )
                    .text( processing ? rfApplyBtnLabel + '\u2026' : rfApplyBtnLabel );
            } );

            // Server-side: intercept form submit, update JS state, reload DT.
            $( '#pta-rf-form' ).on( 'submit', function ( e ) {
                e.preventDefault();
                var rawIds = $( '#pta-rf-sheet-ids' ).val() || [];
                rfSheetIds    = rawIds.map( Number ).filter( Boolean );
                rfStartDate   = $( '#pta-rf-start-date' ).val() || '';
                rfEndDate     = $( '#pta-rf-end-date' ).val() || '';
                rfShowExpired = $( '#pta-rf-show-expired' ).is( ':checked' ) ? 1 : 0;
                rfShowEmpty   = $( '#pta-rf-show-empty' ).is( ':checked' ) ? 1 : 0;
                allTable.ajax.reload();
            } );

            // Reset: restore defaults and reload.
            $( '#pta-rf-reset' ).on( 'click', function () {
                if ( $.fn && $.fn.select2 ) {
                    $( '#pta-rf-sheet-ids' ).val( null ).trigger( 'change' );
                } else {
                    $( '#pta-rf-sheet-ids option' ).prop( 'selected', false );
                }
                $( '#pta-rf-start-date, #pta-rf-end-date' ).val( '' );
                $( '#pta-rf-show-expired' ).prop( 'checked', !! PTASUS.rfShowExpired );
                $( '#pta-rf-show-empty' ).prop( 'checked', !! PTASUS.rfShowEmpty );
                rfSheetIds    = [];
                rfStartDate   = '';
                rfEndDate     = '';
                rfShowExpired = PTASUS.rfShowExpired ? 1 : 0;
                rfShowEmpty   = PTASUS.rfShowEmpty   ? 1 : 0;
                allTable.ajax.reload();
            } );
        } else {
            // Client-side: Reset navigates to the base view_all URL (no rf_ params).
            $( '#pta-rf-reset' ).on( 'click', function () {
                window.location.href = $( this ).data( 'reset-url' );
            } );
        }
    }

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