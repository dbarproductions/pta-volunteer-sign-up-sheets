window.ptaVolunteer = {
    config: {
        ajaxUrl: '',
        method: 'POST',
        extraData: {}
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
        const div = document.createElement('div');
        div.innerHTML = value;
        return div.textContent;
    },

    setupAutocomplete() {
        const inputs = document.querySelectorAll('input[name="signup_firstname"], input[name="signup_lastname"], input[name="signup_email"]');
        inputs.forEach(input => {
            input.addEventListener('input', async (e) => {
                const data = await this.fetchResults(e.target.value);
                this.renderAutocomplete(data, input);
            });
        });
    },

    async fetchResults(query) {
        const formData = new FormData();
        formData.append('q', query);
        formData.append('pta_pub_action', 'autocomplete_volunteer');
        formData.append('action', this.config.extraData.action);
        formData.append('security', this.config.extraData.security);

        console.log('Request URL:', this.config.ajaxUrl);
        console.log('Request Method:', this.config.method);
        console.log('Request Params:', Object.fromEntries(formData));

        const response = await fetch(this.config.ajaxUrl, {
            method: 'POST',
            body: formData
        });

        return await response.json();
    },

    renderAutocomplete(data, input) {
        console.log('Response data:', data);
        let ul = document.querySelector('.autocomplete-results');
        if (!ul) {
            ul = document.createElement('ul');
            ul.className = 'autocomplete-results';
            input.parentNode.appendChild(ul);
        }

        // If no data or invalid response, clear and hide results
        if (!data || !Array.isArray(data)) {
            ul.innerHTML = '';
            return;
        }

        ul.innerHTML = data.map(item => `
            <li>
                <a href="#" data-volunteer='${JSON.stringify(item)}'>
                    <strong>${this.htmlDecode(item.firstname)} ${this.htmlDecode(item.lastname)}</strong>
                    <br>
                    <small>${this.htmlDecode(item.email)}</small>
                </a>
            </li>
        `).join('');

        ul.addEventListener('click', (e) => {
            e.preventDefault();
            if (e.target.closest('a')) {
                const volunteerData = JSON.parse(e.target.closest('a').dataset.volunteer);
                this.populateFields(volunteerData);
                ul.remove();
            }
        });
    },

    populateFields(data) {
        Object.keys(data).forEach(key => {
            const input = document.querySelector(`input[name="signup_${key}"]`) ||
                document.querySelector(`input[name="${key}"]`);
            if (input) {
                input.value = this.htmlDecode(data[key]);
            }
        });

        const validateEmail = document.querySelector('input[name="signup_validate_email"]');
        if (validateEmail) {
            validateEmail.value = this.htmlDecode(data.email);
        }
    }
};