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

const formatElapsedTime = (seconds) => {
    const safeSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
    const minutes = Math.floor(safeSeconds / 60);
    const remainingSeconds = safeSeconds % 60;

    if (minutes === 0) {
        return `${remainingSeconds}s`;
    }

    return `${minutes}m ${String(remainingSeconds).padStart(2, '0')}s`;
};

document.querySelectorAll('[data-audit-progress]').forEach((panel) => {
    if (!(panel instanceof HTMLElement)) {
        return;
    }

    const statusUrl = panel.dataset.statusUrl;
    if (!statusUrl) {
        return;
    }

    const title = panel.querySelector('[data-progress-title]');
    const message = panel.querySelector('[data-progress-message]');
    const elapsed = panel.querySelector('[data-progress-elapsed]');
    const estimate = panel.querySelector('[data-progress-estimate]');
    const pages = panel.querySelector('[data-progress-pages]');
    const connection = panel.querySelector('[data-progress-connection]');
    const steps = {
        crawl: panel.querySelector('[data-progress-step="crawl"]'),
        ai: panel.querySelector('[data-progress-step="ai"]'),
        ready: panel.querySelector('[data-progress-step="ready"]'),
    };
    let elapsedSeconds = 0;
    let finished = false;

    const updateElapsed = () => {
        if (elapsed instanceof HTMLElement) {
            elapsed.textContent = formatElapsedTime(elapsedSeconds);
        }

        if (!finished) {
            elapsedSeconds += 1;
        }
    };

    const updateSteps = (phase) => {
        Object.values(steps).forEach((step) => {
            step?.classList.remove('is-active', 'is-complete');
        });

        if (phase === 'crawl_queued' || phase === 'crawling') {
            steps.crawl?.classList.add('is-active');
            return;
        }

        steps.crawl?.classList.add('is-complete');
        if (phase === 'ai_queued' || phase === 'analyzing') {
            steps.ai?.classList.add('is-active');
            return;
        }

        steps.ai?.classList.add('is-complete');
        steps.ready?.classList.add(phase === 'completed' ? 'is-complete' : 'is-active');
    };

    const poll = async () => {
        try {
            const response = await fetch(statusUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) {
                throw new Error(`Status request failed with HTTP ${response.status}`);
            }

            const status = await response.json();
            elapsedSeconds = Number(status.elapsed_seconds) || 0;
            updateElapsed();
            updateSteps(status.phase);

            if (title instanceof HTMLElement) {
                title.textContent = status.title;
            }
            if (message instanceof HTMLElement) {
                message.textContent = status.message;
            }
            if (estimate instanceof HTMLElement) {
                estimate.textContent = status.estimate;
            }
            if (pages instanceof HTMLElement) {
                pages.textContent = `${status.pages_crawled} / ${status.max_pages ?? 'configured limit'}`;
            }
            if (connection instanceof HTMLElement) {
                connection.textContent = 'Status updated automatically. You can leave and return; processing continues in the background.';
            }

            if (status.terminal) {
                finished = true;
                panel.classList.add('is-finished');
                if (connection instanceof HTMLElement) {
                    connection.textContent = status.successful
                        ? 'Analysis complete. Loading the finished report...'
                        : 'Processing stopped. Loading the recorded details...';
                }
                window.setTimeout(() => window.location.reload(), 900);
                return;
            }
        } catch (error) {
            if (connection instanceof HTMLElement) {
                connection.textContent = 'Status connection interrupted. Retrying automatically; the background analysis continues.';
            }
        }

        window.setTimeout(poll, 3000);
    };

    window.setInterval(updateElapsed, 1000);
    poll();
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
