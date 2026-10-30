const bank = document.querySelector('[data-question-bank]');
if (bank) {
    const panel = bank.querySelector('[data-qb-preview-panel]'), content = bank.querySelector('[data-qb-preview-content]'), bulkForm = bank.querySelector('#question-bulk');
    let previousTrigger = bank.querySelector('[data-qb-preview]'), request;
    bank.addEventListener('error', event => {
        const audio = event.target;
        if (!(audio instanceof HTMLAudioElement) || audio.dataset.errorShown) return;
        audio.dataset.errorShown = '1';
        const message = document.createElement('p'); message.className = 'qb-incomplete'; message.setAttribute('role', 'status');
        message.textContent = 'Không thể phát audio. Kiểm tra tệp trong thư viện tài nguyên.'; audio.after(message);
    }, true);
    const pauseAudio = () => content.querySelectorAll('audio').forEach(audio => audio.pause());
    async function showPreview(id, trigger) {
        request?.abort(); request = new AbortController(); const current = request;
        pauseAudio(); panel.hidden = false; bank.classList.remove('qb-preview-closed');
        bank.querySelectorAll('[data-qb-row]').forEach(row => row.classList.toggle('is-active', row.dataset.qbRow === id));
        bank.querySelectorAll('[data-qb-preview]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.qbPreview === id)));
        previousTrigger = trigger; content.setAttribute('aria-busy', 'true');
        const template = [...bank.querySelectorAll('[data-qb-preview-template]')].find(el => el.dataset.qbPreviewTemplate === id);
        try {
            if (template) content.replaceChildren(template.content.cloneNode(true));
            else {
                content.textContent = 'Đang tải bài và câu hỏi…';
                const source = [...bank.querySelectorAll('[data-qb-preview-url]')].find(el => el.dataset.qbPreviewUrl === id);
                const url = new URL(source.dataset.url, location.origin);
                new URLSearchParams(location.search).forEach((value, name) => url.searchParams.set(name, value));
                const response = await fetch(url, { signal: current.signal, headers: { Accept: 'text/html' } });
                if (!response.ok || response.redirected) throw new Error('Không thể tải nội dung. Hãy tải lại trang hoặc thử lại.');
                const html = await response.text(); if (request !== current) return; content.innerHTML = html;
            }
            if (matchMedia('(max-width: 1050px)').matches) { panel.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' }); panel.focus({ preventScroll: true }); }
        } catch (error) { if (error.name !== 'AbortError') content.textContent = error.message; }
        finally { if (request === current) content.removeAttribute('aria-busy'); }
    }
    bank.addEventListener('click', event => {
        const trigger = event.target.closest('[data-qb-preview]'); if (trigger) return void showPreview(trigger.dataset.qbPreview, trigger);
        const row = event.target.closest('[data-qb-row]'); if (row && !event.target.closest('input, a, button, select')) showPreview(row.dataset.qbRow, row.querySelector('[data-qb-preview]'));
    });
    const close = () => { request?.abort(); pauseAudio(); panel.hidden = true; bank.classList.add('qb-preview-closed'); bank.querySelectorAll('[data-qb-row]').forEach(row => row.classList.remove('is-active')); bank.querySelectorAll('[data-qb-preview]').forEach(button => button.setAttribute('aria-pressed', 'false')); previousTrigger?.focus({ preventScroll: true }); };
    bank.querySelector('[data-qb-close-preview]').addEventListener('click', close);
    panel.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    bank.querySelectorAll('[data-qb-preview]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.qbPreview === bank.querySelector('[data-qb-row]')?.dataset.qbRow)));
    function syncSelection() {
        bank.querySelectorAll('[data-qb-select-entry]').forEach(control => { const ids = JSON.parse(control.dataset.questionIds); const checked = ids.filter(id => bulkForm.querySelector(`input[name="ids[]"][value="${id}"]`)?.checked).length; control.checked = checked === ids.length && ids.length > 0; control.indeterminate = checked > 0 && checked < ids.length; });
        bulkForm.hidden = !bulkForm.querySelector('input[name="ids[]"]:checked, input[name="matching_ids[]"]');
    }
    bank.addEventListener('change', event => {
        const sort = event.target.closest('[data-qb-sort]');
        const pageSize = event.target.closest('[data-qb-per-page]');
        if (sort) return void navigateWith('sort', sort.value);
        if (pageSize) return void navigateWith('per_page', pageSize.value);
        const control = event.target.closest('[data-qb-select-entry]');
        if (control) { const selected = control.checked; JSON.parse(control.dataset.questionIds).forEach(id => { const input = bulkForm.querySelector(`input[name="ids[]"][value="${id}"]`); if (input) { input.checked = selected; input.dispatchEvent(new Event('change', { bubbles: true })); } }); }
        syncSelection();
    });
    syncSelection();
    const navigateWith = (name, value) => { const form = bank.querySelector('[data-question-filters]'); form.querySelector(`[name="${name}"]`).value = value; form.querySelector('[name="q"]').value = bank.querySelector('[data-question-search] [name="q"]').value; form.requestSubmit(); };
    bank.querySelector('[name="missing"]').addEventListener('change', () => bank.querySelector('[data-question-filters]').requestSubmit());

    document.addEventListener('vistiq:ajax-results-updated', event => {
        if (event.detail?.selector !== '#qb-results') return;
        request?.abort();
        pauseAudio();
        panel.hidden = true;
        bank.classList.add('qb-preview-closed');
        bank.querySelectorAll('[data-qb-row]').forEach(row => row.classList.remove('is-active'));
        bank.querySelectorAll('[data-qb-preview]').forEach(button => button.setAttribute('aria-pressed', 'false'));
        syncSelection();
    });
}

const thptQuickForm = document.querySelector('.qb-quick-form');
if (thptQuickForm) {
    const family = thptQuickForm.querySelector('[data-thpt-create-family]');
    const part = thptQuickForm.querySelector('[data-thpt-create-part]');
    const group = thptQuickForm.querySelector('[data-thpt-create-group]');
    const syncGroups = () => {
        [...group.options].forEach(option => {
            if (option.value) option.hidden = option.dataset.family !== family.value;
        });
        if (group.selectedOptions[0]?.hidden) group.value = '';
        part.value = group.value ? group.selectedOptions[0].dataset.part : family.selectedOptions[0]?.dataset.part ?? part.value;
    };
    family.addEventListener('change', syncGroups);
    group.addEventListener('change', () => {
        if (group.value) family.value = group.selectedOptions[0].dataset.family;
        syncGroups();
    });
    syncGroups();
}
const updateSubjectiveSelection = () => {
    const root = document.querySelector('[data-subjective-bank]');
    if (!root) return;
    const boxes = [...root.querySelectorAll('[data-subjective-select]')];
    const count = boxes.filter(box => box.checked).length;
    const all = root.querySelector('[data-subjective-select-all]');
    if (all) {
        all.checked = count > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
        all.disabled = boxes.length === 0;
    }
    const label = root.querySelector('[data-subjective-selection]');
    if (label) label.textContent = count ? `Đã chọn ${count} câu hỏi nháp trên trang này.` : 'Chọn câu hỏi nháp để gửi duyệt.';
    const submit = root.querySelector('[data-subjective-review-submit]');
    if (submit) { submit.disabled = count === 0; submit.textContent = count ? `Gửi duyệt ${count} câu` : 'Gửi duyệt mục đã chọn'; }
};
document.addEventListener('change', event => {
    if (event.target.matches('[data-subjective-select-all]')) document.querySelectorAll('[data-subjective-select]').forEach(box => { box.checked = event.target.checked; });
    if (event.target.matches('[data-subjective-select-all], [data-subjective-select]')) updateSubjectiveSelection();
});
document.addEventListener('vistiq:ajax-results-updated', updateSubjectiveSelection);
updateSubjectiveSelection();
const subjectiveFilters = document.querySelector('[data-subjective-filters]');
if (subjectiveFilters) {
    const phone = matchMedia('(max-width: 680px)');
    const applied = ['exam', 'part', 'difficulty'].some(name => subjectiveFilters.querySelector(`[name="${name}"]`)?.value);
    subjectiveFilters.open = !phone.matches || applied;
    phone.addEventListener('change', () => { subjectiveFilters.open = !phone.matches; });
}
const subjectiveDetail = document.querySelector('[data-subjective-detail]');
if (subjectiveDetail && matchMedia('(max-width: 680px)').matches) {
    subjectiveDetail.scrollIntoView({ block: 'start', behavior: 'instant' });
    subjectiveDetail.focus({ preventScroll: true });
}
