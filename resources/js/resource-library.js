import '../css/resource-library.css';
import { createIcons, AudioLines, ArrowRight, ChevronLeft, ChevronRight, CircleCheck, CirclePlay, Clock3, CloudUpload, Database, ExternalLink, Eye, File, FileText, FilterX, FolderOpen, FolderSearch, HardDrive, Image, Link, MousePointer2, Package, Pause, Play, Plus, RefreshCw, Save, Search, SquarePen, Trash2, Unlink, Upload, UserRound, Volume2, VolumeX, X } from 'lucide';

const icons = { AudioLines, ArrowRight, ChevronLeft, ChevronRight, CircleCheck, CirclePlay, Clock3, CloudUpload, Database, ExternalLink, Eye, File, FileText, FilterX, FolderOpen, FolderSearch, HardDrive, Image, Link, MousePointer2, Package, Pause, Play, Plus, RefreshCw, Save, Search, SquarePen, Trash2, Unlink, Upload, UserRound, Volume2, VolumeX, X };
const refreshIcons = () => createIcons({ icons, nameAttr: 'data-resource-icon', attrs: { 'aria-hidden': 'true' } });
refreshIcons();

let activeLibrary;
let listRequest;
let searchTimer;
let mutationBusy = false;
let retryUrl;

const stopListRequest = () => {
    clearTimeout(searchTimer);
    listRequest?.abort();
    listRequest = null;
    activeLibrary?.element.removeAttribute('aria-busy');
};
const notice = (message, error = false, retry = null) => {
    const element = activeLibrary.element.querySelector('[data-resource-notice]');
    element.hidden = false;
    element.classList.toggle('is-error', error);
    element.querySelector('[data-resource-notice-text]').textContent = message;
    element.querySelector('[data-resource-retry]').hidden = !retry;
    retryUrl = retry;
};
const requestJson = async (url, options = {}) => {
    let response;
    try {
        response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new Error('Không thể kết nối máy chủ. Vui lòng thử lại.');
    }
    if ([401, 419].includes(response.status)) throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
    if (!response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('Phiên đăng nhập đã hết hạn hoặc máy chủ không phản hồi. Vui lòng đăng nhập lại.');
    }
    const result = await response.json();
    if (!response.ok) throw new Error(Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'Không thể thực hiện thao tác.');
    return result;
};
const navigate = async (url, { historyMode = 'push', message = '', selectedId } = {}) => {
    if (mutationBusy) return;
    stopListRequest();
    const controller = new AbortController();
    listRequest = controller;
    const previous = activeLibrary;
    previous.element.setAttribute('aria-busy', 'true');
    const focused = document.activeElement;
    const focusName = focused?.closest('[data-resource-filters]') ? focused.name : null;
    const caret = focusName === 'q' ? [focused.selectionStart, focused.selectionEnd] : null;
    try {
        const result = await requestJson(url, { signal: controller.signal });
        if (controller.signal.aborted) return;
        const document = new DOMParser().parseFromString(result.html, 'text/html');
        const replacement = document.querySelector('[data-resource-library]');
        if (!replacement) throw new Error('Không thể cập nhật danh sách tài nguyên.');
        const chosenId = selectedId ?? previous.selectedId;
        previous.dispose();
        previous.element.replaceWith(replacement);
        activeLibrary = initializeLibrary(replacement, { selectedId: chosenId, closed: previous.closed });
        const nextUrl = result.url || url;
        if (historyMode === 'push' && new URL(nextUrl, location.href).href !== location.href) history.pushState(null, '', nextUrl);
        else if (historyMode === 'replace') history.replaceState(null, '', nextUrl);
        if (focusName) {
            const field = replacement.querySelector('[data-resource-filters]').elements[focusName];
            field?.focus({ preventScroll: true });
            if (caret) field?.setSelectionRange(...caret);
        }
        if (message) notice(message);
    } catch (error) {
        if (!controller.signal.aborted) notice(`${message ? `${message} ` : ''}${error.message}`, true, url);
    } finally {
        if (listRequest === controller) {
            activeLibrary.element.removeAttribute('aria-busy');
            listRequest = null;
        }
    }
};

