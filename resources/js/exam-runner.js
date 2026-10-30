export const orderedOptions = item => (item.option_order ?? Object.keys(item.options ?? {})).filter(key => Object.hasOwn(item.options ?? {}, key)).map(key => [key, item.options[key]]);

export const canNavigateQuestion = (index, currentIndex, policies) =>
    (!(policies.lock_previous) || index >= currentIndex) &&
    (policies.navigation !== 'linear' || Math.abs(index - currentIndex) <= 1);

export const nextStepKind = (items, selected, activeSection) => {
    const current = items[selected], next = items[selected + 1];
    if (!current || !next) return null;
    return current.section === activeSection && next.section === activeSection + 1 ? 'section' : 'question';
};

export const remainingSeconds = (deadline, serverOffset = 0, time = Date.now()) => Math.max(0, Math.floor((Date.parse(deadline) - time - serverOffset) / 1000));

export const reconcileAnswers = (pending, serverAnswers) => {
    const remaining = structuredClone(pending);
    for (const answer of serverAnswers) {
        const local = remaining[answer.item_key];
        if (local && JSON.stringify(local.response) === JSON.stringify(answer.response) && !!local.flagged === !!answer.flagged) { delete remaining[answer.item_key]; }
    }
    return remaining;
};

export const recordingFilename = mime => mime.includes('mp4') ? 'recording.mp4' : mime.includes('ogg') ? 'recording.ogg' : 'recording.webm';

// Submitting and background autosave must await the same write, rather than treating it as a failed sync.
export const singleFlight = operation => {
    let inFlight;
    return (...args) => {
        inFlight ??= Promise.resolve().then(() => operation(...args)).finally(() => { inFlight = null; });
        return inFlight;
    };
};

