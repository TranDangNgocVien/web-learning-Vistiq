const activeRequests = new Map();
const searchTimers = new WeakMap();

function selectorParts(selector) {
    return selector?.split(',').map(part => part.trim()).filter(Boolean) ?? [];
}

function findTargets(selector) {
    try {
        return selectorParts(selector).map(part => ({ selector: part, element: document.querySelector(part) }));
    } catch {
        return [];
    }
}

function formForTarget(selector) {
    return [...document.querySelectorAll('form[data-ajax-filter-form][data-ajax-target]')]
        .find(form => form.dataset.ajaxTarget === selector || selectorParts(form.dataset.ajaxTarget).includes(selector)) ?? null;
}

function statusFor(form) {
    let status = form.nextElementSibling?.matches('[data-ajax-filter-status]') ? form.nextElementSibling : null;
    if (!status) {
        status = document.createElement('p');
        status.className = 'ajax-filter-status';
        status.dataset.ajaxFilterStatus = '';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.setAttribute('aria-atomic', 'true');
        form.insertAdjacentElement('afterend', status);
    }

    return status;
}

function formUrl(form, submitter = null) {
    const url = new URL(form.action || window.location.href, window.location.href);
    const names = new Set([...form.elements].map(control => control.name).filter(Boolean));
    names.add('page');
    names.forEach(name => url.searchParams.delete(name));

    const data = new FormData(form);
    if (submitter instanceof HTMLElement && submitter.form === form && submitter.name && !submitter.disabled) {
        data.set(submitter.name, submitter.value);
    }

    for (const [name, value] of data.entries()) {
        if (typeof value === 'string' && value !== '') url.searchParams.append(name, value);
    }

    return url;
}

function syncForm(form, url) {
    for (const control of form.elements) {
        if (!control.name) continue;
        const values = url.searchParams.getAll(control.name);
        if (control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) {
            control.checked = values.includes(control.value || 'on');
        } else if (control instanceof HTMLSelectElement && control.multiple) {
            [...control.options].forEach(option => { option.selected = values.includes(option.value); });
        } else if ('value' in control) {
            control.value = values.at(-1) ?? '';
        }
    }
}

function copyFormValues(form, source) {
    const data = new FormData(source);
    for (const control of form.elements) {
        if (!control.name) continue;
        const values = data.getAll(control.name).filter(value => typeof value === 'string');
        if (control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) {
            control.checked = values.includes(control.value || 'on');
        } else if (control instanceof HTMLSelectElement && control.multiple) {
            [...control.options].forEach(option => { option.selected = values.includes(option.value); });
        } else if ('value' in control) {
            control.value = values.at(-1) ?? '';
        }
    }
}