function initializeLibrary(library, { selectedId = null, closed = false } = {}) {
    const filters = library.querySelector('[data-resource-filters]');
    const detail = library.querySelector('[data-resource-detail]');
    const detailBody = library.querySelector('[data-resource-detail-body]');
    const workspace = library.querySelector('.resource-workspace');
    const deleteForm = library.querySelector('[data-resource-delete-form]');
    const selections = [...library.querySelectorAll('[data-resource-select]')];
    const selectAll = library.querySelector('[data-resource-select-all]');
    let currentId = null;
    let disposePlayer = () => {};
    const filterUrl = () => {
        const url = new URL(filters.action);
        new FormData(filters).forEach((value, key) => { if (value !== '') url.searchParams.set(key, value); });
        return url.href;
    };
    filters.addEventListener('submit', event => {
        event.preventDefault();
        navigate(filterUrl());
    });
    filters.elements.q.addEventListener('input', event => {
        if (event.isComposing || mutationBusy) return;
        stopListRequest();
        searchTimer = setTimeout(() => navigate(filterUrl(), { historyMode: 'replace' }), 350);
    });
    filters.elements.q.addEventListener('compositionend', () => filters.requestSubmit());

    const setupPlayer = () => {
        const player = detailBody.querySelector('[data-resource-player]');
        if (!player) return;
        const audio = player.querySelector('audio');
        const play = player.querySelector('[data-resource-play]');
        const seek = player.querySelector('[data-resource-seek]');
        const mute = player.querySelector('[data-resource-mute]');
        const canvas = player.querySelector('canvas');
        const context = canvas.getContext('2d');
        const abort = new AbortController();
        let audioContext;
        let peaks = null;
        let decoding = false;
        const formatTime = seconds => Number.isFinite(seconds) ? `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(Math.floor(seconds % 60)).padStart(2, '0')}` : '--:--';
        const draw = () => {
            context.clearRect(0, 0, canvas.width, canvas.height);
            const progress = audio.duration ? audio.currentTime / audio.duration : 0;
            if (!peaks) {
                context.fillStyle = '#c7d7ff';
                context.fillRect(0, canvas.height / 2, canvas.width, 2);
                return;
            }
            peaks.forEach((peak, index) => {
                context.fillStyle = index / peaks.length <= progress ? '#3265ff' : '#b9cdff';
                const height = Math.max(3, peak * (canvas.height - 8));
                context.fillRect(index * 5, (canvas.height - height) / 2, 2, height);
            });
        };
        const decodeWave = async () => {
            if (decoding || peaks || (!window.OfflineAudioContext && !window.AudioContext)) return;
            decoding = true;
            const timeout = window.setTimeout(() => abort.abort(), 12000);
            try {
                const response = await fetch(player.dataset.resourceWaveUrl, { signal: abort.signal });
                if (!response.ok) return;
                const bytes = await response.arrayBuffer();
                if (abort.signal.aborted) return;
                audioContext = window.OfflineAudioContext ? new OfflineAudioContext(1, 1, 44100) : new AudioContext();
                const buffer = await audioContext.decodeAudioData(bytes);
                if (abort.signal.aborted) return;
                const data = buffer.getChannelData(0);
                const count = Math.floor(canvas.width / 5);
                peaks = Array.from({ length: count }, (_, index) => {
                    const start = Math.floor(index * data.length / count);
                    const end = Math.floor((index + 1) * data.length / count);
                    let peak = 0;
                    for (let sample = start; sample < end; sample++) peak = Math.max(peak, Math.abs(data[sample]));
                    return peak;
                });
                draw();
            } catch {
                // Keep playback available when a file cannot be decoded for preview.
            } finally {
                window.clearTimeout(timeout);
                audioContext?.close?.()?.catch(() => {});
            }
        };
        const syncTime = () => {
            player.querySelector('[data-resource-time]').textContent = `${formatTime(audio.currentTime)} / ${formatTime(audio.duration)}`;
            seek.disabled = !Number.isFinite(audio.duration);
            seek.value = audio.duration ? audio.currentTime / audio.duration * 100 : 0;
            const duration = detailBody.querySelector('[data-resource-duration]');
            if (duration && Number.isFinite(audio.duration)) duration.textContent = formatTime(audio.duration);
            draw();
        };
        const syncPlay = () => {
            const playing = !audio.paused;
            play.innerHTML = `<i data-resource-icon="${playing ? 'pause' : 'play'}"></i>`;
            play.title = play.ariaLabel = playing ? 'Tạm dừng audio' : 'Phát audio';
            refreshIcons();
        };
        play.addEventListener('click', async () => {
            if (!audio.paused) { audio.pause(); return; }
            try { await audio.play(); decodeWave(); } catch { player.querySelector('[data-resource-player-error]').hidden = false; }
        });
        mute.addEventListener('click', () => {
            audio.muted = !audio.muted;
            mute.innerHTML = `<i data-resource-icon="${audio.muted ? 'volume-x' : 'volume-2'}"></i>`;
            mute.title = mute.ariaLabel = audio.muted ? 'Bật âm' : 'Tắt âm';
            refreshIcons();
        });
        seek.addEventListener('input', () => { if (Number.isFinite(audio.duration)) audio.currentTime = Number(seek.value) * audio.duration / 100; });
        audio.addEventListener('loadedmetadata', syncTime);
        audio.addEventListener('timeupdate', syncTime);
        ['play', 'pause', 'ended'].forEach(event => audio.addEventListener(event, syncPlay));
        audio.addEventListener('error', () => { player.querySelector('[data-resource-player-error]').hidden = false; });
        audio.hidden = true;
        player.querySelector('.resource-custom-player').hidden = false;
        draw();
        disposePlayer = () => { audio.pause(); abort.abort(); audio.removeAttribute('src'); audio.load(); };
    };
    const openDetail = (id, edit = false, scroll = true) => {
        const template = library.querySelector(`[data-resource-template="${id}"]`);
        if (!template) return;
        if (currentId !== id) {
            disposePlayer();
            disposePlayer = () => {};
            detailBody.replaceChildren(template.content.cloneNode(true));
            currentId = id;
            setupPlayer();
            const image = detailBody.querySelector('.resource-image-preview img');
            image?.addEventListener('error', () => {
                image.hidden = true;
                detailBody.querySelector('[data-resource-image-error]').hidden = false;
            });
        }
        detail.hidden = false;
        workspace.classList.remove('is-detail-closed');
        library.querySelectorAll('[data-resource-row]').forEach(row => row.classList.toggle('is-active', row.dataset.resourceRow === id));
        const editForm = detailBody.querySelector('[data-resource-edit-form]');
        if (editForm) editForm.hidden = !edit;
        refreshIcons();
        if (edit && editForm) {
            editForm.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            editForm.querySelector('input').focus({ preventScroll: true });
        } else if (scroll && window.matchMedia('(max-width: 1050px)').matches) {
            detail.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    };
    const syncSelection = () => {
        const count = selections.filter(input => input.checked).length;
        library.querySelector('[data-resource-bulk]')?.toggleAttribute('hidden', count === 0);
        const label = library.querySelector('[data-resource-bulk-label]');
        if (label) label.textContent = `Đã chọn ${count} tài nguyên`;
        if (selectAll) {
            selectAll.checked = count > 0 && count === selections.length;
            selectAll.indeterminate = count > 0 && count < selections.length;
        }
        selections.forEach(input => input.closest('tr').classList.toggle('is-selected', input.checked));
    };
    selections.forEach(input => input.addEventListener('change', syncSelection));
    selectAll?.addEventListener('change', () => { selections.forEach(input => { input.checked = selectAll.checked; }); syncSelection(); });
    library.querySelector('[data-resource-clear-selection]')?.addEventListener('click', () => { selections.forEach(input => { input.checked = false; }); syncSelection(); });

    let confirmingDeletion = false;
    deleteForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (mutationBusy || confirmingDeletion) return;
        const count = selections.filter(input => input.checked).length;
        if (!count) return;
        stopListRequest();
        confirmingDeletion = true;
        const confirmed = await window.vistiqConfirm(`Xóa ${count} tài nguyên đã chọn? Tài nguyên đang được sử dụng sẽ được giữ lại.`, { title: 'Xóa tài nguyên', confirmLabel: 'Xóa tài nguyên' });
        confirmingDeletion = false;
        if (!confirmed || activeLibrary.element !== library) return;
        mutationBusy = true;
        deleteForm.setAttribute('aria-busy', 'true');
        try {
            const result = await requestJson(deleteForm.action, { method: 'POST', body: new FormData(deleteForm) });
            mutationBusy = false;
            await navigate(location.href, { historyMode: 'replace', message: result.message });
        } catch (error) {
            notice(error.message, true);
        } finally {
            mutationBusy = false;
            deleteForm.removeAttribute('aria-busy');
        }
    });
    library.addEventListener('submit', async event => {
        const form = event.target.closest('[data-resource-edit-form]');
        if (!form) return;
        event.preventDefault();
        if (mutationBusy) return;
        stopListRequest();
        mutationBusy = true;
        const data = new FormData(form);
        const controls = [...form.querySelectorAll('input:not([type=hidden]), textarea, button')];
        const error = form.querySelector('[data-resource-edit-error]');
        error.hidden = true;
        controls.forEach(control => { control.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        try {
            const result = await requestJson(form.action, { method: 'POST', body: data });
            mutationBusy = false;
            await navigate(location.href, { historyMode: 'replace', message: result.message, selectedId: String(result.id) });
        } catch (exception) {
            error.textContent = exception.message;
            error.hidden = false;
        } finally {
            mutationBusy = false;
            controls.forEach(control => { control.disabled = false; });
            form.removeAttribute('aria-busy');
        }
    });
    library.addEventListener('click', event => {
        const link = event.target.closest('.resource-pagination a, .resource-reset');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
            event.preventDefault();
            if (link.getAttribute('aria-disabled') !== 'true') navigate(link.href);
            return;
        }
        if (event.target.closest('[data-resource-retry]')) { navigate(retryUrl, { historyMode: 'replace' }); return; }
        if (mutationBusy) return;
        const opener = event.target.closest('[data-resource-open]');
        const editor = event.target.closest('[data-resource-edit]');
        const deletion = event.target.closest('[data-resource-delete]');
        const tab = event.target.closest('[data-resource-type]');
        const category = event.target.closest('[data-resource-category]');
        if (opener) {
            openDetail(opener.dataset.resourceOpen);
            if (opener.hasAttribute('data-resource-show-questions')) detailBody.querySelector('[data-resource-questions]').scrollIntoView({ block: 'nearest' });
        }
        if (editor) openDetail(editor.dataset.resourceEdit, true);
        if (deletion) {
            selections.forEach(input => { input.checked = input.value === deletion.dataset.resourceDelete; });
            syncSelection();
            deleteForm.requestSubmit();
        }
        if (tab || category) {
            filters.elements[tab ? 'type' : 'category'].value = tab ? tab.dataset.resourceType : category.dataset.resourceCategory;
            filters.requestSubmit();
        }
        if (event.target.closest('[data-resource-close]')) {
            disposePlayer();
            currentId = null;
            detail.hidden = true;
            workspace.classList.add('is-detail-closed');
            library.querySelectorAll('[data-resource-row]').forEach(row => row.classList.remove('is-active'));
        }
        if (event.target.closest('[data-resource-edit-cancel]')) detailBody.querySelector('[data-resource-edit-form]').hidden = true;
    });
    library.querySelectorAll('[data-resource-auto-submit]').forEach(input => input.addEventListener('change', () => filters.requestSubmit()));
    library.querySelector('[data-resource-page-size]').addEventListener('change', event => { filters.elements.per_page.value = event.target.value; filters.requestSubmit(); });

    const uploadDialog = library.querySelector('[data-resource-upload-dialog]');
    let pendingFiles = [];
    let totalFiles = 0;
    let uploadDirty = false;
    library.querySelector('[data-resource-upload]')?.addEventListener('click', () => uploadDialog.showModal());
    library.querySelectorAll('[data-resource-upload-close]').forEach(button => button.addEventListener('click', () => uploadDialog.close()));
    library.querySelector('[data-resource-upload-file]')?.addEventListener('change', event => {
        pendingFiles = [...event.target.files];
        totalFiles = pendingFiles.length;
        const file = pendingFiles[0];
        if (!file) return;
        library.querySelector('[data-resource-upload-title]').value = file.name;
        library.querySelector('[data-resource-upload-transcript]').hidden = !file.type.startsWith('audio/');
        library.querySelector('[data-resource-upload-progress]').textContent = totalFiles > 1 ? `${totalFiles} tệp đã chọn` : '';
    });
    library.querySelector('[data-resource-upload-form]')?.addEventListener('submit', async event => {
        event.preventDefault();
        if (mutationBusy) return;
        stopListRequest();
        mutationBusy = true;
        const form = event.target;
        const submit = form.querySelector('[type="submit"]');
        const error = form.querySelector('[data-resource-upload-error]');
        const editable = [...form.querySelectorAll('input:not([type=hidden]), textarea, [data-resource-upload-close]')];
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        error.hidden = true;
        try {
            const originalData = new FormData(form);
            editable.forEach(control => { control.disabled = true; });
            while (pendingFiles.length) {
                const file = pendingFiles[0];
                const data = new FormData();
                originalData.forEach((value, key) => data.append(key, value));
                data.set('file', file);
                if (totalFiles > 1) data.set('title', file.name);
                if (!file.type.startsWith('audio/')) data.delete('transcript');
                library.querySelector('[data-resource-upload-progress]').textContent = `Đang tải ${totalFiles - pendingFiles.length + 1} / ${totalFiles}: ${file.name}`;
                await requestJson(form.action, { method: 'POST', body: data });
                pendingFiles.shift();
                uploadDirty = true;
            }
            uploadDirty = false;
            uploadDialog.close();
            form.reset();
            form.querySelector('[data-resource-upload-progress]').textContent = '';
            form.querySelector('[data-resource-upload-transcript]').hidden = true;
            mutationBusy = false;
            await navigate(location.href, { historyMode: 'replace', message: `Đã tải lên ${totalFiles} tài nguyên.` });
        } catch (exception) {
            error.textContent = exception.message;
            error.hidden = false;
        } finally {
            mutationBusy = false;
            submit.disabled = false;
            editable.forEach(control => { control.disabled = false; });
            form.removeAttribute('aria-busy');
        }
    });
    uploadDialog?.addEventListener('cancel', event => {
        if (uploadDialog.querySelector('form').getAttribute('aria-busy') === 'true') event.preventDefault();
    });
    uploadDialog?.addEventListener('close', () => {
        if (uploadDirty && !mutationBusy) {
            uploadDirty = false;
            navigate(location.href, { historyMode: 'replace', message: 'Đã tải lên các tài nguyên thành công.' });
        }
    });
    if (closed) {
        detail.hidden = true;
        workspace.classList.add('is-detail-closed');
    } else {
        const chosen = (selectedId && library.querySelector(`[data-resource-row="${selectedId}"]`)) || library.querySelector('[data-resource-row]');
        if (chosen) openDetail(chosen.dataset.resourceRow, false, false);
    }
    refreshIcons();
    return { element: library, get selectedId() { return currentId; }, get closed() { return detail.hidden; }, dispose() { disposePlayer(); } };
}

