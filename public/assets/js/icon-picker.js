export function bindIconPicker(picker) {
    const field = picker.querySelector('[name="icon"]');
    const preview = picker.querySelector('[data-icon-preview]');
    const toggle = picker.querySelector('[data-icon-open]');
    const panel = picker.querySelector('#scope-icon-options');
    const search = picker.querySelector('#scope-icon-search');
    const status = picker.querySelector('[data-icon-status]');
    const results = picker.querySelector('[data-icon-results]');
    const more = picker.querySelector('[data-icon-more]');
    const pageSize = 30;
    let catalog;
    let limit = pageSize;

    function updatePreview() {
        const name = field.value;
        preview.className = /^[a-z0-9-]+$/.test(name) ? 'bi bi-' + name : 'bi bi-question-circle';
        field.setCustomValidity(catalog && !catalog.includes(name) ? 'Choose a valid Bootstrap icon.' : '');
    }

    function render() {
        if (!catalog) return;
        const terms = search.value.toLowerCase().trim().split(/[\s-]+/).filter(Boolean);
        const matches = catalog.filter(name => terms.every(term => name.includes(term)));
        const fragment = document.createDocumentFragment();
        for (const name of matches.slice(0, limit)) {
            const button = document.createElement('button');
            button.className = 'list-group-item list-group-item-action py-1 d-flex gap-2 align-items-center';
            button.type = 'button';
            button.dataset.iconName = name;
            button.setAttribute('aria-pressed', String(field.value === name));
            const icon = document.createElement('i');
            icon.className = 'bi bi-' + name;
            icon.setAttribute('aria-hidden', 'true');
            button.append(icon, name);
            fragment.append(button);
        }
        results.replaceChildren(fragment);
        status.textContent = matches.length === 0 ? 'No matching icons.' : `Showing ${Math.min(limit, matches.length)} of ${matches.length}`;
        more.hidden = matches.length <= limit;
    }

    function close() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    }

    field.addEventListener('input', updatePreview);
    toggle.addEventListener('click', async () => {
        if (!panel.hidden) {
            close();
            return;
        }
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        search.focus();
        if (!catalog) {
            status.textContent = 'Loading icons…';
            try {
                const response = await fetch('/assets/vendor/bootstrap-icons/bootstrap-icons.json');
                if (!response.ok) throw new Error('Icon catalog unavailable');
                catalog = Object.keys(await response.json()).sort();
                updatePreview();
            } catch {
                status.textContent = 'Could not load icons. Enter an icon name above or reopen the list to retry.';
                return;
            }
        }
        render();
    });
    search.addEventListener('input', () => { limit = pageSize; render(); });
    search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    panel.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        event.stopPropagation();
        close();
        toggle.focus();
    });
    more.addEventListener('click', () => {
        const firstNew = limit;
        limit += pageSize;
        render();
        results.children[firstNew]?.focus();
    });
    results.addEventListener('click', event => {
        const button = event.target.closest('[data-icon-name]');
        if (!button) return;
        field.value = button.dataset.iconName;
        updatePreview();
        close();
        toggle.focus();
    });
    return {
        reset() {
            close();
            search.value = '';
            limit = pageSize;
            updatePreview();
        },
    };
}
