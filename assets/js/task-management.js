/**
 * Task Management Modal System
 * Handles modal-based task editing, saving, deleting, and reordering
 */
(function($) {
	'use strict';

	var TaskManager = {
		modal: null,
		hasUnsavedChanges: false,
		originalFormData: null,
		sheetId: 0,
		sheetType: '',
		noSignups: false,
		originalDates: {},
		datesChanged: false,

		init: function() {
			// Only initialize on task management pages
			if (!$('.pta-sus-task-management').length) {
				return;
			}

			this.sheetId = ptaSusTaskData.sheetId;
			this.sheetType = ptaSusTaskData.sheetType;
			this.noSignups = ptaSusTaskData.noSignups;

			// Store original dates for change detection
			this.storeOriginalDates();

			this.initModal();
			this.initEventHandlers();
			this.initDragDrop();
			this.initDateChangeTracking();
		},

		initModal: function() {
			var self = this;
			// Initialize jQuery UI Dialog for modal
			this.modal = $('#pta-sus-task-modal').dialog({
				autoOpen: false,
				modal: true,
				width: 900,
				height: 'auto',
				maxHeight: $(window).height() * 0.9,
				resizable: true,
				draggable: true,
				closeOnEscape: true,
				beforeClose: function(event, ui) {
					return self.handleModalClose();
				},
				open: function(event, ui) {
					// Wait a bit for dialog to fully render before initializing plugins
					setTimeout(function() {
						// Reinitialize form plugins (including Quill)
						self.initFormPlugins();
						// Ensure email templates toggle is bound after modal is open
						self.initEmailTemplatesToggle();
					}, 100);
				},
				close: function(event, ui) {
					// Sync Quill content before closing
					var $container = $('#task_description-quill-container');
					if ($container.length) {
						var quillInstance = $container.data('quill-instance');
						if (quillInstance && quillInstance.root) {
							$('#task_description').val(quillInstance.root.innerHTML);
						}
					}
				}
			});

			// Initialize form plugins
			this.initFormPlugins();
		},

		initFormPlugins: function() {
			// Initialize timepickers in modal
			$('#pta-sus-task-modal .pta-timepicker').timepicker({
				showPeriod: true,
				showLeadingZero: true,
				defaultTime: ''
			});

			// Initialize datepicker in modal
			$('#pta-sus-task-modal .singlePicker').datepick({
				pickerClass: 'pta',
				monthsToShow: 1,
				dateFormat: 'yyyy-mm-dd',
				showTrigger: '#calImg'
			});

			// Initialize Quill editor if available
			this.initQuillEditor();

			// Details checkbox toggle - use event delegation to ensure it works
			$('#pta-sus-task-modal').off('change', '.details_checkbox').on('change', '.details_checkbox', function() {
				$('#pta-sus-task-modal .pta_toggle').toggle(this.checked);
			});

			// Email templates toggle will be initialized separately in initEmailTemplatesToggle()

			// Track form changes
			$('#pta-sus-task-form').off('change input', 'input, select, textarea').on('change input', 'input, select, textarea', function() {
				TaskManager.hasUnsavedChanges = true;
			});
		},

		initEmailTemplatesToggle: function() {
			// Email templates toggle - bind directly to the element after modal is open
			// This ensures the element exists in the DOM
			var $trigger = $('#pta-sus-task-modal .task_email_templates_trigger');
			if ($trigger.length) {
				$trigger.off('click').on('click', function(e) {
					e.preventDefault();
					var $emailTemplates = $('#pta-sus-task-modal .pta_sus_task_email_templates');
					if ($emailTemplates.length) {
						if ($emailTemplates.is(':visible')) {
							$emailTemplates.hide();
						} else {
							$emailTemplates.show();
						}
					}
				});
			}
		},

		initQuillEditor: function() {
			if (typeof Quill === 'undefined') {
				return; // Quill not loaded
			}

			var $container = $('#task_description-quill-container');
			var $hiddenInput = $('#task_description');

			if (!$container.length || !$hiddenInput.length) {
				return; // Not using Quill (HTML disabled)
			}

			// Check if Quill is already initialized on this container
			if ($container.data('quill-instance')) {
				return; // Already initialized
			}

			var initialContent = $hiddenInput.val() || '';

			// Initialize Quill with clipboard module to handle HTML pasting
			var quill = new Quill($container[0], {
				theme: 'snow',
				modules: {
					toolbar: [
						[{ 'header': [1, 2, 3, false] }],
						['bold', 'italic', 'underline', 'strike'],
						[{ 'list': 'ordered'}, { 'list': 'bullet' }],
						['blockquote', 'code-block'],
						['link'],
						['clean']
					],
					clipboard: {
						matchVisual: false // Convert pasted HTML to Quill's format
					}
				}
			});

			// Set initial content - Quill will parse and convert HTML to its format
			if (initialContent) {
				// Use clipboard convert to safely convert HTML to Quill's Delta format
				var delta = quill.clipboard.convert({ html: initialContent });
				quill.setContents(delta, 'silent');
			}

			// Sync Quill content to hidden input on text change
			quill.on('text-change', function() {
				$hiddenInput.val(quill.root.innerHTML);
				TaskManager.hasUnsavedChanges = true;
			});

			// Custom link handler
			var toolbar = quill.getModule('toolbar');
			if (toolbar) {
				toolbar.addHandler('link', function(value) {
					if (value) {
						var href = prompt('Enter the URL:');
						if (href) {
							quill.format('link', href);
						} else {
							quill.format('link', false);
						}
					} else {
						quill.format('link', false);
					}
				});
			}

			// Store Quill instance for later retrieval
			$container.data('quill-instance', quill);
		},

		initEventHandlers: function() {
			var self = this;

			// Add New Task button
			$('#pta-sus-add-new-task').on('click', function() {
				self.openModalForNewTask();
			});

			// Add Copy Task button
			$('#pta-sus-add-copy-task').on('click', function() {
				var taskId = $('#pta-sus-copy-task-select').val();
				if (taskId > 0) {
					self.openModalForCopy(taskId);
				} else {
					self.openModalForNewTask();
				}
			});

			// Edit Task buttons
			$(document).on('click', '.pta-sus-edit-task', function() {
				var taskId = $(this).data('task-id');
				self.openModalForEdit(taskId);
			});

			// Copy Task buttons
			$(document).on('click', '.pta-sus-copy-task', function() {
				var taskId = $(this).data('task-id');
				self.openModalForCopy(taskId);
			});

			// Delete Task buttons
			$(document).on('click', '.pta-sus-delete-task', function() {
				var taskId = $(this).data('task-id');
				self.deleteTask(taskId);
			});

			// Save & Close button
			$('#pta-sus-task-save').on('click', function() {
				self.saveTask();
			});

			// Cancel button
			$('#pta-sus-task-cancel').on('click', function() {
				self.closeModal();
			});

			// Save Order button
			$('#pta-sus-save-order').on('click', function() {
				self.saveTaskOrder();
			});

			// Save Dates button (for Single/Recurring sheets)
			$('#pta-sus-save-dates').on('click', function() {
				self.saveSheetDates();
			});
		},

		storeOriginalDates: function() {
			// Store original dates for change detection
			if (this.sheetType === 'Single') {
				this.originalDates.single = $('#single_date').val() || '';
			} else if (this.sheetType === 'Recurring') {
				this.originalDates.recurring = $('#multi999Picker').val() || '';
			}
		},

		initDateChangeTracking: function() {
			var self = this;
			
			// Track changes to date inputs
			if (this.sheetType === 'Single') {
				var $singleDate = $('#single_date');
				// Only attach datepicker if not readonly
				if (!$singleDate.prop('readonly')) {
					// Initialize datepicker if not already done
					if (!$singleDate.data('datepick-initialized')) {
						$singleDate.datepick({
							pickerClass: 'pta',
							monthsToShow: 1,
							dateFormat: 'yyyy-mm-dd',
							showTrigger: '#calImg'
						});
						$singleDate.data('datepick-initialized', true);
					}
					$singleDate.on('change', function() {
						self.checkDateChanges();
					});
				} else {
					// Disable datepicker on readonly fields
					if ($singleDate.data('datepick-initialized')) {
						$singleDate.datepick('destroy');
						$singleDate.removeData('datepick-initialized');
					}
				}
			} else if (this.sheetType === 'Recurring') {
				$('#multi999Picker').on('change', function() {
					self.checkDateChanges();
				});
			}
		},

		checkDateChanges: function() {
			var changed = false;
			
			if (this.sheetType === 'Single') {
				var currentDate = $('#single_date').val() || '';
				changed = (currentDate !== this.originalDates.single);
			} else if (this.sheetType === 'Recurring') {
				var currentDates = $('#multi999Picker').val() || '';
				changed = (currentDates !== this.originalDates.recurring);
			}
			
			this.datesChanged = changed;
			this.updateDateChangeIndicator();
		},

		updateDateChangeIndicator: function() {
			var $button = $('#pta-sus-save-dates');
			var $section = $('.pta-sus-sheet-dates-section');
			
			if (this.datesChanged) {
				// Add visual indicator that dates have changed
				if (!$section.find('.dates-changed-indicator').length) {
					$button.after('<span class="dates-changed-indicator" style="color: #d63638; margin-left: 10px; font-weight: bold;">*</span>');
				}
				$button.removeClass('button-secondary').addClass('button-primary');
			} else {
				$section.find('.dates-changed-indicator').remove();
			}
		},

		initDragDrop: function() {
			var self = this;
			var $tbody = $('#pta-sus-task-list-body');

			if (!$tbody.length) {
				return;
			}

			$tbody.sortable({
				handle: '.column-drag',
				axis: 'y',
				opacity: 0.6,
				cursor: 'move',
				update: function(event, ui) {
					// Show save order button and ensure it's in correct state
					var $button = $('#pta-sus-save-order');
					$button.prop('disabled', false).text('Save Order');
					$('#pta-sus-save-order-container').show();
					
					// Try immediate save (if it doesn't cause issues)
					// Otherwise, user can click Save Order button
					// For now, we'll use the button approach to be safe
				}
			});
		},

		openModalForNewTask: function() {
			// Check if dates are required and set
			if (this.sheetType === 'Single' || this.sheetType === 'Recurring') {
				var hasDates = false;
				if (this.sheetType === 'Single') {
					var singleDate = $('#single_date').val();
					hasDates = singleDate && singleDate.trim() !== '';
				} else if (this.sheetType === 'Recurring') {
					var recurringDates = $('#multi999Picker').val();
					hasDates = recurringDates && recurringDates.trim() !== '';
				}
				
				if (!hasDates) {
					var dateLabel = this.sheetType === 'Single' ? 'date' : 'dates';
					var buttonLabel = this.sheetType === 'Single' ? 'Save Date' : 'Save Dates';
					var message = 'Please set ' + dateLabel + ' for this sheet before adding tasks.\n\n' +
					              'Click "Cancel" to set ' + dateLabel + ' first, or "OK" to continue anyway (you will need to set ' + dateLabel + ' before saving the task).';
					if (!confirm(message)) {
						// Focus on the date input
						if (this.sheetType === 'Single') {
							$('#single_date').focus();
						} else {
							$('#multi999Picker').focus();
						}
						return;
					}
				}
			}
			
			this.resetForm();
			this.setModalTitle('Add New Task');
			$('#task_id').val(0);
			this.hasUnsavedChanges = false;
			this.originalFormData = this.getFormData();
			this.modal.dialog('open');
		},

		openModalForEdit: function(taskId) {
			var self = this;
			
			// Open modal first
			this.modal.dialog('open');
			this.setModalTitle('Loading...');
			// Show loading overlay
			var $form = $('#pta-sus-task-form');
			$form.css('opacity', '0.5').css('pointer-events', 'none');

			// Load task data via AJAX
			$.ajax({
				url: ptaSusTaskData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pta_sus_get_task',
					nonce: ptaSusTaskData.nonce,
					task_id: taskId
				},
				success: function(response) {
					$form.css('opacity', '1').css('pointer-events', 'auto');
					if (response.success && response.data.task) {
						self.populateForm(response.data.task);
						self.setModalTitle('Edit Task');
						self.hasUnsavedChanges = false;
						// Wait a bit for Quill to initialize before getting form data
						setTimeout(function() {
							self.originalFormData = self.getFormData();
						}, 200);
					} else {
						alert(response.data.message || 'Error loading task data.');
						self.modal.dialog('close');
					}
				},
				error: function() {
					$form.css('opacity', '1').css('pointer-events', 'auto');
					alert('Error loading task data.');
					self.modal.dialog('close');
				}
			});
		},

		openModalForCopy: function(taskId) {
			var self = this;
			
			// Load task data and open as new task
			$.ajax({
				url: ptaSusTaskData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pta_sus_get_task',
					nonce: ptaSusTaskData.nonce,
					task_id: taskId
				},
				success: function(response) {
					if (response.success && response.data.task) {
						var taskData = response.data.task;
						// Clear task_id to make it a new task
						taskData.task_id = 0;
						// Keep original title (no "(Copy)" suffix - tasks can have same name across sheets)
						self.modal.dialog('open');
						self.populateForm(taskData);
						self.setModalTitle('Add New Task');
						self.hasUnsavedChanges = false;
						// Wait a bit for Quill to initialize before getting form data
						setTimeout(function() {
							self.originalFormData = self.getFormData();
						}, 200);
					} else {
						alert(response.data.message || 'Error loading task data.');
					}
				},
				error: function() {
					alert('Error loading task data.');
				}
			});
		},

		populateForm: function(taskData) {
			var self = this;
			// Populate all form fields
			$('#task_id').val(taskData.task_id || 0);
			$('#task_title').val(taskData.task_title || '');
			
			// Handle date field - make readonly if task has signups (Multi-Day only)
			var $dateField = $('#task_dates');
			if ($dateField.length) {
				$dateField.val(taskData.task_dates || '');
				if (taskData.task_has_signups && this.sheetType === 'Multi-Day') {
					$dateField.prop('readonly', true);
					// Add warning message
					var $dateRow = $dateField.closest('tr');
					if ($dateRow.find('.date-signup-warning').length === 0) {
						$dateField.after('<span class="date-signup-warning" style="color: #d63638; margin-left: 10px;"><em>' + 'This date cannot be changed because there are signups for this task on this date. Please clear signups first.' + '</em></span>');
					}
				} else {
					$dateField.prop('readonly', false);
					$dateField.siblings('.date-signup-warning').remove();
				}
			}
			
			$('#task_qty').val(taskData.task_qty || 1);
			$('#task_time_start').val(taskData.task_time_start || '');
			$('#task_time_end').val(taskData.task_time_end || '');
			$('#task_allow_duplicates').prop('checked', taskData.task_allow_duplicates === 'YES');
			$('#task_enable_quantities').prop('checked', taskData.task_enable_quantities === 'YES');
			$('#task_need_details').prop('checked', taskData.task_need_details === 'YES');
			$('#task_details_required').prop('checked', taskData.task_details_required === 'YES');
			$('#task_details_text').val(taskData.task_details_text || 'Item you are bringing');

			// Set description - always using Quill editor
			var description = taskData.task_description || '';
			var $container = $('#task_description-quill-container');
			var $hiddenInput = $('#task_description');
			
			$hiddenInput.val(description);
			// Initialize Quill if not already done
			if (!$container.data('quill-instance')) {
				this.initQuillEditor();
			}
			// Set content in Quill
			var quillInstance = $container.data('quill-instance');
			if (quillInstance) {
				if (description) {
					var delta = quillInstance.clipboard.convert({ html: description });
					quillInstance.setContents(delta, 'silent');
				} else {
					quillInstance.setContents([], 'silent');
				}
			}

			// Set email template IDs
			$('#task_confirmation_email_template_id').val(taskData.task_confirmation_email_template_id || 0);
			$('#task_reminder1_email_template_id').val(taskData.task_reminder1_email_template_id || 0);
			$('#task_reminder2_email_template_id').val(taskData.task_reminder2_email_template_id || 0);
			$('#task_clear_email_template_id').val(taskData.task_clear_email_template_id || 0);
			$('#task_reschedule_email_template_id').val(taskData.task_reschedule_email_template_id || 0);

			// Toggle details section if needed
			if (taskData.task_need_details === 'YES') {
				$('#pta-sus-task-modal .pta_toggle').show();
			} else {
				$('#pta-sus-task-modal .pta_toggle').hide();
			}

			// Reinitialize form plugins
			this.initFormPlugins();
		},

		resetForm: function() {
			$('#pta-sus-task-form')[0].reset();
			$('#task_id').val(0);
			$('#task_qty').val(1);
			$('#task_details_text').val('Item you are bringing');
			$('#task_need_details, #task_details_required, #task_allow_duplicates, #task_enable_quantities').prop('checked', false);
			$('#pta-sus-task-modal .pta_toggle').hide();

			// Clear description - always using Quill editor
			var $container = $('#task_description-quill-container');
			var $hiddenInput = $('#task_description');
			
			$hiddenInput.val('');
			var quillInstance = $container.data('quill-instance');
			if (quillInstance) {
				quillInstance.setContents([], 'silent');
			}

			// Reset email template selects
			$('#pta-sus-task-modal select[id^="task_"][id$="_email_template_id"]').val(0);
		},

		getFormData: function() {
			// Get description from Quill editor (always used)
			var description = '';
			var $container = $('#task_description-quill-container');
			var $hiddenInput = $('#task_description');
			
			// Using Quill editor - sync content first
			var quillInstance = $container.data('quill-instance');
			if (quillInstance && quillInstance.root) {
				// Get HTML from Quill
				description = quillInstance.root.innerHTML;
				// Sync to hidden input
				$hiddenInput.val(description);
			} else {
				// Fallback to hidden input value
				description = $hiddenInput.val();
			}

			return {
				task_id: $('#task_id').val(),
				task_title: $('#task_title').val(),
				task_description: description,
				task_dates: $('#task_dates').val(),
				task_qty: $('#task_qty').val(),
				task_time_start: $('#task_time_start').val(),
				task_time_end: $('#task_time_end').val(),
				task_allow_duplicates: $('#task_allow_duplicates').is(':checked') ? 'YES' : 'NO',
				task_enable_quantities: $('#task_enable_quantities').is(':checked') ? 'YES' : 'NO',
				task_need_details: $('#task_need_details').is(':checked') ? 'YES' : 'NO',
				task_details_required: $('#task_details_required').is(':checked') ? 'YES' : 'NO',
				task_details_text: $('#task_details_text').val(),
				task_confirmation_email_template_id: $('#task_confirmation_email_template_id').val(),
				task_reminder1_email_template_id: $('#task_reminder1_email_template_id').val(),
				task_reminder2_email_template_id: $('#task_reminder2_email_template_id').val(),
				task_clear_email_template_id: $('#task_clear_email_template_id').val(),
				task_reschedule_email_template_id: $('#task_reschedule_email_template_id').val()
			};
		},

		saveTask: function() {
			var self = this;
			
			// Sync Quill content before getting form data
			var $container = $('#task_description-quill-container');
			if ($container.length && typeof Quill !== 'undefined') {
				var quillInstance = $container.data('quill-instance');
				if (quillInstance && quillInstance.root) {
					$('#task_description').val(quillInstance.root.innerHTML);
				}
			}
			
			var formData = this.getFormData();

			// Basic validation
			if (!formData.task_title || formData.task_title.trim() === '') {
				alert('Task title is required.');
				return;
			}

			// Add sheet info to form data
			formData.task_sheet_id = this.sheetId;
			formData.task_sheet_type = this.sheetType;
			formData.task_no_signups = this.noSignups ? 1 : 0;
			formData.action = 'pta_sus_save_task';
			formData.nonce = ptaSusTaskData.nonce;

			// Add dates for Single/Recurring/Ongoing sheets from input fields
			if (this.sheetType === 'Single' || this.sheetType === 'Ongoing') {
				// Get date from input field (more reliable than stored data)
				var singleDate = $('#single_date').val();
				formData.single_date = singleDate || ptaSusTaskData.singleDate || '';
			} else if (this.sheetType === 'Recurring') {
				// Get dates from input field (more reliable than stored data)
				var recurringDates = $('#multi999Picker').val();
				formData.recurring_dates = recurringDates || ptaSusTaskData.recurringDates || '';
			}

			// Disable save button
			$('#pta-sus-task-save').prop('disabled', true).text('Saving...');

			$.ajax({
				url: ptaSusTaskData.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						self.hasUnsavedChanges = false;
						self.closeModal();
						
						// Display messages (HTML is already formatted from server)
						if (response.data && response.data.message) {
							self.showAjaxMessage(response.data.message, 'success');
						}
						
						// Update table dynamically instead of reloading
						if (response.data.task_id) {
							// Use task data from response if available, otherwise fetch it
							if (response.data.task && response.data.action === 'created') {
								// New task - add to table using data from response
								var $tbody = $('#pta-sus-task-list-body');
								// Remove "no tasks" row if it exists
								$tbody.find('.pta-sus-no-tasks-row').remove();
								// Show table if it was hidden
								$('.pta-sus-task-list').show();
								// Build and append the new row
								var $row = self.buildTaskRow(response.data.task);
								$tbody.append($row);
								// Reinitialize sortable
								self.initDragDrop();
							} else if (response.data.task && response.data.action === 'updated') {
								// Updated task - replace the row
								var $existingRow = $('#pta-sus-task-list-body tr[data-task-id="' + response.data.task_id + '"]');
								if ($existingRow.length) {
									var $newRow = self.buildTaskRow(response.data.task);
									$existingRow.replaceWith($newRow);
									// Reinitialize sortable
									self.initDragDrop();
								} else {
									// Row doesn't exist, fetch and add it
									self.addTaskToTable(response.data.task_id);
								}
							} else {
								// Fallback: fetch task data
								if (response.data.action === 'created') {
									self.addTaskToTable(response.data.task_id);
								} else {
									self.updateTaskInTable(response.data.task_id);
								}
							}
						}
					} else {
						// Display error messages from PTA_SUS_Messages
						if (response.data.message) {
							// Messages are already formatted HTML from PTA_SUS_Messages
							// Insert messages at top of modal or show in alert
							var $modal = $('#pta-sus-task-modal');
							var $messageContainer = $modal.find('.pta-sus-task-messages');
							if ($messageContainer.length === 0) {
								$messageContainer = $('<div class="pta-sus-task-messages"></div>');
								$modal.find('form').prepend($messageContainer);
							}
							$messageContainer.html(response.data.message).show();
							// Scroll to top of modal
							$modal.scrollTop(0);
						} else {
							alert('Error saving task.');
						}
						$('#pta-sus-task-save').prop('disabled', false).text('Save & Close');
					}
				},
				error: function() {
					alert('Error saving task.');
					$('#pta-sus-task-save').prop('disabled', false).text('Save & Close');
				}
			});
		},

		deleteTask: function(taskId) {
			if (!confirm('Are you sure you want to delete this task?')) {
				return;
			}

			var self = this;

			$.ajax({
				url: ptaSusTaskData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pta_sus_delete_task',
					nonce: ptaSusTaskData.nonce,
					task_id: taskId
				},
				success: function(response) {
					if (response.success) {
						// Display messages (HTML is already formatted from server)
						if (response.data && response.data.message) {
							self.showAjaxMessage(response.data.message, 'success');
						}
						// Remove the task row from table
						$('#pta-sus-task-list-body tr[data-task-id="' + taskId + '"]').remove();
						
						// If no tasks left, show "no tasks" message
						var $tbody = $('#pta-sus-task-list-body');
						if ($tbody.find('tr').length === 0) {
							var colspan = 6;
							if (self.sheetType === 'Multi-Day') colspan++;
							if (!self.noSignups) colspan++;
							var noTasksRow = '<tr class="pta-sus-no-tasks-row"><td colspan="' + colspan + '" class="pta-sus-no-tasks" style="text-align: center; padding: 20px; color: #666;">No tasks have been created yet. Click "Add New Task" to get started.</td></tr>';
							$tbody.html(noTasksRow);
						}
					} else {
						// Display error messages (errors don't auto-dismiss)
						if (response.data && response.data.message) {
							self.showAjaxMessage(response.data.message, 'error');
						} else {
							alert('Error deleting task.');
						}
					}
				},
				error: function() {
					alert('Error deleting task.');
				}
			});
		},

		saveTaskOrder: function() {
			var self = this;
			var taskOrder = [];

			$('#pta-sus-task-list-body tr').each(function(index) {
				var taskId = $(this).data('task-id');
				if (taskId) {
					taskOrder[index] = taskId;
				}
			});

			$('#pta-sus-save-order').prop('disabled', true).text('Saving...');

			$.ajax({
				url: ptaSusTaskData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pta_sus_reorder_tasks',
					nonce: ptaSusTaskData.nonce,
					sheet_id: this.sheetId,
					task_order: taskOrder
				},
				success: function(response) {
					// Always reset button state first
					$('#pta-sus-save-order').prop('disabled', false).text('Save Order');
					
					if (response.success) {
						$('#pta-sus-save-order-container').hide();
						// Display messages (HTML is already formatted from server)
						if (response.data && response.data.message) {
							self.showAjaxMessage(response.data.message, 'success');
						}
						// Update position attributes on rows
						$('#pta-sus-task-list-body tr').each(function(index) {
							$(this).attr('data-position', index);
						});
					} else {
						// Display error messages (errors don't auto-dismiss)
						if (response.data && response.data.message) {
							self.showAjaxMessage(response.data.message, 'error');
						} else {
							alert('Error saving task order.');
						}
						$('#pta-sus-save-order').prop('disabled', false).text('Save Order');
					}
				},
				error: function() {
					alert('Error saving task order.');
					$('#pta-sus-save-order').prop('disabled', false).text('Save Order');
				}
			});
		},

		saveSheetDates: function() {
			var self = this;
			var $button = $('#pta-sus-save-dates');
			
			// Don't allow saving if button is disabled (sheet has signups)
			if ($button.prop('disabled')) {
				return;
			}
			
			var $spinner = $button.siblings('.spinner');
			var sheetId = $button.data('sheet-id');
			var sheetType = $button.data('sheet-type');
			
			// Check if dates actually changed (for Single sheets with signups check)
			if ('Single' === sheetType) {
				var currentDate = $('#single_date').val();
				if (currentDate === this.originalDates.single) {
					// No change - don't save
					return;
				}
			}
			
			// Check if modal is open and warn user
			if (this.modal && this.modal.dialog('isOpen') && this.hasUnsavedChanges) {
				if (!confirm('You have unsaved changes in the task form. The dates will be saved, but you may want to save your task first. Continue saving dates?')) {
					return;
				}
			}
			
			// Get dates based on sheet type
			var postData = {
				action: 'pta_sus_save_sheet_dates',
				nonce: ptaSusTaskData.nonce,
				sheet_id: sheetId,
				sheet_type: sheetType
			};
			
			if ('Single' === sheetType) {
				var singleDate = $('#single_date').val();
				if (!singleDate) {
					self.showAjaxMessage('<div class="error inline"><p><strong>Please select a date.</strong></p></div>', 'error');
					return;
				}
				postData.single_date = singleDate;
			} else if ('Recurring' === sheetType) {
				var recurringDates = $('#multi999Picker').val();
				if (!recurringDates) {
					self.showAjaxMessage('<div class="error inline"><p><strong>Please select at least two dates.</strong></p></div>', 'error');
					return;
				}
				postData.recurring_dates = recurringDates;
			} else if ('Ongoing' === sheetType) {
				// Ongoing doesn't need dates, but we'll send it anyway
				postData.recurring_dates = '';
			}
			
			// Disable button and show spinner
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: ptaSusTaskData.ajaxUrl,
				type: 'POST',
				data: postData,
				success: function(response) {
					$spinner.removeClass('is-active');
					$button.prop('disabled', false);
					
					if (response.success) {
						// Display messages using showAjaxMessage (auto-dismisses after 5 seconds)
						if (response.data && response.data.message) {
							self.showAjaxMessage(response.data.message, 'success');
						}
						// Update stored dates
						if ('Single' === sheetType) {
							ptaSusTaskData.singleDate = postData.single_date;
							self.originalDates.single = postData.single_date;
							// Update the date input value
							$('#single_date').val(postData.single_date);
							// Update the indicator icon
							var $dateRow = $('#single_date').closest('p');
							$dateRow.find('.dashicons').remove();
							$dateRow.find('label').after('<span class="dashicons dashicons-yes-alt" style="color: #46b450; vertical-align: middle; margin-left: 5px;" title="Date is set"></span>');
						} else if ('Recurring' === sheetType) {
							ptaSusTaskData.recurringDates = postData.recurring_dates;
							self.originalDates.recurring = postData.recurring_dates;
							// Update the date input value
							$('#multi999Picker').val(postData.recurring_dates);
							// Update the indicator icon
							var $dateRow = $('#multi999Picker').closest('p');
							$dateRow.find('.dashicons').remove();
							$dateRow.find('label').after('<span class="dashicons dashicons-yes-alt" style="color: #46b450; vertical-align: middle; margin-left: 5px;" title="Dates are set"></span>');
						}
						// Reset change tracking
						self.datesChanged = false;
						self.updateDateChangeIndicator();
						// Reload page after a short delay to show updated task dates (only if tasks exist)
						if (response.data.tasks_updated > 0) {
							setTimeout(function() {
								window.location.reload();
							}, 1500);
						}
					} else {
						// Display error messages (errors don't auto-dismiss)
						if (response.data && response.data.message) {
							self.showAjaxMessage(response.data.message, 'error');
						}
						// If has_signups flag is set, update the readonly state and disable button
						if (response.data.has_signups && 'Single' === sheetType) {
							$('#single_date').prop('readonly', true);
							$button.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
							// Destroy datepicker on readonly field
							var $singleDate = $('#single_date');
							if ($singleDate.data('datepick-initialized')) {
								$singleDate.datepick('destroy');
								$singleDate.removeData('datepick-initialized');
							}
						}
					}
				},
				error: function() {
					$spinner.removeClass('is-active');
					$button.prop('disabled', false);
					self.showAjaxMessage('<div class="error inline"><p><strong>Error saving dates. Please try again.</strong></p></div>', 'error');
				}
			});
		},

		refreshTaskList: function() {
			// Reload the page to refresh the task list
			// In the future, we could do this via AJAX to avoid full page reload
			window.location.reload();
		},

		addTaskToTable: function(taskId) {
			var self = this;
			// Fetch the task data
			$.ajax({
				url: ptaSusTaskData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pta_sus_get_task',
					nonce: ptaSusTaskData.nonce,
					task_id: taskId
				},
				success: function(response) {
					if (response.success && response.data.task) {
						var task = response.data.task;
						var $tbody = $('#pta-sus-task-list-body');
						
						// Remove "no tasks" row if it exists
						$tbody.find('.pta-sus-no-tasks-row').remove();
						
						// Show table if it was hidden
						$('.pta-sus-task-list').show();
						
						// Build and append the new row
						var $row = self.buildTaskRow(task);
						$tbody.append($row);
						
						// Reinitialize sortable
						self.initDragDrop();
					}
				},
				error: function() {
					// Fallback to page reload if AJAX fails
					self.refreshTaskList();
				}
			});
		},

		updateTaskInTable: function(taskId) {
			var self = this;
			// Fetch the updated task data
			$.ajax({
				url: ptaSusTaskData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pta_sus_get_task',
					nonce: ptaSusTaskData.nonce,
					task_id: taskId
				},
				success: function(response) {
					if (response.success && response.data.task) {
						var task = response.data.task;
						var $existingRow = $('#pta-sus-task-list-body tr[data-task-id="' + taskId + '"]');
						
						if ($existingRow.length) {
							// Replace existing row
							var $newRow = self.buildTaskRow(task);
							$existingRow.replaceWith($newRow);
							
							// Reinitialize sortable
							self.initDragDrop();
						} else {
							// Row doesn't exist, add it
							self.addTaskToTable(taskId);
						}
					}
				},
				error: function() {
					// Fallback to page reload if AJAX fails
					self.refreshTaskList();
				}
			});
		},

		buildTaskRow: function(task) {
			var self = this;
			var sheetType = this.sheetType;
			var noSignups = this.noSignups;
			
			// Format times - use simple format for now (can be enhanced later)
			// Times come from server as HH:MM:SS, we'll format them simply
			var startTime = task.task_time_start || '';
			var endTime = task.task_time_end || '';
			
			// Simple time formatting function
			function formatTime(timeStr) {
				if (!timeStr) return '';
				var parts = timeStr.split(':');
				if (parts.length < 2) return timeStr;
				var hour = parseInt(parts[0], 10);
				var min = parts[1];
				// Use 12-hour format with AM/PM
				var ampm = hour >= 12 ? 'PM' : 'AM';
				hour = hour % 12;
				if (hour === 0) hour = 12;
				return hour + ':' + min + ' ' + ampm;
			}
			
			startTime = formatTime(startTime);
			endTime = formatTime(endTime);
			
			// Build description preview
			var description = '';
			if (task.task_description) {
				// Strip HTML and get first 10 words
				var text = $('<div>').html(task.task_description).text();
				var words = text.split(/\s+/);
				description = words.slice(0, 10).join(' ');
				if (words.length > 10) description += '...';
			}
			
			// Build row HTML
			var rowHtml = '<tr data-task-id="' + task.task_id + '" data-position="' + (task.task_position || 0) + '">';
			rowHtml += '<td class="column-drag"><span class="dashicons dashicons-move" style="cursor: move;"></span></td>';
			rowHtml += '<td class="column-title"><strong>' + self.escapeHtml(task.task_title || '') + '</strong>';
			if (description) {
				rowHtml += '<br/><small>' + self.escapeHtml(description) + '</small>';
			}
			rowHtml += '</td>';
			
			if (sheetType === 'Multi-Day') {
				rowHtml += '<td class="column-date">' + self.escapeHtml(task.task_dates || '') + '</td>';
			}
			
			if (!noSignups) {
				rowHtml += '<td class="column-qty">' + (task.task_qty || 0) + '</td>';
			}
			
			rowHtml += '<td class="column-time">' + self.escapeHtml(startTime) + '</td>';
			rowHtml += '<td class="column-time">' + self.escapeHtml(endTime) + '</td>';
			rowHtml += '<td class="column-actions">';
			rowHtml += '<button type="button" class="button button-small pta-sus-edit-task" data-task-id="' + task.task_id + '">Edit</button> ';
			rowHtml += '<button type="button" class="button button-small pta-sus-copy-task" data-task-id="' + task.task_id + '">Copy</button> ';
			rowHtml += '<button type="button" class="button button-small pta-sus-delete-task" data-task-id="' + task.task_id + '">Delete</button>';
			rowHtml += '</td>';
			rowHtml += '</tr>';
			
			return $(rowHtml);
		},

		escapeHtml: function(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
		},

		showAjaxMessage: function(messageHtml, type) {
			var self = this;
			type = type || 'success';
			
			// Find or create messages container
			var $messagesContainer = $('.pta-sus-ajax-messages');
			if ($messagesContainer.length === 0) {
				$messagesContainer = $('<div class="pta-sus-ajax-messages"></div>');
				$('.pta-sus-task-management').before($messagesContainer);
			}
			
			// Parse the message HTML (server returns divs with class "error" or "updated")
			var $messageDivs = $(messageHtml);
			
			// Wrap each message div in a container with dismiss functionality
			$messageDivs.each(function() {
				var $notice = $(this);
				if (!$notice.hasClass('error') && !$notice.hasClass('updated') && !$notice.hasClass('notice')) {
					// If it's a wrapper, find the actual notice inside
					$notice = $notice.find('.error, .updated, .notice').first();
				}
				
				if ($notice.length && ($notice.hasClass('error') || $notice.hasClass('updated') || $notice.hasClass('notice'))) {
					// Create wrapper for this message
					var $messageWrapper = $('<div class="pta-sus-ajax-message-wrapper" style="position: relative; margin-bottom: 10px;"></div>');
					
					// Ensure notice has relative positioning for dismiss button
					$notice.css('position', 'relative').css('padding-right', '38px');
					
					// Add dismiss button (WordPress admin style)
					var $dismissBtn = $('<button type="button" class="notice-dismiss" style="position: absolute; top: 0; right: 1px; border: none; margin: 0; padding: 9px; background: none; cursor: pointer; color: #787c82;"><span class="screen-reader-text">Dismiss this notice.</span></button>');
					$notice.append($dismissBtn);
					
					$messageWrapper.append($notice);
					$messagesContainer.append($messageWrapper).show();
					
					// Handle dismiss button click
					$dismissBtn.on('click', function(e) {
						e.preventDefault();
						$messageWrapper.fadeOut(300, function() {
							$(this).remove();
							// Hide container if no messages left
							if ($messagesContainer.find('.pta-sus-ajax-message-wrapper').length === 0) {
								$messagesContainer.hide();
							}
						});
					});
					
					// Auto-fade out after 5 seconds (only for success/updated messages)
					if (type === 'success' && $notice.hasClass('updated')) {
						setTimeout(function() {
							if ($messageWrapper.is(':visible')) {
								$messageWrapper.fadeOut(500, function() {
									$(this).remove();
									// Hide container if no messages left
									if ($messagesContainer.find('.pta-sus-ajax-message-wrapper').length === 0) {
										$messagesContainer.hide();
									}
								});
							}
						}, 5000);
					}
				} else {
					// Fallback: just append the HTML as-is
					var $messageWrapper = $('<div class="pta-sus-ajax-message-wrapper" style="margin-bottom: 10px;"></div>');
					$messageWrapper.html(messageHtml);
					$messagesContainer.append($messageWrapper).show();
				}
			});
			
			// Scroll to top to show messages
			$('html, body').animate({ scrollTop: 0 }, 300);
		},

		handleModalClose: function() {
			// Check for unsaved changes in task form
			if (this.hasUnsavedChanges) {
				var confirmClose = confirm('You have unsaved changes in the task form. Are you sure you want to close without saving?');
				if (!confirmClose) {
					return false;
				}
			}
			
			// Check for unsaved date changes
			if (this.datesChanged && (this.sheetType === 'Single' || this.sheetType === 'Recurring')) {
				var dateLabel = this.sheetType === 'Single' ? 'date' : 'dates';
				var confirmClose = confirm('You have unsaved changes to the ' + dateLabel + '. The task form will close, but you should save the ' + dateLabel + ' using the "Save ' + (this.sheetType === 'Single' ? 'Date' : 'Dates') + '" button. Continue closing?');
				if (!confirmClose) {
					return false;
				}
			}
			
			return true;
		},

		closeModal: function() {
			if (this.handleModalClose()) {
				// Reset save button state before closing
				$('#pta-sus-task-save').prop('disabled', false).text('Save & Close');
				this.modal.dialog('close');
				this.hasUnsavedChanges = false;
			}
		},

		setModalTitle: function(title) {
			$('#pta-sus-task-modal-title').text(title);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		TaskManager.init();
	});

})(jQuery);

