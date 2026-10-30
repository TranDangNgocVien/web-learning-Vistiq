import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

export function initQuestionEditor(root) {
    if (!root || root._qeController) return root?._qeController;
    const lifecycle = new AbortController();
    root.addEventListener('error', event => {
        const audio = event.target;
        if (!(audio instanceof HTMLAudioElement) || audio.dataset.errorShown) return;
        audio.dataset.errorShown = '1'; const message = document.createElement('p'); message.className = 'qe-help';
        message.textContent = 'Không thể phát audio. Kiểm tra tệp trong thư viện tài nguyên.'; message.setAttribute('role', 'status'); audio.after(message);
    }, true);
    const richEditors = [];
    const embedded = root.querySelector('[data-qe-form]').dataset.embedded === '1';
    const form = root.querySelector('[data-qe-form]');
    const catalog = JSON.parse(form.querySelector('[data-qe-catalog]').textContent);
    const initial = JSON.parse(root.querySelector('[data-qe-initial]').textContent);
    const canEdit = form.dataset.canEdit === '1';
    const csrf = form.querySelector('[name="_token"]').value;
    const feedback = root.querySelector('[data-qe-feedback]');
    const saveStatus = root.querySelector('[data-qe-save-status]');
    const exam = form.querySelector('[data-qe-exam]');
    const part = form.querySelector('[data-qe-part]');
    const type = form.querySelector('[data-qe-type]');
    const list = form.querySelector('[data-qe-options]');
    const answerSelect = form.querySelector('[data-qe-correct-select]');
    const thptQuestionNote = root.querySelector('[data-thpt-question-note]');
    const dialog = root.querySelector('[data-qe-dialog]');
    const pickerResults = root.querySelector('[data-qe-picker-results]');
    const pickerFeedback = root.querySelector('[data-qe-picker-feedback]');
    let dirty = false, saving = null, autosavePaused = false, saveTimer, previewTimer, previewRequest, pickerRequest, pickerTimer, pickerKind;
    let historyPage = initial.history.next_page;
    let previousPartCode = part.selectedOptions[0]?.dataset.partCode;
    let tags = form.querySelector('[data-qe-tags-value]').value.split(',').map(tag => tag.trim()).filter(Boolean);
    const errorText = result => Object.values(result.errors ?? {}).flat().join('\n') || result.message || 'Không thể xử lý. Hãy thử lại.';
    const showError = message => { feedback.textContent = message; feedback.hidden = false; };
    const api = async (url, options = {}) => {
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers } });
        const result = await response.json().catch(() => ({ message: 'Phiên đăng nhập đã hết hoặc máy chủ không phản hồi. Hãy tải lại trang.' }));
        if (!response.ok) { const error = new Error(errorText(result)); error.status = response.status; throw error; }
        return result;
    };
    const element = (tag, className, text) => { const el = document.createElement(tag); if (className) el.className = className; if (text !== undefined) el.textContent = text; return el; };
    const family = () => catalog.families[type.selectedOptions[0]?.dataset.code] ?? 'text';
    const typeCode = () => type.selectedOptions[0]?.dataset.code;
    const rows = () => [...list.querySelectorAll('[data-qe-option]')];
    const originalOptions = [...form.querySelectorAll('[data-qe-original-option]')].map(input => ({ input, text: input.value, html: input.dataset.qeOriginalOption }));
    form.addEventListener('formdata', event => {
        originalOptions.forEach(({ input, text, html }) => {
            if (input.isConnected && !input.disabled && input.value === text && event.formData.has(input.name)) event.formData.set(input.name, html);
        });
    });
    const markDirty = () => {
        if (!canEdit) return;
        dirty = true;
        saveStatus.textContent = 'Có thay đổi chưa lưu';
        clearTimeout(previewTimer); previewTimer = setTimeout(refreshPreview, 400);
        clearTimeout(saveTimer);
        if (!embedded && form.dataset.historyUrl && !autosavePaused) saveTimer = setTimeout(() => saveDraft(), 3500);
    };
    const formData = () => {
        const data = new FormData(form);
        if (!data.has('correct_options[]')) data.set('correct_options', '');
        return data;
    };
    async function refreshPreview() {
        if (!canEdit) return;
        previewRequest?.abort();
        const request = new AbortController(); previewRequest = request;
        const data = formData(); data.delete('_method');
        const target = root.querySelector('[data-qe-preview]');
        target.setAttribute('aria-busy', 'true');
        try {
            const result = await api(form.dataset.previewUrl, { method: 'POST', body: data, signal: request.signal });
            if (previewRequest !== request) return;
            target.querySelectorAll('audio').forEach(audio => audio.pause());
            target.innerHTML = result.html;
            const warnings = root.querySelector('[data-qe-warnings]');
            warnings.hidden = !result.warnings?.length;
            warnings.querySelector('ul').replaceChildren(...(result.warnings ?? []).map(message => element('li', '', message)));
        } catch (error) { if (error.name !== 'AbortError') showError(error.message); }
        finally { if (previewRequest === request) target.removeAttribute('aria-busy'); }
    }
    async function saveDraft() {
        clearTimeout(saveTimer);
        if (!canEdit || (!embedded && !form.dataset.historyUrl)) return false;
        if (saving) { await saving; return dirty ? saveDraft() : !autosavePaused; }
        if (!dirty && form.querySelector('[name="_method"]')) return true;
        const data = formData();
        const sent = new URLSearchParams(data).toString();
        saveStatus.textContent = 'Đang lưu nháp…';
        const submits = [...form.querySelectorAll('[type="submit"]')]; submits.forEach(button => { button.disabled = true; });
        saving = (async () => {
            try {
                const result = await api(form.action, { method: 'POST', body: data });
                // Compare before replacing the version counter so edits made during the request stay dirty.
                dirty = new URLSearchParams(formData()).toString() !== sent;
                if (embedded && result.id && !form.querySelector('[name="_method"]')) {
                    form.action = form.action.replace(/\/$/, '') + '/' + result.id;
                    const method = element('input'); method.type = 'hidden'; method.name = '_method'; method.value = 'PUT'; form.append(method);
                }
                form.querySelector('[name="revision_counter"]').value = result.revision_counter;
                if (result.id) root.querySelector('[data-qe-id]').value = `Q-${String(result.id).padStart(5, '0')}`;
                root.querySelector('[data-qe-updated]').value = result.updated_at;
                root.querySelector('[data-qe-current-status]').textContent = 'Bản nháp';
                if (result.workflow_html && root.querySelector('[data-qe-workflow]')) root.querySelector('[data-qe-workflow]').innerHTML = result.workflow_html;
                if (result.history) renderHistory(result.history);
                feedback.hidden = true; autosavePaused = false;
                saveStatus.textContent = dirty ? 'Có thay đổi chưa lưu' : 'Đã lưu nháp';
                if (dirty && !embedded) saveTimer = setTimeout(() => saveDraft(), 3500);
                if (embedded) root.dispatchEvent(new CustomEvent('qe:saved', { bubbles: true, detail: { ...result, dirty } }));
                return true;
            } catch (error) { autosavePaused = true; saveStatus.textContent = 'Chưa lưu được'; showError(error.message); return false; }
            finally { submits.forEach(button => { button.disabled = false; }); }
        })();
        const success = await saving; saving = null; return success;
    }
    form.querySelectorAll('[data-qe-rich]').forEach(textarea => {
        const wrapper = element('div', 'qe-rich'), toolbar = element('div', 'qe-rich-toolbar'), surface = element('div', 'qe-rich-surface');
        textarea.before(wrapper); wrapper.append(toolbar, surface, textarea); textarea.hidden = true;
        const updateCount = editor => { const count = root.querySelector(`[data-qe-count="${textarea.name}"]`); if (count) count.textContent = `${editor.getText().length.toLocaleString('vi')} / 10.000`; };
        const editor = new Editor({ element: surface, editable: canEdit, extensions: [StarterKit.configure({ link: { openOnClick: false } })], content: textarea.value, onUpdate: ({ editor: instance }) => { textarea.value = instance.getHTML(); textarea.dispatchEvent(new Event('input', { bubbles: true })); updateCount(instance); } });
        surface.setAttribute('aria-label', textarea.name === 'prompt' ? 'Nội dung câu hỏi' : 'Giải thích đáp án');
        const actions = [
            ['B', 'In đậm', () => editor.chain().focus().toggleBold().run(), 'bold'],
            ['I', 'In nghiêng', () => editor.chain().focus().toggleItalic().run(), 'italic'],
            ['U', 'Gạch chân', () => editor.chain().focus().toggleUnderline().run(), 'underline'],
            ['↗', 'Chèn liên kết', () => { const url = window.prompt('Địa chỉ liên kết HTTPS', editor.getAttributes('link').href ?? 'https://'); if (url === null) return; if (url === '') editor.chain().focus().unsetLink().run(); else if (/^https?:\/\//i.test(url)) editor.chain().focus().setLink({ href: url }).run(); else showError('Liên kết cần bắt đầu bằng https:// hoặc http://.'); }, 'link'],
            ['• ≡', 'Danh sách dấu đầu dòng', () => editor.chain().focus().toggleBulletList().run(), 'bulletList'],
            ['1 ≡', 'Danh sách đánh số', () => editor.chain().focus().toggleOrderedList().run(), 'orderedList'],
            ['↶', 'Hoàn tác', () => editor.chain().focus().undo().run()],
        ];
        const buttons = actions.map(([label, title, run, active]) => { const button = element('button', '', label); button.type = 'button'; button.title = title; button.setAttribute('aria-label', title); button.disabled = !canEdit; button.addEventListener('click', run); toolbar.append(button); return { button, active }; });
        editor.on('transaction', () => buttons.forEach(({ button, active }) => { if (active) { button.classList.toggle('is-active', editor.isActive(active)); button.setAttribute('aria-pressed', String(editor.isActive(active))); } }));
        richEditors.push(editor);
        updateCount(editor);
    });
    function syncAnswers() {
        const single = typeCode() !== 'multiple_response';
        let found = false;
        rows().forEach(row => {
            const answer = row.querySelector('[name="correct_options[]"]');
            answer.type = single ? 'radio' : 'checkbox';
            if (single && answer.checked) { if (found) answer.checked = false; found = true; }
            row.classList.toggle('is-correct', answer.checked && family() === 'choice');
            const label = row.querySelector('[data-choice-answer]'); label.hidden = family() !== 'choice'; answer.disabled = !canEdit || family() !== 'choice';
        });
        const selected = rows().find(row => row.querySelector('[name="correct_options[]"]').checked)?.dataset.qeOption ?? '';
        answerSelect.replaceChildren(new Option('Chọn', ''), ...rows().map(row => new Option(row.dataset.qeOption, row.dataset.qeOption)));
        answerSelect.value = selected;
        root.querySelector('[data-qe-single-answer]').hidden = family() !== 'choice' || !single;
    }
    function newOption(key, value = '') {
        const row = element('div', 'qe-option-row'); row.dataset.qeOption = key;
        row.append(element('span', 'qe-option-key', key));
        const input = element('input'); input.name = `options_values[${key}]`; input.value = value; input.maxLength = 2000; input.setAttribute('aria-label', `Phương án ${key}`); row.append(input);
        const label = element('label', 'qe-option-answer'); label.dataset.choiceAnswer = '';
        const answer = element('input'); answer.name = 'correct_options[]'; answer.value = key; answer.type = 'checkbox'; answer.setAttribute('aria-label', `Đặt ${key} là đáp án đúng`); label.append(answer, element('span', '', 'Đáp án đúng')); row.append(label);
        const tools = element('div', 'qe-option-tools');
        [['up', '↑', `Di chuyển phương án ${key} lên`], ['down', '↓', `Di chuyển phương án ${key} xuống`], ['remove', '×', `Xóa phương án ${key}`]].forEach(([action, text, title]) => { const button = element('button', '', text); button.type = 'button'; button.title = title; button.setAttribute('aria-label', title); if (action === 'remove') button.dataset.qeRemoveOption = ''; else button.dataset.qeMove = action; tools.append(button); });
        row.append(tools); list.append(row); return row;
    }
    function updateTypes(changed = false) {
        const group = form.querySelector('[name="question_group_id"]');
        [...group.options].forEach(option => { option.hidden = Boolean(option.value) && option.dataset.part !== part.value; option.disabled = option.hidden; });
        if (group.selectedOptions[0]?.hidden) group.value = '';
        const available = catalog.parts.find(item => String(item.id) === part.value)?.types ?? [];
        [...type.options].forEach(option => { option.hidden = !available.includes(option.dataset.code); option.disabled = option.hidden; });
        if (type.selectedOptions[0]?.hidden) type.value = [...type.options].find(option => !option.hidden)?.value ?? '';
        const code = typeCode();
        const special = code === 'true_false_not_given' ? ['TRUE', 'FALSE', 'NOT_GIVEN'] : code === 'yes_no_not_given' ? ['YES', 'NO', 'NOT_GIVEN'] : null;
        if (special && rows().map(row => row.dataset.qeOption).join(',') !== special.join(',')) { list.replaceChildren(); special.forEach(key => newOption(key, key.replaceAll('_', ' '))); }
        if (changed && code === 'multiple_choice' && rows().some(row => ['TRUE', 'FALSE', 'NOT_GIVEN', 'YES', 'NO'].includes(row.dataset.qeOption))) { list.replaceChildren(); ['A', 'B', 'C', 'D'].forEach(key => newOption(key)); }
        const isThptQuestion = part.selectedOptions[0]?.dataset.examCode === 'thpt';
        root.classList.toggle('is-thpt-question', isThptQuestion);
        if (thptQuestionNote) thptQuestionNote.hidden = !isThptQuestion;
        if (isThptQuestion && code === 'multiple_choice' && rows().map(row => row.dataset.qeOption).join(',') !== 'A,B,C,D') {
            const currentRows = rows();
            const currentValues = currentRows.slice(0, 4).map(row => row.querySelector('input[name^="options_values["]').value);
            const correctIndex = currentRows.findIndex(row => row.querySelector('[name="correct_options[]"]').checked);
            list.replaceChildren();
            ['A', 'B', 'C', 'D'].forEach((key, index) => {
                const row = newOption(key, currentValues[index] ?? '');
                if (index === correctIndex) row.querySelector('[name="correct_options[]"]').checked = true;
            });
            markDirty();
        }
        const currentFamily = family() === 'writing' ? 'essay' : family();
        const minWords = form.querySelector('[data-qe-min-words]');
        const currentPartCode = part.selectedOptions[0]?.dataset.partCode;
        if (changed && previousPartCode !== currentPartCode && currentFamily === 'essay' && minWords && ['', '150', '250'].includes(minWords.value)) {
            minWords.value = currentPartCode === 'writing-task-2' ? '250' : '150';
        }
        previousPartCode = currentPartCode;
        form.querySelectorAll('[data-type-family]').forEach(section => {
            const visible = section.dataset.typeFamily.split(' ').includes(currentFamily); section.hidden = !visible;
            section.querySelectorAll('input,textarea,select,button').forEach(input => { input.disabled = !visible || !canEdit; });
            if (section.matches('textarea')) section.disabled = !visible || !canEdit;
        });
        syncAnswers();
    }
    function updateParts() {
        [...part.options].forEach(option => { option.hidden = option.dataset.exam !== exam.value; option.disabled = option.hidden; });
        if (part.selectedOptions[0]?.hidden) part.value = [...part.options].find(option => !option.hidden)?.value ?? '';
        updateTypes(true);
    }
    exam.value = part.selectedOptions[0]?.dataset.exam ?? exam.value;
    updateParts();
    exam.addEventListener('change', updateParts);
    part.addEventListener('change', () => updateTypes(true));
    type.addEventListener('change', () => updateTypes(true));
    answerSelect.addEventListener('change', () => { rows().forEach(row => { row.querySelector('[name="correct_options[]"]').checked = row.dataset.qeOption === answerSelect.value; }); syncAnswers(); markDirty(); });
    list.addEventListener('change', () => syncAnswers());
    list.addEventListener('click', event => {
        const row = event.target.closest('[data-qe-option]'); if (!row) return;
        if (event.target.closest('[data-qe-remove-option]')) { if (rows().length <= 2) { showError('Cần giữ ít nhất hai phương án.'); return; } row.remove(); syncAnswers(); markDirty(); }
        const mover = event.target.closest('[data-qe-move]');
        if (mover) { if (mover.dataset.qeMove === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling); else if (mover.dataset.qeMove === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row); syncAnswers(); markDirty(); }
    });
    root.querySelector('[data-qe-add-option]').addEventListener('click', () => {
        if (rows().length >= 30) return showError('Tối đa 30 phương án.');
        const keys = new Set(rows().map(row => row.dataset.qeOption));
        const key = Array.from({ length: 30 }, (_, i) => i < 26 ? String.fromCharCode(65 + i) : `A${String.fromCharCode(65 + i - 26)}`).find(key => !keys.has(key));
        const row = newOption(key); updateTypes(); row.querySelector('input').focus(); markDirty();
    });
    function renderTags() {
        tags = [...new Set(tags)]; form.querySelector('[data-qe-tags-value]').value = tags.join(', ');
        root.querySelector('[data-qe-tags]').replaceChildren(...tags.map(tag => { const chip = element('span', 'qe-chip'); chip.append(element('span', '', tag)); if (canEdit) { const remove = element('button', '', '×'); remove.type = 'button'; remove.setAttribute('aria-label', `Gỡ thẻ ${tag}`); remove.addEventListener('click', () => { tags = tags.filter(item => item !== tag); renderTags(); markDirty(); }); chip.append(remove); } return chip; }));
    }
    renderTags();
    function renderHistory(data, append = false) {
        const target = root.querySelector('[data-qe-history]'); if (!append) target.replaceChildren();
        if (!data.items.length && !append) target.append(element('p', 'qe-help', 'Chưa có lịch sử chỉnh sửa.'));
        data.items.forEach(item => { const row = element('div', 'qe-history-row'); row.append(element('span', 'qe-history-avatar', item.actor.split(' ').filter(Boolean).slice(-2).map(word => word[0]).join(''))); const copy = element('div'); copy.append(element('strong', '', item.actor), element('small', '', `${item.summary} · v${item.number}`)); const time = element('time', '', item.time); time.dateTime = item.created_at; time.title = item.created_at; row.append(copy, time); target.append(row); });
        historyPage = data.next_page; root.querySelector('[data-qe-more-history]').hidden = !historyPage;
    }
    function renderLinks(items) {
        const target = root.querySelector('[data-qe-links]'); target.replaceChildren();
        if (!items.length && form.dataset.linksUrl) target.append(element('p', 'qe-help', 'Chưa liên kết với đề thi.'));
        items.forEach(item => { const row = element('div', 'qe-exam-link'); row.append(element('span', '', '▤')); const link = element('a'); link.href = item.url; link.append(element('strong', '', item.title), element('small', '', `${item.position ? `Câu ${item.position} · ` : ''}${item.draft ? 'Bản nháp' : 'Đã xuất bản'}${item.published && item.draft ? ' · Có bản đã xuất bản' : ''}`)); row.append(link); if (canEdit && item.draft) { const remove = element('button', '', '×'); remove.type = 'button'; remove.title = 'Gỡ khỏi bản nháp'; remove.setAttribute('aria-label', `Gỡ câu hỏi khỏi bản nháp ${item.title}`); remove.addEventListener('click', async () => { remove.disabled = true; try { const result = await api(`${form.dataset.linksUrl}/${item.id}`, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ revision_counter: item.revision_counter }) }); renderLinks(result.items); } catch (error) { showError(error.message); remove.disabled = false; } }); row.append(remove); } target.append(row); });
    }
    renderHistory(initial.history); renderLinks(initial.links);
    root.querySelector('[data-qe-more-history]').addEventListener('click', async event => { const button = event.currentTarget; button.disabled = true; try { const result = await api(`${form.dataset.historyUrl}?page=${historyPage}`); renderHistory(result, true); } catch (error) { showError(error.message); } finally { button.disabled = false; } });
    async function loadPicker() {
        pickerRequest?.abort(); const request = new AbortController(); pickerRequest = request;
        const url = new URL(form.dataset.catalogUrl, location.origin); url.searchParams.set('kind', pickerKind); url.searchParams.set('q', root.querySelector('[data-qe-picker-search]').value); if (pickerKind === 'exam') url.searchParams.set('part_id', part.value);
        pickerFeedback.textContent = 'Đang tải…'; pickerResults.replaceChildren();
        try {
            const result = await api(url, { signal: request.signal }); if (pickerRequest !== request) return;
            pickerFeedback.textContent = result.items.length ? '' : 'Không có kết quả phù hợp.';
            result.items.forEach(item => { const button = element('button', 'qe-picker-result'); button.type = 'button'; button.append(element('strong', '', item.name), element('small', '', item.detail ?? '')); button.addEventListener('click', () => chooseItem(item, button)); pickerResults.append(button); });
        } catch (error) { if (error.name !== 'AbortError') pickerFeedback.textContent = error.message; }
    }
    async function chooseItem(item, button) {
        if (pickerKind === 'exam') {
            button.disabled = true;
            try { if (dirty && !await saveDraft()) return; const result = await api(`${form.dataset.linksUrl}/${item.id}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ revision_counter: item.revision_counter }) }); renderLinks(result.items); dialog.close(); }
            catch (error) { pickerFeedback.textContent = error.message; }
            finally { button.disabled = false; }
            return;
        }
        if (pickerKind === 'tag') { tags.push(item.name); renderTags(); }
        else if (pickerKind === 'knowledge') {
            const target = root.querySelector('[data-qe-knowledge]');
            if (![...target.querySelectorAll('[data-id]')].some(chip => chip.dataset.id === String(item.id))) {
                const chip = element('span', 'qe-chip'); chip.dataset.id = item.id;
                const input = element('input'); input.type = 'hidden'; input.name = 'metadata[knowledge_ids][]'; input.value = item.id;
                const remove = element('button', '', '×'); remove.type = 'button'; remove.dataset.qeRemoveChip = ''; remove.setAttribute('aria-label', `Gỡ kiến thức ${item.name}`); chip.append(input, element('span', '', item.name), remove); target.insertBefore(chip, target.querySelector('[data-qe-picker]'));
            }
        } else { const resource = root.querySelector(`[data-qe-resource="${pickerKind}"]`); resource.querySelector('input').value = item.id; resource.querySelector('[data-qe-resource-label]').textContent = item.name; resource.querySelector('[data-qe-clear-resource]').hidden = false; }
        dialog.close(); markDirty();
    }
    root.addEventListener('click', event => {
        const trigger = event.target.closest('[data-qe-picker]');
        if (trigger && canEdit) { pickerKind = trigger.dataset.qePicker; root.querySelector('#qe-picker-title').textContent = { tag: 'Chọn thẻ', knowledge: 'Chọn kiến thức', image: 'Chọn ảnh', audio: 'Chọn audio', stimulus: 'Chọn bài đọc / bài nghe', exam: 'Liên kết đề thi' }[pickerKind]; root.querySelector('[data-qe-picker-search]').value = ''; root.querySelector('[data-qe-new-tag]').hidden = pickerKind !== 'tag'; dialog.showModal(); loadPicker(); }
        const removeChip = event.target.closest('[data-qe-remove-chip]'); if (removeChip) { removeChip.closest('.qe-chip').remove(); markDirty(); }
        const clear = event.target.closest('[data-qe-clear-resource]'); if (clear) { const resource = clear.closest('[data-qe-resource]'); resource.querySelector('input').value = ''; resource.querySelector('[data-qe-resource-label]').textContent = { image: 'Chọn ảnh', audio: 'Chọn Audio', stimulus: 'Chọn bài đọc / bài nghe' }[resource.dataset.qeResource]; clear.hidden = true; markDirty(); }
    });
    root.querySelector('[data-qe-dialog-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pickerRequest?.abort(); clearTimeout(pickerTimer); });
    root.querySelector('[data-qe-picker-search]').addEventListener('input', () => { clearTimeout(pickerTimer); pickerTimer = setTimeout(loadPicker, 250); });
    root.querySelector('[data-qe-add-tag]').addEventListener('click', () => { const input = root.querySelector('[data-qe-tag-input]'); const value = input.value.trim(); if (!value || value.includes(',')) { pickerFeedback.textContent = 'Nhập tên thẻ, không chứa dấu phẩy.'; return; } tags.push(value); renderTags(); input.value = ''; dialog.close(); markDirty(); });
    root.querySelector('[data-qe-refresh]').addEventListener('click', refreshPreview);
    const expand = root.querySelector('[data-qe-expand]'), previewCard = root.querySelector('.qe-preview-card');
    expand.addEventListener('click', () => { const expanded = previewCard.classList.toggle('is-expanded'); expand.setAttribute('aria-expanded', String(expanded)); expand.setAttribute('aria-label', expanded ? 'Thu gọn xem trước' : 'Mở rộng xem trước'); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && previewCard.classList.contains('is-expanded')) { previewCard.classList.remove('is-expanded'); expand.setAttribute('aria-expanded', 'false'); expand.focus(); } }, { signal: lifecycle.signal });
    form.addEventListener('input', markDirty); form.addEventListener('change', markDirty);
    form.addEventListener('submit', async event => {
        if (!embedded && !form.dataset.historyUrl) { dirty = false; clearTimeout(saveTimer); return; }
        event.preventDefault();
        const next = event.submitter?.name === 'save_next';
        if (await saveDraft()) root.dispatchEvent(new CustomEvent('qe:complete', { bubbles: true, detail: { next, dirty } }));
    });
    root.querySelector('[data-qe-workflow]')?.addEventListener('submit', event => { if (dirty || saving) { event.preventDefault(); showError('Lưu nháp trước khi thay đổi trạng thái câu hỏi.'); } });
    window.addEventListener('beforeunload', event => { if (dirty || saving) { event.preventDefault(); event.returnValue = ''; } }, { signal: lifecycle.signal });
    root._qeController = {
        canLeave: () => !saving && (!dirty || window.confirm('Có thay đổi chưa lưu. Bỏ thay đổi để chuyển câu hỏi?')),
        destroy: () => { lifecycle.abort(); clearTimeout(saveTimer); clearTimeout(previewTimer); clearTimeout(pickerTimer); previewRequest?.abort(); pickerRequest?.abort(); richEditors.forEach(editor => editor.destroy()); dialog.close(); delete root._qeController; },
    };
    if (embedded) {
        // Keep the article binding visible while the server enforces it on every save.
        const stimulus = root.querySelector('[data-qe-resource="stimulus"]');
        stimulus?.querySelectorAll('button').forEach(button => { button.disabled = true; });
        const boundPart = initialPart => {
            if (!initialPart) return;
            exam.disabled = true; part.style.pointerEvents = 'none'; part.tabIndex = -1;
            part.addEventListener('change', () => { part.value = initialPart; updateTypes(); });
        };
        if (part.value && root.dataset.lockPart === '1') boundPart(part.value);
        const group = form.querySelector('[name="question_group_id"]'); group.style.pointerEvents = 'none'; group.tabIndex = -1;
        form.querySelector('[name="group_order"]').readOnly = true;
    }
    return root._qeController;
}
initQuestionEditor(document.querySelector('[data-qe-root]'));
