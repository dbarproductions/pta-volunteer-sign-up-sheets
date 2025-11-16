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

            // Try with prefix first (frontend), then without (admin)
            let input = document.querySelector(`input[name="${prefix}${key}"]`) ||
                document.querySelector(`input[name="${key}"]`);

            if (input) {
                input.value = this.htmlDecode(data[key]);
            }
        });

        // Handle email validation field (frontend only)
        if (prefix === 'signup_') {
            const validateEmail = document.querySelector('input[name="signup_validate_email"]');
            if (validateEmail) {
                validateEmail.value = this.htmlDecode(data.email || '');
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