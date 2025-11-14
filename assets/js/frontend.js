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
            ...userConfig
        };

        if (document.querySelector('input[name="signup_firstname"], input[name="signup_lastname"]')) {
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
        const inputs = document.querySelectorAll('input[name="signup_firstname"], input[name="signup_lastname"], input[name="signup_email"]');
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

            // Add click handler once (not on every render)
            ul.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const link = e.target.closest('a[data-volunteer]');
                if (link) {
                    try {
                        const volunteerData = JSON.parse(link.dataset.volunteer);
                        this.populateFields(volunteerData);
                        this.clearAutocomplete(input);
                    } catch (error) {
                        // Invalid JSON, ignore
                    }
                }
            });
        }

        // If no data or invalid response, clear and hide results
        if (!data || !Array.isArray(data) || data.length === 0) {
            ul.innerHTML = '';
            ul.style.display = 'none';
            return;
        }

        // Show and populate results
        ul.style.display = 'block';
        ul.innerHTML = data.map(item => {
            const firstname = this.htmlDecode(item.firstname || '');
            const lastname = this.htmlDecode(item.lastname || '');
            const email = this.htmlDecode(item.email || '');
            // Escape JSON for HTML data attribute
            // JSON.stringify already escapes quotes, so we just need to escape HTML entities
            // Must escape & first to avoid double-escaping
            const jsonData = JSON.stringify(item)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            return `
                <li>
                    <a href="#" data-volunteer="${jsonData}">
                        <strong>${firstname} ${lastname}</strong>
                        <br>
                        <small>${email}</small>
                    </a>
                </li>
            `;
        }).join('');
    },

    clearAutocomplete(input) {
        const ul = input.parentNode.querySelector('.autocomplete-results');
        if (ul) {
            ul.innerHTML = '';
            ul.style.display = 'none';
        }
    },

    populateFields(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        Object.keys(data).forEach(key => {
            const input = document.querySelector(`input[name="signup_${key}"]`) ||
                document.querySelector(`input[name="${key}"]`);
            if (input) {
                input.value = this.htmlDecode(data[key]);
            }
        });

        const validateEmail = document.querySelector('input[name="signup_validate_email"]');
        if (validateEmail) {
            validateEmail.value = this.htmlDecode(data.email || '');
        }
    }
};