async function updateResults({ selector, url, form = null, historyMode = 'push' }) {
    const targets = findTargets(selector);
    if (!targets.length || targets.some(target => !target.element)) {
        window.location.assign(url.href);
        return;
    }

    activeRequests.get(selector)?.abort();
    const controller = new AbortController();
    activeRequests.set(selector, controller);
    const status = form ? statusFor(form) : null;

    if (form) {
        form.dataset.ajaxState = 'loading';
        form.setAttribute('aria-busy', 'true');
        status.textContent = 'Đang cập nhật kết quả…';
    }
    targets.forEach(target => target.element.setAttribute('aria-busy', 'true'));

    try {
        const response = await fetch(url, {
            signal: controller.signal,
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (response.redirected) {
            window.location.assign(response.url);
            return;
        }
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const html = await response.text();
        if (activeRequests.get(selector) !== controller) return;
        const parsedDocument = new DOMParser().parseFromString(html, 'text/html');
        const replacements = targets.map(target => ({
            selector: target.selector,
            current: target.element,
            replacement: parsedDocument.querySelector(target.selector),
        }));
        if (replacements.some(target => !target.replacement)) {
            window.location.assign(url.href);
            return;
        }

        const resolvedUrl = new URL(response.url || url.href, window.location.href);
        if (historyMode === 'push') window.history.pushState({}, '', resolvedUrl.href);
        if (historyMode === 'replace') window.history.replaceState({}, '', resolvedUrl.href);
        replacements.forEach(({ current, replacement }) => {
            current.replaceWith(replacement);
            replacement.removeAttribute('aria-busy');
        });
        const peerForms = [...document.querySelectorAll('form[data-ajax-filter-form][data-ajax-target]')]
            .filter(candidate => candidate.dataset.ajaxTarget === selector);
        if (form && !peerForms.includes(form)) peerForms.push(form);
        const sourceForm = [...parsedDocument.querySelectorAll('form[data-ajax-filter-form][data-ajax-target]')]
            .find(candidate => candidate.dataset.ajaxTarget === selector);
        if (sourceForm) peerForms.forEach(peer => copyFormValues(peer, sourceForm));
        else peerForms.forEach(peer => syncForm(peer, resolvedUrl));
        if (status) status.textContent = 'Kết quả đã được cập nhật.';
        document.dispatchEvent(new CustomEvent('vistiq:ajax-results-updated', {
            detail: {
                selector,
                url: resolvedUrl,
                previousTargets: replacements.map(target => target.current),
                targets: replacements.map(target => target.replacement),
                target: replacements[0].replacement,
            },
        }));
    } catch (error) {
        if (error.name !== 'AbortError' && form) {
            status.textContent = 'Không thể tải kết quả. Hãy thử lại hoặc kiểm tra kết nối.';
        }
    } finally {
        if (activeRequests.get(selector) === controller) {
            activeRequests.delete(selector);
            if (form) {
                delete form.dataset.ajaxState;
                form.removeAttribute('aria-busy');
            }
            findTargets(selector).forEach(target => target.element?.removeAttribute('aria-busy'));
        }
    }
}

function submitFilter(form, historyMode = 'push', submitter = null) {
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-ajax-filter-form]')) return;
    const selector = form.dataset.ajaxTarget;
    if (!selector) return;
    updateResults({ selector, url: formUrl(form, submitter), form, historyMode });
}

export function initializeAjaxFilters() {
    if (document.documentElement.dataset.ajaxFiltersInitialized === 'true') return;
    document.documentElement.dataset.ajaxFiltersInitialized = 'true';

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-ajax-filter-form]') || !form.dataset.ajaxTarget) return;
        event.preventDefault();
        clearTimeout(searchTimers.get(form));
        const historyMode = form.dataset.ajaxHistoryMode || 'push';
        delete form.dataset.ajaxHistoryMode;
        submitFilter(form, historyMode, event.submitter);
    });

    document.addEventListener('input', event => {
        const control = event.target;
        const form = control.form;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-ajax-filter-form][data-ajax-debounce]')) return;
        if (!(control instanceof HTMLInputElement) || !['search', 'text', 'email', 'number'].includes(control.type)) return;
        if (event.isComposing || control.disabled || control.readOnly) return;

        clearTimeout(searchTimers.get(form));
        const delay = Math.max(150, Number(form.dataset.ajaxDebounce) || 350);
        searchTimers.set(form, window.setTimeout(() => {
            const firstSearch = form.hasAttribute('data-ajax-history-first-push') && form.dataset.ajaxSearched !== 'true';
            form.dataset.ajaxHistoryMode = firstSearch ? 'push' : 'replace';
            form.dataset.ajaxSearched = 'true';
            form.requestSubmit();
        }, delay));
    });

    document.addEventListener('change', event => {
        const control = event.target;
        const form = control.form;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-ajax-filter-form][data-ajax-on-change]')) return;
        if (control instanceof HTMLInputElement && ['search', 'text', 'email', 'number'].includes(control.type)) return;
        form.dataset.ajaxHistoryMode = 'replace';
        form.requestSubmit();
    });

    document.addEventListener('click', event => {
        const reset = event.target.closest('[data-ajax-filter-reset]');
        if (reset) {
            const url = new URL(reset.href, window.location.href);
            if (url.origin !== window.location.origin) return;
            event.preventDefault();
            const selector = reset.dataset.ajaxFilterReset;
            updateResults({ selector, url, form: formForTarget(selector), historyMode: 'push' });
            return;
        }

        const pageLink = event.target.closest('[data-ajax-pagination] a[href]');
        if (!pageLink || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const results = pageLink.closest('[data-ajax-results]');
        if (!results?.id) return;
        const url = new URL(pageLink.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        event.preventDefault();
        const paginationTarget = `#${CSS.escape(results.id)}`;
        const form = formForTarget(paginationTarget);
        updateResults({ selector: form?.dataset.ajaxTarget ?? paginationTarget, url, form, historyMode: 'push' });
    });

    window.addEventListener('popstate', () => {
        const currentUrl = new URL(window.location.href);
        const updatedSelectors = new Set();
        const forms = [...document.querySelectorAll('form[data-ajax-filter-form][data-ajax-target]')];
        forms.forEach(form => syncForm(form, currentUrl));
        forms.forEach(form => {
            const selector = form.dataset.ajaxTarget;
            if (updatedSelectors.has(selector)) return;
            updatedSelectors.add(selector);
            if (findTargets(selector).some(target => !target.element)) return;
            updateResults({ selector, url: currentUrl, historyMode: 'none' });
        });
    });
}
