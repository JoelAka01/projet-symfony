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

    const title      = panel.querySelector('[data-progress-title]');
    const message    = panel.querySelector('[data-progress-message]');
    const elapsed    = panel.querySelector('[data-progress-elapsed]');
    const estimate   = panel.querySelector('[data-progress-estimate]');
    const pages      = panel.querySelector('[data-progress-pages]');
    const connection = panel.querySelector('[data-progress-connection]');
    const steps = {
        crawl: panel.querySelector('[data-progress-step="crawl"]'),
        ai:    panel.querySelector('[data-progress-step="ai"]'),
        ready: panel.querySelector('[data-progress-step="ready"]'),
    };

    // KPI cards outside the progress section
    const kpiPagesCrawled = document.querySelector('[data-kpi-pages-crawled]');
    const kpiPagesFailed  = document.querySelector('[data-kpi-pages-failed]');
    const kpiSeoScore     = document.querySelector('[data-kpi-seo-score]');
    const kpiAiStatus     = document.querySelector('[data-kpi-ai-status]');

    // Use server-anchored timestamps for elapsed time so it survives page reloads.
    // stepStartedAt is an absolute Date parsed from the server's ISO timestamp.
    let stepStartedAt = null;
    const rawTs = panel.dataset.stepStartedAt;
    if (rawTs) {
        const d = new Date(rawTs);
        if (!isNaN(d.getTime())) {
            stepStartedAt = d;
        }
    }

    let finished       = false;
    let es             = null;
    let pollTimer      = null;
    let esErrorCount   = 0;

    // ── Elapsed counter (anchored to server timestamp) ─────────────────────
    const computeElapsed = () => {
        if (!stepStartedAt) {
            return 0;
        }
        return Math.max(0, Math.floor((Date.now() - stepStartedAt.getTime()) / 1000));
    };

    const tickElapsed = () => {
        if (elapsed instanceof HTMLElement && !finished) {
            elapsed.textContent = formatElapsedTime(computeElapsed());
        }
    };

    // Start the 1-second ticker immediately with server-anchored time
    tickElapsed();
    window.setInterval(tickElapsed, 1000);

    // ── Step indicators ────────────────────────────────────────────────────
    const updateSteps = (phase) => {
        Object.values(steps).forEach((step) => step?.classList.remove('is-active', 'is-complete'));

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

    // ── Update a KPI card ──────────────────────────────────────────────────
    const updateKpi = (card, value) => {
        if (card instanceof HTMLElement) {
            const strong = card.querySelector('strong');
            if (strong) {
                strong.textContent = String(value);
            }
        }
    };

    // ── Apply a status payload to the DOM ──────────────────────────────────
    const applyStatus = (status) => {
        // Anchor elapsed timer to the server's step timestamp.
        if (status.step_started_at) {
            const d = new Date(status.step_started_at);
            if (!isNaN(d.getTime())) {
                stepStartedAt = d;
            }
        }

        tickElapsed();
        updateSteps(status.phase);

        if (title instanceof HTMLElement)    { title.textContent    = status.title;    }
        if (message instanceof HTMLElement)  { message.textContent  = status.message;  }
        if (estimate instanceof HTMLElement) { estimate.textContent = status.estimate; }
        if (pages instanceof HTMLElement) {
            pages.textContent = `${status.pages_crawled} / ${status.max_pages ?? 'configured limit'}`;
        }

        // Live-update KPI cards
        updateKpi(kpiPagesCrawled, status.pages_crawled);
        updateKpi(kpiPagesFailed, status.pages_failed ?? 0);
        if (status.seo_score != null) {
            updateKpi(kpiSeoScore, status.seo_score);
        }
        if (status.ai_status) {
            updateKpi(kpiAiStatus, status.ai_status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()));
        }

        if (status.terminal) {
            finished = true;
            panel.classList.add('is-finished');

            if (es) { es.close(); es = null; }
            if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }

            if (connection instanceof HTMLElement) {
                connection.textContent = status.successful
                    ? 'Analysis complete — loading the finished report…'
                    : 'Processing stopped — loading the recorded details…';
            }

            // Give the server 2 s to persist everything before reloading.
            window.setTimeout(() => window.location.reload(), 2000);
            return true;
        }
        return false;
    };

    // ── HTTP polling fallback ──────────────────────────────────────────────
    const schedulePoll = (delay = 3000) => {
        if (finished) { return; }
        if (pollTimer) { clearTimeout(pollTimer); }
        pollTimer = window.setTimeout(doPoll, delay);
    };

    const doPoll = async () => {
        if (finished) { return; }
        pollTimer = null;

        try {
            const response = await fetch(statusUrl, {
                headers: { Accept: 'application/json' },
                cache:   'no-store',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const status     = await response.json();
            const isTerminal = applyStatus(status);

            if (!isTerminal) {
                schedulePoll(3000);
            }
        } catch (error) {
            if (connection instanceof HTMLElement) {
                connection.textContent = 'Status connection interrupted — retrying automatically.';
            }
            schedulePoll(4000);
        }
    };

    // ── Mercure SSE connection ─────────────────────────────────────────────
    // data-event-source-url is generated by Twig's mercure() helper and is
    // already the complete signed EventSource URL (hub + topic + JWT).
    // Do NOT append the topic again — just open it as-is.
    const eventSourceUrl = panel.dataset.eventSourceUrl;

    if (eventSourceUrl && window.EventSource) {
        try {
            es = new EventSource(eventSourceUrl);

            es.onopen = () => {
                esErrorCount = 0;
                if (connection instanceof HTMLElement) {
                    connection.textContent = '⚡ Live updates connected via Mercure.';
                }
            };

            es.onmessage = (event) => {
                try {
                    esErrorCount = 0;
                    const status = JSON.parse(event.data);
                    if (connection instanceof HTMLElement) {
                        connection.textContent = '⚡ Live updates connected via Mercure.';
                    }
                    applyStatus(status);
                } catch (e) {
                    console.error('Failed to parse Mercure SSE payload', e);
                }
            };

            es.onerror = () => {
                esErrorCount++;
                // Let the browser auto-reconnect for the first few errors.
                // EventSource has built-in reconnection logic. Only fall back
                // to polling if multiple consecutive errors occur, which
                // indicates a real connection problem rather than a transient
                // hiccup during the long Claude analysis wait.
                if (esErrorCount >= 3) {
                    console.warn('Mercure connection failed repeatedly — falling back to HTTP polling.');
                    if (es) { es.close(); es = null; }
                    if (connection instanceof HTMLElement) {
                        connection.textContent = 'Live connection lost — polling for updates every 3 seconds.';
                    }
                    schedulePoll(500);
                } else {
                    // Also start polling as a parallel safety net. The poll will
                    // catch state changes even while SSE tries to reconnect.
                    if (!pollTimer) {
                        schedulePoll(3000);
                    }
                }
            };
        } catch (err) {
            console.error('Failed to open Mercure EventSource', err);
            schedulePoll(500);
        }
    } else {
        // No Mercure configured or EventSource unavailable — poll immediately.
        schedulePoll(500);
    }
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

// simulated checkout inputs format & limitations
const cardNumberInput = document.getElementById('checkout_cardNumber');
const expiryInput     = document.getElementById('checkout_expiry');
const cvcInput        = document.getElementById('checkout_cvc');

if (cardNumberInput) {
    cardNumberInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, ''); // strip non digits
        if (value.length > 16) {
            value = value.slice(0, 16); // limit to 16 digits
        }
        // format with space every 4 digits
        const formatted = value.match(/.{1,4}/g)?.join(' ') || '';
        e.target.value = formatted;
    });
}

if (expiryInput) {
    let lastValue = '';
    expiryInput.addEventListener('input', (e) => {
        let value = e.target.value;
        
        // if deleting, dont automatically add back the slash
        if (value.length < lastValue.length) {
            lastValue = value;
            return;
        }
        
        let clean = value.replace(/\D/g, '');
        if (clean.length > 4) {
            clean = clean.slice(0, 4);
        }
        
        if (clean.length > 2) {
            clean = clean.slice(0, 2) + '/' + clean.slice(2);
        } else if (clean.length === 2) {
            clean = clean + '/';
        }
        
        e.target.value = clean;
        lastValue = clean;
    });
}

if (cvcInput) {
    cvcInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, ''); // strip non digits
        if (value.length > 3) {
            value = value.slice(0, 3); // limit to 3 digits
        }
        e.target.value = value;
    });
}