const library = document.querySelector('[data-resource-library]');
if (library) {
    activeLibrary = initializeLibrary(library);
    window.addEventListener('popstate', () => navigate(location.href, { historyMode: 'replace' }));
    window.addEventListener('pagehide', () => { stopListRequest(); activeLibrary.dispose(); });
}

const resourceEditor = document.querySelector('[data-resource-editor]');
if (resourceEditor) {
    const syncSelection = () => {
        const form = resourceEditor.querySelector('[data-resource-question-form]');
        const rows = [...resourceEditor.querySelectorAll('[data-resource-question-select]')];
        const selectAll = resourceEditor.querySelector('[data-resource-select-all-questions]');
        const selectionLabel = resourceEditor.querySelector('[data-resource-question-selection]');
        const count = rows.filter(input => input.checked).length;
        if (selectionLabel) selectionLabel.textContent = count ? `${count} câu đã chọn` : 'Chọn tối đa 100 câu';
        if (selectAll) {
            selectAll.checked = rows.length > 0 && count === rows.length;
            selectAll.indeterminate = count > 0 && count < rows.length;
        }
    };
    document.addEventListener('change', event => {
        const selectAll = event.target.closest('[data-resource-select-all-questions]');
        if (resourceEditor.contains(event.target) && selectAll) {
            resourceEditor.querySelectorAll('[data-resource-question-select]').forEach(input => { input.checked = selectAll.checked; });
            syncSelection();
        } else if (resourceEditor.contains(event.target) && event.target.closest('[data-resource-question-select]')) {
            syncSelection();
        }
    });
    document.addEventListener('submit', event => {
        const form = event.target.closest('[data-resource-question-form]');
        if (!form || !resourceEditor.contains(form)) return;
        const rows = [...form.querySelectorAll('[data-resource-question-select]')];
        const selectionLabel = resourceEditor.querySelector('[data-resource-question-selection]');
        const count = rows.filter(input => input.checked).length;
        if (!count || count > 100) {
            event.preventDefault();
            selectionLabel.textContent = count > 100 ? 'Chỉ có thể cập nhật tối đa 100 câu mỗi lần.' : 'Chọn ít nhất một câu hỏi.';
        }
    });
    document.addEventListener('vistiq:ajax-results-updated', event => {
        if (event.detail.targets?.some(target => target.id === 'resource-question-results')) syncSelection();
    });
    syncSelection();
}
