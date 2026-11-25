(() => {
    const storageKey = 'crewweaver-theme';
    const systemTheme = matchMedia('(prefers-color-scheme: dark)');
    const normalize = value => value === 'light' || value === 'dark' ? value : 'system';
    let preference = normalize(localStorage.getItem(storageKey));

    const currentTheme = () => preference === 'system' ? (systemTheme.matches ? 'dark' : 'light') : preference;

    function applyTheme() {
        const effective = currentTheme();
        document.documentElement.setAttribute('data-bs-theme', effective);
        const toggle = document.querySelector('[data-theme-toggle]');
        if (toggle) {
            const next = effective === 'dark' ? 'light' : 'dark';
            const label = 'Switch to ' + next + ' theme';
            toggle.setAttribute('aria-label', label);
            toggle.title = label;
            toggle.querySelector('i').className = next === 'dark' ? 'bi bi-moon' : 'bi bi-sun';
        }
        document.querySelectorAll('[data-theme-value]').forEach(button => {
            const selected = button.dataset.themeValue === preference;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
    }

    function selectTheme(value) {
        preference = normalize(value);
        localStorage.setItem(storageKey, preference);
        applyTheme();
    }

    applyTheme();
    window.addEventListener('DOMContentLoaded', () => {
        applyTheme();
        document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
            selectTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });
        document.querySelectorAll('[data-theme-value]').forEach(button => button.addEventListener('click', () => {
            selectTheme(button.dataset.themeValue);
        }));
    });
    systemTheme.addEventListener('change', () => {
        if (preference === 'system') applyTheme();
    });
    window.addEventListener('storage', event => {
        if (event.key !== storageKey) return;
        preference = normalize(event.newValue);
        applyTheme();
    });
})();
