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

document.querySelectorAll('[data-vat-master]').forEach((master) => {
    const form = master.closest('form');
    const scope = form.querySelector('[data-vat-scope]');
    const notice = form.querySelector('[data-vat-disabled-notice]');
    const status = form.querySelector('[data-vat-master-status]');
    const summary = form.querySelector('[data-vat-master-summary]');

    const sync = () => {
        const enabled = master.checked;

        status.textContent = enabled ? 'Enabled' : 'Disabled';
        summary.textContent = enabled
            ? 'VAT is calculated for every new quote and booking.'
            : 'VAT is not calculated for new quotes or bookings.';

        scope.inert = !enabled;
        scope.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        scope.classList.toggle('opacity-50', !enabled);
        notice.classList.toggle('hidden', enabled);
    };

    master.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('[data-saved-location-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-saved-location-search]');
    const results = picker.querySelector('[data-saved-location-results]');
    const selectedBox = picker.querySelector('[data-saved-location-selected]');
    const inputs = picker.querySelector('[data-saved-location-inputs]');
    const status = picker.querySelector('[data-saved-location-status]');
    const inputName = picker.dataset.inputName;
    const multiple = picker.dataset.multiple === 'true';
    const searchUrl = picker.dataset.searchUrl;
    const disabled = search.disabled;
    const cache = new Map();
    let selected = new Map((JSON.parse(picker.dataset.selected || '[]')).map((location) => [Number(location.id), location]));
    let debounce;
    let request;
    let requestVersion = 0;

    const setStatus = (message = '', tone = 'slate') => {
        status.textContent = message;
        status.className = `mt-2 ${message ? '' : 'hidden '}text-xs font-medium ${tone === 'rose' ? 'text-rose-600' : 'text-slate-600'}`;
    };

    const close = () => {
        results.replaceChildren();
        results.classList.add('hidden');
        search.setAttribute('aria-expanded', 'false');
    };

    const draw = () => {
        selectedBox.replaceChildren();
        inputs.replaceChildren();

        selected.forEach((location, id) => {
            const chip = document.createElement('span');
            chip.className = `inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold ${location.is_active === false ? 'bg-amber-50 text-amber-800' : 'bg-indigo-50 text-indigo-800'}`;
            const text = document.createElement('span');
            text.className = 'truncate';
            text.textContent = location.is_active === false ? `${location.name} (inactive)` : location.name;
            chip.append(text);

            if (!disabled) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'text-base leading-none text-indigo-500 hover:text-rose-600';
                remove.setAttribute('aria-label', `Remove ${location.name}`);
                remove.textContent = '×';
                remove.addEventListener('click', () => {
                    selected.delete(id);
                    draw();
                });
                chip.append(remove);
            }

            selectedBox.append(chip);
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = multiple ? `${inputName}[]` : inputName;
            hidden.value = String(id);
            inputs.append(hidden);
        });
    };

    const select = (location) => {
        const id = Number(location.id);
        if (!multiple) selected = new Map();
        selected.set(id, location);
        search.value = '';
        setStatus();
        close();
        draw();
        search.focus();
    };

    const render = (locations) => {
        results.replaceChildren();
        const available = locations.filter((location) => !selected.has(Number(location.id)));
        if (available.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'px-3 py-2 text-sm text-slate-500';
            empty.textContent = 'No matching active saved locations.';
            results.append(empty);
        }

        available.forEach((location) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.role = 'option';
            button.className = 'block w-full rounded-lg px-3 py-2 text-left hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none';
            const name = document.createElement('span');
            name.className = 'block text-sm font-semibold text-slate-800';
            name.textContent = location.name;
            const address = document.createElement('span');
            address.className = 'block truncate text-xs text-slate-500';
            address.textContent = location.address;
            button.append(name, address);
            button.addEventListener('click', () => select(location));
            button.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close();
                    search.focus();
                }
            });
            results.append(button);
        });

        results.classList.remove('hidden');
        search.setAttribute('aria-expanded', 'true');
    };

    const lookup = async (query) => {
        const cached = cache.get(query.toLowerCase());
        if (cached && Date.now() - cached.createdAt < 30000) {
            render(cached.locations);
            return;
        }

        request?.abort();
        request = new AbortController();
        const version = ++requestVersion;
        setStatus('Searching saved locations…');

        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Saved-location search is unavailable.');
            if (version !== requestVersion) return;

            const locations = payload.data || [];
            cache.set(query.toLowerCase(), { createdAt: Date.now(), locations });
            setStatus();
            render(locations);
        } catch (exception) {
            if (exception.name !== 'AbortError') {
                setStatus(exception.message || 'Saved-location search is unavailable.', 'rose');
                close();
            }
        }
    };

    search.addEventListener('input', () => {
        clearTimeout(debounce);
        const query = search.value.trim();
        if (query.length < 2) {
            request?.abort();
            requestVersion += 1;
            setStatus(query.length === 1 ? 'Type one more character to search.' : '');
            close();
            return;
        }
        debounce = setTimeout(() => lookup(query), 300);
    });

    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'ArrowDown' && !results.classList.contains('hidden')) {
            event.preventDefault();
            results.querySelector('button')?.focus();
        } else if (event.key === 'Enter' && !results.classList.contains('hidden')) {
            const firstResult = results.querySelector('button');
            if (firstResult) {
                event.preventDefault();
                firstResult.click();
            }
        }
    });

    document.addEventListener('click', (event) => {
        if (!picker.contains(event.target)) close();
    });
    window.addEventListener('admin:location-created', () => cache.clear());

    draw();
});
