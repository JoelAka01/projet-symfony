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
