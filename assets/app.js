import './styles/app.css';

document.querySelectorAll('[data-analysis-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const loading = form.querySelector('[data-analysis-loading]');
        const submit = form.querySelector('[data-analysis-submit]');

        if (loading instanceof HTMLElement) {
            loading.hidden = false;
        }

        if (submit instanceof HTMLButtonElement) {
            submit.disabled = true;
            submit.textContent = 'Analyzing...';
        }
    });
});

document.querySelectorAll('[data-cms-connection-form]').forEach((form) => {
    const select = form.querySelector('[data-cms-provider-select]');
    if (!(select instanceof HTMLSelectElement)) {
        return;
    }

    const refreshFields = () => {
        form.querySelectorAll('[data-cms-provider-field]').forEach((row) => {
            if (!(row instanceof HTMLElement)) {
                return;
            }

            row.hidden = row.dataset.cmsProviderField !== select.value;
        });

        form.querySelectorAll('[data-cms-provider-guide]').forEach((guide) => {
            if (!(guide instanceof HTMLElement)) {
                return;
            }

            guide.hidden = guide.dataset.cmsProviderGuide !== select.value;
        });
    };

    select.addEventListener('change', refreshFields);
    refreshFields();
});

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm ?? 'Continue?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-cms-publish-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const mode = form.querySelector('select[name$="[mode]"]');
        if (mode instanceof HTMLSelectElement && mode.value === 'publish' && !window.confirm('Publish these reviewed changes to the live website now?')) {
            event.preventDefault();
        }
    });
});

// Profile dropdown toggle and click outside close handlers
document.querySelectorAll('.profile-dropdown-container').forEach((container) => {
    const trigger = container.querySelector('.profile-trigger');
    if (trigger) {
        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            container.classList.toggle('is-active');
            const expanded = container.classList.contains('is-active');
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }
});

document.addEventListener('click', () => {
    document.querySelectorAll('.profile-dropdown-container.is-active').forEach((container) => {
        container.classList.remove('is-active');
        const trigger = container.querySelector('.profile-trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
});

