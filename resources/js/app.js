import './bootstrap';

document.querySelectorAll('[data-country-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-country-search]');
    const results = picker.querySelector('[data-country-results]');
    const selected = picker.querySelector('[data-country-selected]');
    const codesInput = picker.querySelector('[data-country-codes]');
    const namesInput = picker.querySelector('[data-country-names]');
    const error = picker.querySelector('[data-country-error]');
    const searchUrl = picker.dataset.searchUrl;
    const countryUrlTemplate = picker.dataset.countryUrlTemplate;
    let countries = JSON.parse(picker.dataset.countries || '[]');
    let debounce;
    let request;

    const showError = (message = '') => {
        error.textContent = message;
        error.classList.toggle('hidden', message === '');
    };

    const sync = () => {
        countries.sort((a, b) => a.name.localeCompare(b.name));
        codesInput.value = JSON.stringify(countries.map((country) => country.code));
        namesInput.value = JSON.stringify(Object.fromEntries(countries.map((country) => [country.code, country.name])));
        selected.replaceChildren();

        countries.forEach((country) => {
            const tag = document.createElement('span');
            tag.className = 'inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700';
            tag.append(`${country.name} (${country.code})`);

            if (!search.disabled) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'text-slate-400 hover:text-rose-600';
                remove.setAttribute('aria-label', `Remove ${country.name}`);
                remove.textContent = '×';
                remove.addEventListener('click', () => {
                    countries = countries.filter((item) => item.code !== country.code);
                    sync();
                });
                tag.append(remove);
            }

            selected.append(tag);
        });
    };

    const clearResults = () => {
        results.replaceChildren();
        results.classList.add('hidden');
    };

    const addCountry = async (placeId) => {
        clearResults();
        showError();
        try {
            const response = await fetch(countryUrlTemplate.replace('__PLACE_ID__', encodeURIComponent(placeId)), {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Country details could not be loaded.');

            const country = payload.data;
            if (!countries.some((item) => item.code === country.code)) {
                countries.push(country);
                sync();
            }
            search.value = '';
        } catch (exception) {
            showError(exception.message || 'Country details could not be loaded.');
        }
    };

    const renderResults = (items) => {
        clearResults();
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'block w-full px-3.5 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50';
            button.textContent = item.name;
            button.addEventListener('click', () => addCountry(item.place_id));
            results.append(button);
        });
        results.classList.toggle('hidden', items.length === 0);
    };

    search.addEventListener('input', () => {
        clearTimeout(debounce);
        const query = search.value.trim();
        if (query.length < 2) return clearResults();

        debounce = setTimeout(async () => {
            request?.abort();
            request = new AbortController();
            showError();
            try {
                const response = await fetch(`${searchUrl}?query=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: request.signal,
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Country search is unavailable.');
                renderResults(payload.data || []);
            } catch (exception) {
                if (exception.name !== 'AbortError') showError(exception.message || 'Country search is unavailable.');
            }
        }, 250);
    });

    sync();
});

document.querySelectorAll('[data-service-area-master]').forEach((master) => {
    const form = master.closest('form');
    const scope = form.querySelector('[data-service-area-scope]');
    const scopeNotice = form.querySelector('[data-service-area-disabled-notice]');
    const status = form.querySelector('[data-service-area-master-status]');
    const summary = form.querySelector('[data-service-area-master-summary]');
    const requiredToggle = form.querySelector('[data-required-location-toggle]');
    const requiredScope = form.querySelector('[data-required-location-scope]');
    const requiredNotice = form.querySelector('[data-required-location-disabled-notice]');
    const matchType = form.querySelector('[data-required-location-match-type]');
    const radiusScope = form.querySelector('[data-required-location-radius-scope]');

    const setScopeState = (element, disabled) => {
        if (!element) return;
        element.inert = disabled;
        element.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        element.classList.toggle('opacity-50', disabled);
    };

    const sync = () => {
        const enforced = master.checked;
        const requiredEnabled = enforced && requiredToggle?.checked;
        const radiusEnabled = requiredEnabled && matchType?.value === 'radius';

        status.textContent = enforced ? 'Enabled' : 'Disabled';
        summary.textContent = enforced
            ? 'Country and required-location rules are checked for every new quote.'
            : 'This master switch bypasses all country and required-location checks for new quotes.';

        setScopeState(scope, !enforced);
        scopeNotice.classList.toggle('hidden', enforced);

        setScopeState(requiredScope, !requiredEnabled);
        requiredNotice.classList.toggle('hidden', requiredEnabled);

        setScopeState(radiusScope, !radiusEnabled);
    };

    master.addEventListener('change', sync);
    requiredToggle?.addEventListener('change', sync);
    matchType?.addEventListener('change', sync);
    sync();
});