export const initializeExamRunner = (rootOverride = undefined) => {
    const root = rootOverride ?? (typeof document === 'undefined' ? null : document.querySelector('[data-exam-runner]'));
    if (!root || root.dataset.examRunnerInitialized === 'true') return;
    root.dataset.examRunnerInitialized = 'true';
    const config = JSON.parse(root.querySelector('[data-exam-config]').textContent);
    const policies = config.snapshot.policies;
    let state = config.state ?? { active_section: 0, answers: [], paused: false, deadline: new Date(Date.now() + policies.duration_minutes * 60000).toISOString(), server_time: new Date().toISOString() };
    let offset = Date.parse(state.server_time) - Date.now();
    let selected = state.current_item_index ?? 0, submitting = false, saving = false, pausedAt = null, navigating = false, fullscreenViolationPending = false;
    const storageKey = `vistiq-attempt-${config.state?.id ?? 'preview'}`;
    const savedAnswers = new Map(state.answers.map(answer => [answer.item_key, answer]));
    let pending = {}, conflicting = false, eventQueue = [], activeRecorder = null, recordingPending = false, questionVisitedAt = Date.now();
    const recordings = new Map(), audioGraphs = new WeakMap();
    let recordingDatabase, uploadingRecordings = false;
    try { if (!config.preview) { eventQueue = JSON.parse(localStorage.getItem(`${storageKey}-events`) ?? '[]'); } } catch { eventQueue = []; }
    try { if (!config.preview) { pending = JSON.parse(localStorage.getItem(storageKey) ?? '{}'); } } catch { pending = {}; }
    const status = root.querySelector('[data-save-status]');
    const warning = root.querySelector('[data-warning]');
    const persist = () => { if (!config.preview) { try { localStorage.setItem(storageKey, JSON.stringify(pending)); } catch { status.textContent = 'Trình duyệt không cho lưu cục bộ. Giữ kết nối mạng.'; } } };
    const showWarning = message => { if (conflicting && warning.querySelector('button')) { return; } warning.hidden = false; warning.textContent = message; };
    const request = async (endpoint, body = undefined, options = {}) => {
        const response = await fetch(endpoint, { method: body === undefined ? 'GET' : 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: body === undefined ? undefined : JSON.stringify(body), keepalive: options.keepalive ?? false });
        const data = await response.json();
        if (!response.ok) { const error = new Error(data.message ?? 'Không thể xử lý yêu cầu'); error.status = response.status; throw error; }
        return data;
    };
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
    const current = () => config.snapshot.items[selected];
    const sectionAccessible = item => {
        const active = config.snapshot.sections[state.active_section], section = config.snapshot.sections[item.section];
        if (policies.navigation === 'linear' || policies.lock_previous) { return item.section === state.active_section; }
        if (active.duration_minutes > 0) { return active.timing_group === section.timing_group; }
        return true;
    };
    const questionAccessible = (item, index) => sectionAccessible(item) && canNavigateQuestion(index, state.current_item_index ?? selected, policies);
    const audioMarkup = (item, stimulus = null) => {
        const url = stimulus ? stimulus.asset_path : item.metadata.audio_url;
        return `<div class="assessment-audio"><button type="button" class="button button-quiet" data-play-audio data-stimulus="${stimulus?.id ?? ''}" ${config.preview && !url ? 'disabled' : ''}>Phát audio${policies.strict_audio ? ' · một lần' : ''}</button><audio ${policies.strict_audio ? '' : 'controls'} preload="none" ${url ? `src="${escape(url)}"` : ''}></audio><canvas data-waveform height="48" width="500" aria-label="Dạng sóng audio"></canvas></div>`;
    };
    const recordingStorage = async (operation, value = null) => {
        recordingDatabase ??= new Promise((resolve, reject) => {
            const opening = indexedDB.open('vistiq-recordings', 1);
            opening.onupgradeneeded = () => opening.result.createObjectStore('recordings', { keyPath: 'id' });
            opening.onsuccess = () => resolve(opening.result);
            opening.onerror = () => reject(opening.error);
        });
        const database = await recordingDatabase;
        return new Promise((resolve, reject) => {
            const transaction = database.transaction('recordings', operation === 'getAll' ? 'readonly' : 'readwrite');
            const store = transaction.objectStore('recordings'), request = operation === 'getAll' ? store.getAll() : store[operation](value);
            transaction.oncomplete = () => resolve(request.result);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error);
        });
    };
    const flushRecordings = singleFlight(async () => {
        if (uploadingRecordings || config.preview || !navigator.onLine || state.paused || state.status === 'expired' || submitting) { return; }
        if (!recordings.size) { return; }
        uploadingRecordings = true;
        try {
            for (const [itemKey, recording] of recordings) {
                const form = new FormData();
                form.append('file', recording.blob, recordingFilename(recording.blob.type));
                form.append('item_key', itemKey);
                const response = await fetch(config.endpoints.recording, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: form });
                const data = await response.json();
                if (!response.ok) { throw new Error(data.message ?? 'Không thể tải recording.'); }
                if (recordings.get(itemKey) !== recording) { continue; }
                pending[itemKey] = { item_key: itemKey, version: savedAnswers.get(itemKey)?.version ?? 0, response: { asset_id: data.asset_id }, recording_url: data.url, spent_seconds: pending[itemKey]?.spent_seconds ?? savedAnswers.get(itemKey)?.spent_seconds ?? 0, flagged: pending[itemKey]?.flagged ?? savedAnswers.get(itemKey)?.flagged ?? false };
                persist();
                recordings.delete(itemKey);
                await recordingStorage('delete', recording.id).catch(() => {});
            }
            if (await flush()) { const answer = savedAnswers.get(current()?.key); if (answer?.recording_url) { root.querySelector('[data-recording]')?.setAttribute('src', answer.recording_url); } }
        } catch (error) { showWarning(`Recording đang chờ đồng bộ: ${error.message}`); }
        finally { uploadingRecordings = false; }
    });
    const restoreRecordings = async () => {
        if (config.preview) { return; }
        try {
            for (const recording of await recordingStorage('getAll')) {
                if (recording.attempt === storageKey) { recording.url = URL.createObjectURL(recording.blob); recordings.set(recording.itemKey, recording); }
            }
            if (recordings.size) { render(); await flushRecordings(); }
        } catch { /* Recording remains usable in memory if browser storage is unavailable. */ }
    };
    const answerFor = item => pending[item.key] ?? savedAnswers.get(item.key);
    const hasResponse = answer => {
        const response = answer?.response;
        if (!response || typeof response !== 'object') { return false; }
        return Object.values(response).some(value => {
            if (Array.isArray(value)) { return value.length > 0; }
            if (value && typeof value === 'object') { return Object.values(value).some(entry => String(entry ?? '').trim() !== ''); }
            return typeof value === 'string' ? value.trim() !== '' : value !== null && value !== false;
        });
    };
    const syncProgress = () => {
        const items = config.snapshot.items;
        const answered = items.filter(item => hasResponse(answerFor(item))).length;
        const flagged = items.filter(item => answerFor(item)?.flagged).length;
        const total = items.length;
        root.querySelectorAll('[data-answered-count], [data-answered-summary]').forEach(node => { node.textContent = answered; });
        const unansweredNode = root.querySelector('[data-unanswered-count]');
        const flaggedNode = root.querySelector('[data-flagged-count]');
        if (unansweredNode) { unansweredNode.textContent = total - answered; }
        if (flaggedNode) { flaggedNode.textContent = flagged; }
        const progress = root.querySelector('[data-progress-bar]');
        if (progress) { progress.style.width = `${total ? answered / total * 100 : 0}%`; }
    };
    const render = () => {
        if (!config.snapshot.items.length) { root.querySelector('[data-items]').textContent = 'Đề chưa có câu hỏi.'; syncProgress(); return; }
        const item = current(), answer = answerFor(item) ?? { response: {} }, response = answer.response ?? {};
        questionVisitedAt = Date.now();
        const recordingUrl = recordings.get(item.key)?.url ?? answer.recording_url;
        const navigation = root.querySelector('[data-navigation]');
        navigation.innerHTML = config.snapshot.sections.map((section, sectionIndex) => {
            const entries = config.snapshot.items.map((entry, index) => ({ entry, index })).filter(({ entry }) => entry.section === sectionIndex);
            if (!entries.length) { return ''; }
            const complete = entries.filter(({ entry }) => hasResponse(answerFor(entry))).length;
            return `<details class="exam-nav-section" ${entries.some(({ index }) => index === selected) || sectionIndex === state.active_section ? 'open' : ''}><summary><span>${escape(section.title)}</span><small>${entries.length} câu</small></summary><div class="assessment-nav-grid">${entries.map(({ entry, index }) => {
                const saved = answerFor(entry), classes = ['assessment-nav-question'];
                if (selected === index) { classes.push('is-current'); }
                if (hasResponse(saved)) { classes.push('is-answered'); }
                if (saved?.flagged) { classes.push('is-flagged'); }
                return `<button class="${classes.join(' ')}" type="button" data-question="${index}" aria-label="Câu ${index + 1}${hasResponse(saved) ? ', đã trả lời' : ', chưa trả lời'}${saved?.flagged ? ', đã đánh dấu' : ''}" ${!questionAccessible(entry, index) ? 'disabled' : ''}>${index + 1}</button>`;
            }).join('')}</div><span class="exam-nav-complete">${complete}/${entries.length} câu đã trả lời</span></details>`;
        }).join('');
        let stimulusHtml = '';
        if (item.metadata?.image_url) { stimulusHtml += `<img class="assessment-image" src="${escape(item.metadata.image_url)}" alt="Hình câu hỏi">`; }
        if (item.metadata?.audio_id) { stimulusHtml += audioMarkup(item); }
        for (const stimulus of item.stimuli ?? []) {
            stimulusHtml += `<section class="assessment-passage"><h3>${escape(stimulus.title)}</h3>${stimulus.stimulus_type === 'audio' ? audioMarkup(item, stimulus) : stimulus.body ?? ''}</section>`;
        }
        const choice = ['multiple_choice', 'multiple_response', 'true_false_not_given', 'yes_no_not_given'].includes(item.type);
        let answerHtml = '';
        if (choice) {
            answerHtml = orderedOptions(item).map(([key, value]) => `<label class="assessment-option"><input type="${item.type === 'multiple_response' ? 'checkbox' : 'radio'}" name="answer" value="${escape(key)}" ${(response.options ?? []).includes(key) ? 'checked' : ''}> <b>${escape(key)}</b> ${escape(value)}</label>`).join('');
            if (item.selection_count) { answerHtml += `<p>Chọn ${item.selection_count} đáp án.</p>`; }
        } else if (item.matching_rows?.length) {
            answerHtml = item.matching_rows.map(key => `<label>${escape(item.metadata?.labels?.[key] ?? key)}<select data-matching="${escape(key)}"><option value="">Chưa chọn</option>${Object.entries(item.options).map(([option, label]) => `<option value="${escape(option)}" ${response.matching?.[key] === option ? 'selected' : ''}>${escape(option)} · ${escape(label)}</option>`).join('')}</select></label>`).join('');
        } else if (Object.keys(item.blank_rows ?? {}).length) {
            answerHtml = Object.entries(item.blank_rows).map(([key, blank]) => `<label>${escape(blank.label || key)}<input data-blank="${escape(key)}" value="${escape(response.blanks?.[key] ?? '')}"></label>`).join('');
        } else if (item.type === 'speaking') {
            answerHtml = `<p>${escape(item.metadata?.cue_card)}</p><button type="button" class="button button-primary" data-record>Ghi âm</button><audio data-recording controls ${recordingUrl ? `src="${escape(recordingUrl)}"` : ''}></audio><p data-recording-status role="status">Cho phép microphone để ghi câu trả lời.</p>`;
        } else {
            answerHtml = `<label>Câu trả lời<textarea data-text rows="${item.type === 'essay' ? 16 : 3}">${escape(response.text ?? '')}</textarea></label><p data-word-count></p>`;
        }
        const sectionTitle = escape(config.snapshot.sections[item.section].title);
        const previousDisabled = selected === 0 || !questionAccessible(config.snapshot.items[selected - 1], selected - 1) ? 'disabled' : '';
        const nextKind = nextStepKind(config.snapshot.items, selected, state.active_section);
        const nextDisabled = !nextKind || (nextKind === 'section'
            ? state.paused || state.status === 'expired'
            : !questionAccessible(config.snapshot.items[selected + 1], selected + 1)) ? 'disabled' : '';
        const nextAction = nextKind === 'section' ? 'data-next-section' : 'data-step-question="1"';
        const nextLabel = nextKind === 'section' ? 'Phần tiếp theo' : 'Câu tiếp theo';
        const html = `<article class="panel assessment-item ${item.stimuli?.length ? 'has-reading-passage' : ''}">
            <section class="assessment-stimulus-panel ${stimulusHtml ? '' : 'is-empty'}">
                <header class="assessment-reading-heading"><div><span class="badge badge-blue">${sectionTitle}</span><h2>${sectionTitle}</h2></div><span>Câu ${selected + 1} / ${config.snapshot.items.length}</span></header>
                ${stimulusHtml || '<p class="assessment-stimulus-empty">Câu hỏi không có đoạn văn đi kèm.</p>'}
            </section>
            <section class="assessment-response-panel">
                <div class="assessment-prompt">${item.prompt ?? ''}</div>
                <div class="assessment-answer-list">${answerHtml}</div>
                <label class="assessment-flag"><input type="checkbox" data-flag ${answer.flagged ? 'checked' : ''}> Đánh dấu xem lại</label>
                <nav class="assessment-question-nav" aria-label="Điều hướng câu hỏi">
                    <button class="button button-quiet" type="button" data-step-question="-1" ${previousDisabled}>‹ <span>Câu trước</span></button>
                    <button class="button button-primary" type="button" ${nextAction} ${nextDisabled}><span>${nextLabel}</span> ›</button>
                </nav>
            </section>
        </article>`;
        root.querySelector('[data-items]').innerHTML = html;
        const blocked = (!policies.allow_mobile && matchMedia('(max-width: 600px)').matches) || (policies.require_fullscreen && !document.fullscreenElement);
        root.querySelectorAll('[data-items] input, [data-items] textarea, [data-items] select, [data-record], [data-play-audio]').forEach(input => { input.disabled = state.paused || state.status === 'expired' || blocked || !sectionAccessible(item); });
        root.querySelector('[data-flag]').closest('label').hidden = !policies.review_later;
        root.querySelector('[data-next-section]').disabled = state.paused || state.status === 'expired' || state.active_section >= config.snapshot.sections.length - 1;
        if (state.status === 'expired') { showWarning('Đã hết giờ. Đáp án được khóa; bấm Nộp bài để nhận kết quả.'); }
        if (root.querySelector('[data-pause]')) { root.querySelector('[data-pause]').disabled = state.status === 'expired'; }
        if (policies.require_fullscreen && !document.fullscreenElement) { showWarning(config.preview ? 'Đề yêu cầu toàn màn hình khi làm bài chính thức.' : 'Đã thoát toàn màn hình. Bài thi đang được nộp tự động.'); }
        if (policies.watermark) { root.querySelector('.assessment-item').dataset.watermark = config.watermark; }
        root.classList.toggle('assessment-no-selection', !!policies.disable_selection);
        root.querySelector('[data-pause]')?.replaceChildren(document.createTextNode(state.paused ? 'Tiếp tục' : 'Tạm dừng'));
        syncProgress(); countWords();
    };
    const countWords = () => { const counter = root.querySelector('[data-word-count]'); if (counter) { const count = (root.querySelector('[data-text]')?.value.trim().match(/\S+/g) ?? []).length; counter.textContent = `${count} từ${current().metadata?.min_words ? ` · tối thiểu ${current().metadata.min_words}` : ''}${current().metadata?.max_words ? ` · tối đa ${current().metadata.max_words} từ` : ''}`; } };
    const capture = () => {
        const item = current(); if (!item || state.paused || state.status === 'expired') { return; }
        const response = {};
        if (root.querySelector('input[name="answer"]')) { response.options = [...root.querySelectorAll('input[name="answer"]:checked')].map(input => input.value); }
        else if (root.querySelector('[data-matching]')) { response.matching = Object.fromEntries([...root.querySelectorAll('[data-matching]')].map(input => [input.dataset.matching, input.value])); }
        else if (root.querySelector('[data-blank]')) { response.blanks = Object.fromEntries([...root.querySelectorAll('[data-blank]')].map(input => [input.dataset.blank, input.value])); }
        else if (item.type === 'speaking') { Object.assign(response, pending[item.key]?.response ?? savedAnswers.get(item.key)?.response ?? {}); }
        else { response.text = root.querySelector('[data-text]')?.value ?? ''; }
        const spent = Number(pending[item.key]?.spent_seconds ?? savedAnswers.get(item.key)?.spent_seconds ?? 0) + Math.max(0, Math.floor((Date.now() - questionVisitedAt) / 1000));
        questionVisitedAt = Date.now();
        pending[item.key] = { item_key: item.key, version: savedAnswers.get(item.key)?.version ?? 0, response, spent_seconds: spent, flagged: !!root.querySelector('[data-flag]')?.checked, recording_url: pending[item.key]?.recording_url ?? savedAnswers.get(item.key)?.recording_url };
        persist(); status.textContent = config.preview ? 'Preview · đáp án được giữ trong phiên này' : 'Đã lưu cục bộ · chờ đồng bộ'; syncProgress(); countWords();
    };
    const flush = singleFlight(async () => {
        if (conflicting || saving || submitting || state.paused || state.status === 'expired' || !Object.keys(pending).length) { return !saving && !conflicting; }
        if (config.preview) { return true; }
        saving = true; const batch = JSON.parse(JSON.stringify(pending)), sectionBeforeSave = state.active_section;
        try {
            const data = await request(config.endpoints.answers, { answers: Object.values(batch) });
            for (const answer of data.answers) {
                savedAnswers.set(answer.item_key, { ...batch[answer.item_key], version: answer.version });
                if (JSON.stringify(pending[answer.item_key]) === JSON.stringify(batch[answer.item_key])) { delete pending[answer.item_key]; }
                else if (pending[answer.item_key]) { pending[answer.item_key].version = answer.version; }
                if (answer.review) { showWarning(`Đáp án: ${JSON.stringify(answer.review)} · ${answer.grade.points} điểm`); }
            }
            persist(); status.textContent = Object.keys(pending).length ? 'Đang đồng bộ thay đổi mới' : 'Đã đồng bộ với server'; return true;
        } catch (error) {
            if (error.status === 409) {
                const latest = await request(config.endpoints.state);
                if (latest.status !== 'in_progress') { await refresh(); return false; }
                const sectionChanged = latest.active_section !== sectionBeforeSave;
                const stateChanged = latest.active_section !== state.active_section || latest.status !== state.status || ((policies.lock_previous || policies.navigation === 'linear') && latest.current_item_index !== state.current_item_index);
                state = latest; config.snapshot = latest.snapshot; offset = Date.parse(state.server_time) - Date.now();
                latest.answers.forEach(answer => savedAnswers.set(answer.item_key, answer));
                pending = reconcileAnswers(pending, latest.answers); persist();
                const inaccessible = Object.keys(pending).filter(key => {
                    const item = config.snapshot.items.find(entry => entry.key === key);
                    if (!item) { return true; }
                    const active = config.snapshot.sections[state.active_section], section = config.snapshot.sections[item.section];
                    if (!active || !section) { return true; }
                    if (policies.navigation === 'linear' || policies.lock_previous) { return item.section !== state.active_section; }
                    return active.duration_minutes > 0 && (active.timing_group ?? state.active_section) !== (section.timing_group ?? item.section);
                });
                if (inaccessible.length) {
                    inaccessible.forEach(key => { delete pending[key]; }); persist();
                    const sectionTitle = config.snapshot.sections[state.active_section]?.title ?? `phần ${state.active_section + 1}`;
                    selected = state.current_item_index ?? Math.max(0, config.snapshot.items.findIndex(item => item.section === state.active_section)); render();
                    showWarning(`Đã chuyển sang ${sectionTitle}. ${inaccessible.length} câu ở phần đã kết thúc chưa kịp đồng bộ nên không được ghi nhận.`);
                    status.textContent = 'Có đáp án chưa đồng bộ vì phần thi đã kết thúc';
                    if (Object.keys(pending).length) { window.setTimeout(() => { void flush(); }, 0); }
                    return !sectionChanged && !Object.keys(pending).length;
                }
                if (stateChanged) { selected = state.current_item_index ?? Math.max(0, config.snapshot.items.findIndex(item => item.section === state.active_section)); render(); }
                if (sectionChanged && !Object.keys(pending).length) {
                    const sectionTitle = config.snapshot.sections[state.active_section]?.title ?? `phần ${state.active_section + 1}`;
                    showWarning(`Server đã chuyển sang ${sectionTitle}.`);
                    status.textContent = 'Đã cập nhật phần thi từ server';
                    return false;
                }
                if (Object.keys(pending).length) {
                    conflicting = true; showWarning(error.message);
                    for (const [label, action] of [['Dùng bản trên server','use-server'],['Lưu lại bản cục bộ','retry-local']]) {
                        const button = document.createElement('button'); button.type = 'button'; button.textContent = label; button.dataset.conflictAction = action; warning.append(button);
                    }
                }
            }
            status.textContent = 'Chưa đồng bộ · giữ đáp án cục bộ'; return false;
        } finally { saving = false; }
    });
    const refresh = async () => {
        if (config.preview || submitting) { return; }
        try {
            const updated = await request(config.endpoints.state);
            if (['completed', 'pending_grading', 'submitted'].includes(updated.status)) { localStorage.removeItem(storageKey); location.assign(config.endpoints.result); return; }
            const changed = updated.active_section !== state.active_section || updated.status !== state.status || ((policies.lock_previous || policies.navigation === 'linear') && updated.current_item_index !== state.current_item_index); state = updated; config.snapshot = updated.snapshot; updated.answers.forEach(answer => savedAnswers.set(answer.item_key, answer)); pending = reconcileAnswers(pending,updated.answers); persist(); offset = Date.parse(state.server_time) - Date.now();
            if (changed) { selected = state.current_item_index ?? Math.max(0, config.snapshot.items.findIndex(item => item.section === state.active_section)); render(); }
        } catch { status.textContent = 'Mất kết nối · bài được lưu cục bộ'; }
    };
    let sendingEvents = false;
    const persistEvents = () => { try { localStorage.setItem(`${storageKey}-events`, JSON.stringify(eventQueue)); } catch {} };
    const flushEvents = async () => {
        if (sendingEvents || !navigator.onLine || config.preview) { return; }
        sendingEvents = true;
        try {
            while (eventQueue.length) {
                const data = await request(config.endpoints.events, eventQueue[0], { keepalive: true }); eventQueue.shift(); persistEvents();
                if (data.warnings >= policies.warning_threshold) { showWarning(`Đã ghi nhận ${data.warnings} tín hiệu. Reviewer sẽ xem xét trong ngữ cảnh bài thi.`); }
                if (data.submitted) { location.assign(config.endpoints.result); break; }
            }
        } catch {} finally { sendingEvents = false; }
    };
    const event = type => {
        const requiredFullscreenViolation = type === 'FULLSCREEN_EXIT' && policies.require_fullscreen;
        const requiredFocusViolation = ['TAB_HIDDEN', 'WINDOW_BLUR'].includes(type) && policies.require_fullscreen && policies.track_focus;
        if (config.preview || submitting || (!policies.event_logging && !requiredFullscreenViolation && !requiredFocusViolation)) { return; }
        eventQueue.push({ type, event_key: crypto.randomUUID(), item_key: current()?.key }); persistEvents(); return flushEvents();
    };
    const submitForPolicyExit = async type => {
        if (config.preview || !policies.require_fullscreen || fullscreenViolationPending) return;
        fullscreenViolationPending = true;
        capture();
        await flush();
        event(type);
    };
    root.addEventListener('input', capture);
    root.addEventListener('change', eventObject => {
        if (eventObject.target.matches('[data-viewport]')) { root.dataset.viewport = eventObject.target.value; }
        else { capture(); }
    });
    root.addEventListener('click', async eventObject => {
        const target = eventObject.target.closest('button'); if (!target) { return; }
        try {
            if (target.dataset.conflictAction) {
                if (target.dataset.conflictAction === 'use-server') { pending = {}; }
                else { Object.values(pending).forEach(answer => { answer.version = savedAnswers.get(answer.item_key)?.version ?? 0; }); }
                conflicting = false; warning.hidden = true; persist(); render(); await flush(); return;
            }
            if ((activeRecorder?.state === 'recording' || recordingPending) && !target.matches('[data-record]')) { showWarning('Dừng ghi âm trước khi chuyển câu hoặc nộp bài.'); return; }
            if (navigating || submitting || target.disabled) { return; }
            if (target.dataset.question !== undefined || target.dataset.stepQuestion !== undefined) {
                const index = target.dataset.question !== undefined ? Number(target.dataset.question) : selected + Number(target.dataset.stepQuestion);
                if (!config.snapshot.items[index] || !questionAccessible(config.snapshot.items[index], index)) { return; }
                capture();
                if (policies.lock_previous || policies.navigation === 'linear') {
                    navigating = true;
                    try {
                        if (!(await flush()) || Object.keys(pending).length && !config.preview) { showWarning('Đồng bộ đáp án trước khi chuyển câu.'); return; }
                        if (config.preview) { state.current_item_index = index; }
                        else { state = await request(config.endpoints.navigate, { item_key: config.snapshot.items[index].key }); offset = Date.parse(state.server_time) - Date.now(); }
                    } finally { navigating = false; }
                } else { state.current_item_index = index; }
                selected = index; render();
            }
            if (target.matches('[data-submit]')) {
                capture();
                const answered = config.snapshot.items.filter(item => hasResponse(answerFor(item))).length;
                const confirmationMessage = config.preview ? 'Kết thúc preview?' : `Đã trả lời ${answered}/${config.snapshot.items.length} câu; còn ${config.snapshot.items.length - answered} câu chưa trả lời. Nộp bài sẽ khóa câu trả lời.`;
                const confirmed = typeof window.vistiqConfirm === 'function'
                    ? await window.vistiqConfirm(confirmationMessage, { title: 'Xác nhận nộp bài', confirmLabel: 'Nộp bài', danger: false })
                    : window.confirm(confirmationMessage);
                if (!confirmed) { return; }
                capture();
                if (state.status !== 'expired') {
                    await flushRecordings();
                    if (recordings.size || uploadingRecordings) { showWarning('Recording chưa đồng bộ. Kết nối mạng và thử nộp lại.'); return; }
                    if (!(await flush())) { showWarning('Chưa đồng bộ hết đáp án. Hãy kết nối mạng rồi nộp lại.'); return; }
                    if (Object.keys(pending).length && !config.preview) { return; }
                }
                submitting = true;
                if (config.preview) { showWarning('Preview đã kết thúc. Không tạo lượt thi hoặc kết quả.'); submitting = false; }
                else { const data = await request(config.endpoints.submit, {}); localStorage.removeItem(storageKey); location.assign(data.url); }
            }
            if (target.matches('[data-fullscreen]')) { await document.documentElement.requestFullscreen(); }
            if (target.matches('[data-pause]')) {
                capture();
                if (!state.paused && !(await flush())) { return; }
                if (config.preview) { if (state.paused) { state.deadline = new Date(Date.parse(state.deadline) + Date.now() - pausedAt).toISOString(); } else { pausedAt = Date.now(); } state.paused = !state.paused; }
                else { state = await request(state.paused ? config.endpoints.resume : config.endpoints.pause, {}); offset = Date.parse(state.server_time) - Date.now(); }
                render();
            }
            if (target.matches('[data-next-section]')) {
                capture();
                if (!(await flush())) { return; }
                if (config.preview) { state.active_section = Math.min(state.active_section + 1, config.snapshot.sections.length - 1); }
                else { state = await request(config.endpoints.section, {}); offset = Date.parse(state.server_time) - Date.now(); }
                selected = state.current_item_index ?? Math.max(0, config.snapshot.items.findIndex(item => item.section === state.active_section)); render();
            }
            if (target.matches('[data-highlight]')) {
                const selection = window.getSelection(); if (selection.rangeCount && root.querySelector('[data-items]').contains(selection.anchorNode)) { const range = selection.getRangeAt(0); const mark = document.createElement('mark'); try { range.surroundContents(mark); } catch { showWarning('Chọn một đoạn trong cùng đoạn văn để đánh dấu.'); } }
            }
            if (target.matches('[data-play-audio]')) {
                const player = target.parentElement.querySelector('audio');
                if (!player.src && !config.preview) { const data = await request(config.endpoints.audio, { item_key: current().key, ...(target.dataset.stimulus ? { stimulus_id: Number(target.dataset.stimulus) } : {}) }); player.src = data.url; }
                await player.play(); if (policies.strict_audio) { target.disabled = true; player.addEventListener('ended', () => { player.removeAttribute('src'); }, { once: true }); }
                const canvas = target.parentElement.querySelector('canvas'), context = canvas.getContext('2d');
                try { let graph = audioGraphs.get(player); if (!graph) { const audioContext = new AudioContext(), analyser = audioContext.createAnalyser(); audioContext.createMediaElementSource(player).connect(analyser); analyser.connect(audioContext.destination); graph = { audioContext, analyser }; audioGraphs.set(player, graph); } await graph.audioContext.resume(); const analyser = graph.analyser, buffer = new Uint8Array(analyser.frequencyBinCount); const draw = () => { if (player.paused) { return; } analyser.getByteTimeDomainData(buffer); context.clearRect(0, 0, canvas.width, canvas.height); context.beginPath(); buffer.forEach((value, index) => context.lineTo(index / buffer.length * canvas.width, value / 256 * canvas.height)); context.strokeStyle = '#1d7362'; context.stroke(); requestAnimationFrame(draw); }; draw(); } catch { /* Playback remains available if waveform is unsupported. */ }
            }
            if (target.matches('[data-record]')) {
                if (target.recorder?.state === 'recording') { target.recorder.stop(); return; }
                if (recordingPending) { return; }
                const itemKey = current().key; const chunks = []; let stream;
                target.disabled = true; recordingPending = true;
                try { stream = await navigator.mediaDevices.getUserMedia({ audio: true }); }
                finally { target.disabled = false; recordingPending = false; }
                const recorder = new MediaRecorder(stream); activeRecorder = recorder; target.recorder = recorder; target.textContent = 'Dừng ghi âm';
                recorder.ondataavailable = data => chunks.push(data.data);
                recorder.onstop = async () => {
                    stream.getTracks().forEach(track => track.stop()); target.textContent = 'Ghi lại'; const blob = new Blob(chunks, { type: recorder.mimeType });
                    activeRecorder = null;
                    const url = URL.createObjectURL(blob);
                    root.querySelector('[data-recording]')?.setAttribute('src', url);
                    if (config.preview) { return; }
                    if (current()?.key === itemKey) { capture(); }
                    const recording = { id: `${storageKey}:${itemKey}`, attempt: storageKey, itemKey, blob, url };
                    recordings.set(itemKey, recording);
                    try {
                        await recordingStorage('put', { ...recording, url: undefined });
                        status.textContent = 'Recording đã lưu trên thiết bị · chờ đồng bộ';
                    } catch { showWarning('Trình duyệt không cho lưu recording. Giữ trang mở để đồng bộ.'); }
                    await flushRecordings();
                };
                recorder.start();
            }
        } catch (error) { submitting = false; showWarning(error.message); }
    });
    root.addEventListener('click', eventObject => {
        const link = eventObject.target.closest('a[href]');
        if (link && !config.preview && policies.require_fullscreen) {
            eventObject.preventDefault();
            void submitForPolicyExit('FULLSCREEN_EXIT');
        }
    });
    for (const [browserEvent, policy, signal] of [['copy', 'disable_copy', 'COPY_ATTEMPT'], ['paste', 'disable_paste', 'PASTE_ATTEMPT'], ['contextmenu', 'disable_context_menu', 'CONTEXT_MENU']]) {
        root.addEventListener(browserEvent, eventObject => { if (policies[policy]) { eventObject.preventDefault(); event(signal); } });
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && policies.track_focus) {
            if (policies.require_fullscreen) { void submitForPolicyExit('TAB_HIDDEN'); }
            else { event('TAB_HIDDEN'); }
        }
    });
    window.addEventListener('blur', () => {
        if (policies.track_focus) {
            root.classList.toggle('assessment-blurred', !!policies.blur_on_focus_loss);
            if (policies.require_fullscreen) { void submitForPolicyExit('WINDOW_BLUR'); }
            else { event('WINDOW_BLUR'); }
        }
    });
    window.addEventListener('focus', () => root.classList.remove('assessment-blurred'));
    document.addEventListener('fullscreenchange', () => {
        capture(); render();
        if (!document.fullscreenElement && policies.require_fullscreen) { void submitForPolicyExit('FULLSCREEN_EXIT'); }
    });
    window.addEventListener('popstate', () => {
        if (policies.require_fullscreen) { void submitForPolicyExit('FULLSCREEN_EXIT'); }
    });
    window.addEventListener('online', async () => { await refresh(); await flushRecordings(); await flush(); await flushEvents(); });
    window.addEventListener('offline', () => { status.textContent = 'Offline · tiếp tục lưu cục bộ'; event('NETWORK_DISCONNECT'); });
    window.addEventListener('beforeunload', eventObject => { if ((Object.keys(pending).length || recordings.size || activeRecorder?.state === 'recording') && !submitting) { eventObject.preventDefault(); eventObject.returnValue = ''; } });
    setInterval(() => { flushRecordings(); flush(); }, 3000); setInterval(() => { refresh(); flushEvents(); }, 15000);
    setInterval(() => { if (state.paused) { root.querySelector('[data-timer]').textContent = 'Tạm dừng'; return; } const seconds = remainingSeconds(state.deadline, offset); root.querySelector('[data-timer]').textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`; if (seconds === 0 && !config.preview && state.status === 'in_progress') { refresh(); } }, 1000);
    const mobileViewport = matchMedia('(max-width: 600px)');
    const syncViewport = () => { capture(); render(); root.querySelector('[data-items]').hidden = !policies.allow_mobile && mobileViewport.matches; if (!policies.allow_mobile && mobileViewport.matches) { showWarning('Đề yêu cầu tablet hoặc desktop.'); } };
    mobileViewport.addEventListener('change', syncViewport);
    if (!config.preview && typeof BroadcastChannel !== 'undefined') {
        const channel = new BroadcastChannel(storageKey), tabId = crypto.randomUUID(); let signaled = false;
        channel.onmessage = message => { if (message.data.tabId !== tabId && !signaled) { signaled = true; event('MULTIPLE_SESSION'); if (message.data.ping) { channel.postMessage({ tabId }); } } };
        channel.postMessage({ tabId, ping: true }); window.addEventListener('pagehide', () => channel.close());
    }
    render(); root.querySelector('[data-items]').hidden = !policies.allow_mobile && mobileViewport.matches; restoreRecordings(); flush(); event('RELOAD');
    if (!config.preview && policies.require_fullscreen && !document.fullscreenElement && !eventQueue.some(entry => entry.type === 'FULLSCREEN_EXIT')) {
        void submitForPolicyExit('FULLSCREEN_EXIT');
    }
};

initializeExamRunner();
