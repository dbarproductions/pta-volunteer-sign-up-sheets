window.ptaVolunteer = {
    config: {
        ajaxUrl: '',
        method: 'POST',
        extraData: {},
        minSearchLength: 2,
        debounceDelay: 300
    },

    init(userConfig = {}) {
        this.config = {
            ...this.config,
            ...userConfig,
            fieldPrefix: userConfig.fieldPrefix !== undefined ? userConfig.fieldPrefix : 'signup_', // Default to 'signup_' for frontend
            updateUserDropdown: userConfig.updateUserDropdown || false // Admin can set to true
        };

        if (document.querySelector('input[name="signup_firstname"], input[name="signup_lastname"], input[name="firstname"], input[name="lastname"]')) {
            this.setupAutocomplete();
        }
    },

    htmlDecode(value) {
        if (!value) return '';
        const div = document.createElement('div');
        div.innerHTML = value;
        return div.textContent || div.innerText || '';
    },

    setupAutocomplete() {
        // Support both frontend (signup_*) and admin (*) field names
        const inputs = document.querySelectorAll(
            'input[name="signup_firstname"], input[name="signup_lastname"], input[name="signup_email"], ' +
            'input[name="firstname"], input[name="lastname"], input[name="email"]'
        );
        inputs.forEach(input => {
            let debounceTimer;
            let currentController;

            input.addEventListener('input', (e) => {
                const query = e.target.value.trim();

                // Clear previous debounce timer
                clearTimeout(debounceTimer);

                // Cancel previous request if still pending
                if (currentController) {
                    currentController.abort();
                }

                // Clear results if query is too short
                if (query.length < this.config.minSearchLength) {
                    this.clearAutocomplete(input);
                    return;
                }

                // Debounce the search
                debounceTimer = setTimeout(async () => {
                    try {
                        currentController = new AbortController();
                        const data = await this.fetchResults(query, currentController.signal);
                        this.renderAutocomplete(data, input);
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            // Only log/handle non-abort errors
                            this.clearAutocomplete(input);
                        }
                    } finally {
                        currentController = null;
                    }
                }, this.config.debounceDelay);
            });

            // Clear results when input loses focus (after a short delay to allow click)
            input.addEventListener('blur', () => {
                setTimeout(() => {
                    this.clearAutocomplete(input);
                }, 200);
            });
        });
    },

    async fetchResults(query, signal) {
        const formData = new FormData();
        formData.append('q', query);
        formData.append('pta_pub_action', 'autocomplete_volunteer');
        formData.append('action', this.config.extraData.action);
        formData.append('security', this.config.extraData.security);

        const response = await fetch(this.config.ajaxUrl, {
            method: 'POST',
            body: formData,
            signal: signal
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const json = await response.json();

        // Handle WordPress wp_send_json_success() response structure
        // {success: true, data: [...]} or {success: false, data: {...}}
        if (json && typeof json === 'object' && 'success' in json) {
            if (json.success && Array.isArray(json.data)) {
                return json.data;
            } else {
                // Error response or empty data
                return [];
            }
        }

        // Fallback for legacy response format (direct array)
        if (Array.isArray(json)) {
            return json;
        }

        return [];
    },

    renderAutocomplete(data, input) {
        let ul = input.parentNode.querySelector('.autocomplete-results');

        if (!ul) {
            ul = document.createElement('ul');
            ul.className = 'autocomplete-results';
            input.parentNode.appendChild(ul);
        }

        // If no data or invalid response, clear and hide results
        if (!data || !Array.isArray(data) || data.length === 0) {
            ul.innerHTML = '';
            ul.style.display = 'none';
            return;
        }

        // Store data in a closure for lookup (more reliable than data attributes)
        if (!ul._volunteerData) {
            ul._volunteerData = [];
        }
        ul._volunteerData = data;

        // Show and populate results
        ul.style.display = 'block';
        ul.innerHTML = data.map((item, index) => {
            const firstname = this.htmlDecode(item.firstname || '');
            const lastname = this.htmlDecode(item.lastname || '');
            const email = this.htmlDecode(item.email || '');

            return `
                <li>
                    <a href="#" data-index="${index}">
                        <strong>${firstname} ${lastname}</strong>
                        <br>
                        <small>${email}</small>
                    </a>
                </li>
            `;
        }).join('');

        // Update click handler to use stored data
        ul.removeEventListener('click', ul._clickHandler);
        ul._clickHandler = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const link = e.target.closest('a[data-index]');
            if (link && ul._volunteerData) {
                const index = parseInt(link.getAttribute('data-index'), 10);
                if (!isNaN(index) && ul._volunteerData[index]) {
                    this.populateFields(ul._volunteerData[index]);
                    this.clearAutocomplete(input);
                }
            }
        };
        ul.addEventListener('click', ul._clickHandler);
    },

    clearAutocomplete(input) {
        const ul = input.parentNode.querySelector('.autocomplete-results');
        if (ul) {
            ul.innerHTML = '';
            ul.style.display = 'none';
            // Clean up stored data and event handler
            if (ul._volunteerData) {
                ul._volunteerData = null;
            }
            if (ul._clickHandler) {
                ul.removeEventListener('click', ul._clickHandler);
                ul._clickHandler = null;
            }
        }
    },

    populateFields(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        const prefix = this.config.fieldPrefix || 'signup_';

        Object.keys(data).forEach(key => {
            // Skip user_id - handled separately
            if (key === 'user_id') {
                return;
            }

            // Skip standard fields that are handled below
            if (['firstname', 'lastname', 'email', 'phone'].includes(key)) {
                return;
            }

            // Try to find field with prefix first (frontend), then without (admin)
            // Handle different element types: input, select, textarea
            // Also handle multi-select fields with [] in name (Custom Fields)
            // Note: Custom Fields multi-select fields use name="field_name[]" (no prefix)
            let field = null;
            
            // Build list of possible selectors to try
            const selectors = [];
            
            // First try with prefix (for standard fields like signup_firstname)
            if (prefix) {
                selectors.push(
                    `input[name="${prefix}${key}"]`,
                    `select[name="${prefix}${key}"]`,
                    `textarea[name="${prefix}${key}"]`,
                    `select[name="${prefix}${key}[]"]`  // Multi-select with prefix
                );
            }
            
            // Then try without prefix (for Custom Fields and admin fields)
            selectors.push(
                `input[name="${key}"]`,
                `select[name="${key}"]`,
                `textarea[name="${key}"]`,
                `select[name="${key}[]"]`  // Multi-select without prefix (Custom Fields)
            );
            
            // Try each selector until we find a match
            for (const selector of selectors) {
                field = document.querySelector(selector);
                if (field) {
                    break;
                }
            }

            if (field) {
                const value = data[key];
                const fieldType = field.tagName.toLowerCase();
                const isMultiSelect = field.multiple || field.name.indexOf('[]') !== -1;
                
                if (fieldType === 'select' && isMultiSelect) {
                    // Handle multi-select (Select2)
                    // Value should be an array or comma-separated string
                    let values = [];
                    if (Array.isArray(value)) {
                        values = value.map(v => String(v).trim()).filter(v => v);
                    } else if (value) {
                        values = String(value).split(',').map(v => v.trim()).filter(v => v);
                    }
                    
                    // Use jQuery/Select2 method if available (more reliable for Select2)
                    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                        const $field = jQuery(field);
                        const setSelect2Values = () => {
                            // First, ensure Select2 is initialized
                            if (!$field.data('select2')) {
                                // Select2 not initialized yet, initialize it first
                                $field.select2();
                            }
                            
                            // Set values using Select2's val() method
                            // This properly handles multi-select and updates the UI
                            $field.val(values);
                            
                            // Trigger change to ensure Select2 updates and any change handlers fire
                            $field.trigger('change');
                        };
                        
                        // Try to set values immediately
                        setSelect2Values();
                        
                        // If Select2 wasn't initialized, try again after a short delay
                        // This handles cases where fields are populated before Select2 is initialized
                        if (!$field.data('select2')) {
                            setTimeout(() => {
                                setSelect2Values();
                            }, 100);
                        }
                    } else {
                        // Fallback: manually set selections on native select
                        Array.from(field.options).forEach(option => {
                            const optionValue = String(option.value).trim();
                            option.selected = values.includes(optionValue);
                        });
                        // Trigger change event
                        if (field.dispatchEvent) {
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                } else if (fieldType === 'select') {
                    // Handle single select
                    field.value = this.htmlDecode(value || '');
                    // Trigger change to update Select2 if present
                    if (typeof jQuery !== 'undefined' && jQuery(field).data('select2')) {
                        jQuery(field).trigger('change');
                    } else if (field.dispatchEvent) {
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } else if (fieldType === 'textarea') {
                    // Handle textarea
                    field.value = this.htmlDecode(value || '');
                } else if (fieldType === 'input' && field.type === 'checkbox') {
                    // Handle checkbox
                    field.checked = value == 1 || value === true || value === '1';
                } else if (fieldType === 'input' && field.type === 'radio') {
                    // Handle radio buttons - find the one with matching value
                    const radioGroup = document.querySelectorAll(`input[type="radio"][name="${field.name}"]`);
                    radioGroup.forEach(radio => {
                        radio.checked = radio.value === String(value);
                    });
                } else {
                    // Handle regular input (text, email, etc.)
                    field.value = this.htmlDecode(value || '');
                }
                
                // Handle Quill editor fields (Custom Fields HTML fields)
                // Quill fields have a hidden input with data-quill-field="true"
                if (fieldType === 'input' && field.hasAttribute('data-quill-field')) {
                    // For HTML fields, use raw value (don't decode - it's already HTML)
                    // The value from the server should already be properly formatted HTML
                    const htmlContent = value || '';
                    
                    // First, set the value in the hidden input (raw HTML, no decoding)
                    field.value = htmlContent;
                    
                    const containerId = field.id + '-quill-container';
                    const container = document.getElementById(containerId);
                    if (container && typeof Quill !== 'undefined') {
                        // Try to get Quill instance (might be stored in jQuery data or as property)
                        let quill = null;
                        if (typeof jQuery !== 'undefined') {
                            quill = jQuery(container).data('quill-instance');
                        }
                        if (!quill && container.quillInstance) {
                            quill = container.quillInstance;
                        }
                        
                        if (quill && quill.root) {
                            // Quill is already initialized, set content (raw HTML, no decoding)
                            if (htmlContent) {
                                const delta = quill.clipboard.convert({ html: htmlContent });
                                quill.setContents(delta, 'silent');
                            } else {
                                quill.setText('');
                            }
                        } else {
                            // Quill not initialized yet - initialize it now
                            // This matches the initialization in Custom Fields admin/public JS
                            quill = new Quill(container, {
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
                            if (htmlContent) {
                                const delta = quill.clipboard.convert({ html: htmlContent });
                                quill.setContents(delta, 'silent');
                            }
                            
                            // Store Quill instance
                            if (typeof jQuery !== 'undefined') {
                                jQuery(container).data('quill-instance', quill);
                            } else {
                                container.quillInstance = quill;
                            }
                            
                            // Sync Quill content to hidden input on text change
                            quill.on('text-change', function() {
                                const html = quill.root.innerHTML;
                                // Only update if content actually changed (avoid infinite loops)
                                if (field.value !== html) {
                                    field.value = html;
                                }
                            });
                        }
                    }
                }
            }
        });

        // Handle standard fields (firstname, lastname, email, phone)
        Object.keys(data).forEach(key => {
            if (!['firstname', 'lastname', 'email', 'phone'].includes(key)) {
                return;
            }

            // Try with prefix first (frontend), then without (admin)
            let input = document.querySelector(`input[name="${prefix}${key}"]`) ||
                document.querySelector(`input[name="${key}"]`);

            if (input) {
                input.value = this.htmlDecode(data[key] || '');
            }
        });

        // Handle email validation field (frontend only)
        if (prefix === 'signup_') {
            const validateEmail = document.querySelector('input[name="signup_validate_email"]');
            if (validateEmail) {
                validateEmail.value = this.htmlDecode(data.email || '');
            }
            
            // Set signup_user_id hidden field (public side) when user is selected from autocomplete
            const signupUserIdField = document.querySelector('input[name="signup_user_id"]');
            if (signupUserIdField && data.user_id) {
                const user_id = parseInt(data.user_id || 0, 10);
                signupUserIdField.value = user_id > 0 ? user_id : '';
            }
        }

        // Update user_id dropdown if configured (admin only)
        if (this.config.updateUserDropdown) {
            const userDropdown = document.querySelector('select[name="user_id"]');
            if (userDropdown) {
                // Get user_id from data, default to 0 if not present or invalid
                const user_id = parseInt(data.user_id || 0, 10);

                // Only update dropdown if we have a valid user_id (> 0)
                if (user_id > 0) {
                    // Check if this user_id exists in the dropdown
                    const option = userDropdown.querySelector(`option[value="${user_id}"]`);
                    if (option) {
                        // Valid option exists, set it
                        userDropdown.value = user_id;
                    } else {
                        // User ID doesn't exist in dropdown, set to "None"
                        userDropdown.value = '0';
                    }
                } else {
                    // No user_id or user_id is 0, explicitly set to "None"
                    userDropdown.value = '0';
                }

                // DO NOT trigger change event - we already have all the data from signup
                // The change event would overwrite with user data, which we don't want
            }
        }
    }
};