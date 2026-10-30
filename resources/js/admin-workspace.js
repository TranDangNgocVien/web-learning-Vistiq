import { requestAdminConfirmation } from './confirmation-dialog.js';
import './exam-builder';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const encode = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);

document.querySelectorAll('[data-admin-toast]').forEach(toast => {
    const dismiss = () => {
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 240);
    };

    toast.querySelector('[data-dismiss-admin-toast]')?.addEventListener('click', dismiss);
    const timeout = Number(toast.dataset.adminToastTimeout ?? 6000);
    if (timeout > 0) {
        window.setTimeout(dismiss, timeout);
    }
});

if (document.querySelector('[data-rich-editor]')) import('./rich-text-editor.js');

document.querySelectorAll('[data-resource-picker]').forEach(picker => {
    const search = picker.querySelector('[data-resource-search]');
    const select = picker.querySelector('[data-resource-select]');
    const emptyState = picker.querySelector('[data-resource-empty]');
    const options = select ? [...select.options] : [];

    if (!(search instanceof HTMLInputElement) || !(select instanceof HTMLSelectElement)) {
        return;
    }

    const filterResources = () => {
        const term = search.value.trim().toLocaleLowerCase('vi');
        let visibleCount = 0;

        options.forEach(option => {
            const matches = !term || option.textContent.trim().toLocaleLowerCase('vi').includes(term);
            option.hidden = !matches;
            visibleCount += matches ? 1 : 0;
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    };

    search.addEventListener('input', filterResources);
    filterResources();
});

document.querySelectorAll('[data-unsaved-form]').forEach(form => {
    let dirty = false, saving = false, queuedSubmit = false, lastSaveSucceeded = false;
    form.addEventListener('input', () => { dirty = true; }); form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', event => {
        if (form.dataset.mediaUploading === 'true') {
            event.preventDefault();
            const status = form.querySelector('[data-media-upload-status]');
            if (status) { status.textContent = 'Đợi upload audio hoàn tất trước khi lưu biểu mẫu.'; }
            return;
        }

        if (!saving) {
            dirty = false;
            return;
        }

        event.preventDefault();
        queuedSubmit = true;
        const status = form.querySelector('[data-autosave-status]') ?? document.querySelector('[data-autosave-status]');
        if (status) { status.textContent = 'Đang hoàn tất lần tự lưu trước…'; }
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    if (!form.hasAttribute('data-autosave')) { return; }
    setInterval(async () => {
        if (!form.hasAttribute('data-autosave') || !dirty || saving || !navigator.onLine) { return; }
        saving = true; lastSaveSucceeded = false;
        const data = new FormData(form), sent = new URLSearchParams(data).toString(), status = form.querySelector('[data-autosave-status]') ?? document.querySelector('[data-autosave-status]');
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            const result = await response.json(); if (!response.ok) { throw new Error(result.message ?? 'Không thể lưu'); }
            if (new URLSearchParams(new FormData(form)).toString() === sent) { dirty = false; }
            form.querySelector('[name="revision_counter"]').value = result.revision_counter; lastSaveSucceeded = true; if (status) { status.textContent = 'Đã lưu draft'; }
        } catch (error) { if (status) { status.textContent = error.message; } form.removeAttribute('data-autosave'); }
        finally {
            saving = false;
            if (queuedSubmit) {
                queuedSubmit = false;
                if (lastSaveSucceeded) {
                    dirty = false;
                    HTMLFormElement.prototype.submit.call(form);
                }
            }
        }
    }, 5000);
});

document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || event.defaultPrevented) {
        return;
    }
    if (form.dataset.confirmApproved === 'true') {
        delete form.dataset.confirmApproved;
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    if (form.dataset.confirmPending === 'true') {
        return;
    }

    form.dataset.confirmPending = 'true';
    const confirmed = await requestAdminConfirmation(form.dataset.confirm, {
        title: form.dataset.confirmTitle,
        confirmLabel: form.dataset.confirmLabel,
        danger: form.dataset.confirmTone !== 'safe',
    });
    delete form.dataset.confirmPending;

    if (!confirmed || !form.isConnected) {
        return;
    }

    form.dataset.confirmApproved = 'true';
    if (event.submitter instanceof HTMLElement) {
        form.requestSubmit(event.submitter);
    } else {
        form.requestSubmit();
    }
    form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])').forEach(control => {
        if (form.dataset.pendingLabel) control.textContent = form.dataset.pendingLabel;
        control.disabled = true;
        control.setAttribute('aria-busy', 'true');
    });
}, true);

const audioDeleteForm = document.querySelector('[data-audio-delete-form]');
if (audioDeleteForm) {
    const audioSelectAll = audioDeleteForm.querySelector('[data-audio-select-all]');
    const audioSelectAllValue = audioDeleteForm.querySelector('[data-audio-select-all-value]');
    const audioSelectAllLabel = audioDeleteForm.querySelector('[data-audio-select-all-label]');
    const audioSelectionCount = audioDeleteForm.querySelector('[data-audio-selection-count]');
    const audioDeleteSelected = audioDeleteForm.querySelector('[data-audio-delete-selected]');
    const audioSingleId = audioDeleteForm.querySelector('[data-audio-single-id]');
    let audioTotal = Number(audioDeleteForm.dataset.audioTotal || 0);
    let audioAllSelectionActive = false;
    const syncAudioSelection = () => {
        const audioSelections = [...audioDeleteForm.querySelectorAll('[data-audio-selection]')];
        const selected = audioSelections.filter(input => input.checked);
        audioDeleteSelected.disabled = selected.length === 0;
        audioSelectAll.checked = audioAllSelectionActive;
        audioSelectAll.indeterminate = !audioAllSelectionActive && selected.length > 0;
        audioSelectAllValue.disabled = !audioAllSelectionActive;
        audioSelectAllLabel.textContent = audioAllSelectionActive ? 'Đã chọn tất cả' : 'Chọn tất cả';
        audioSelectionCount.textContent = `${audioAllSelectionActive ? audioTotal : selected.length} đã chọn`;
        audioDeleteSelected.textContent = audioAllSelectionActive ? 'Xóa tất cả audio' : 'Xóa audio đã chọn';
    };

    audioDeleteForm.addEventListener('change', event => {
        const audioSelections = [...audioDeleteForm.querySelectorAll('[data-audio-selection]')];
        if (event.target.matches('[data-audio-selection]')) {
            if (audioAllSelectionActive && !audioSelections.every(selection => selection.checked)) {
                audioAllSelectionActive = false;
            }
            syncAudioSelection();
        }
        if (event.target.matches('[data-audio-select-all]')) {
            audioAllSelectionActive = audioSelectAll.checked;
            audioSelections.forEach(input => { input.checked = audioSelectAll.checked; });
            syncAudioSelection();
        }
    });

    audioDeleteForm.addEventListener('click', event => {
        const button = event.target.closest('[data-audio-delete-single]');
        if (!button) {
            return;
        }

        audioSingleId.disabled = false;
        audioSingleId.value = button.dataset.audioDeleteSingle;
        audioAllSelectionActive = false;
        audioDeleteForm.querySelectorAll('[data-audio-selection]').forEach(input => { input.checked = false; });
        syncAudioSelection();
        audioDeleteForm.requestSubmit();
    });

    document.addEventListener('audio-library-updated', () => {
        audioAllSelectionActive = false;
        audioTotal = Number(audioDeleteForm.dataset.audioTotal || 0);
        audioSingleId.disabled = true;
        audioSingleId.value = '';
        syncAudioSelection();
    });

    audioDeleteForm.addEventListener('submit', async event => {
        event.preventDefault();
        const selected = audioSingleId.disabled
            ? audioSelections.filter(input => input.checked).map(input => input.value)
            : [audioSingleId.value];
        const selectAll = !audioSingleId.disabled ? false : !audioSelectAllValue.disabled;

        if (selected.length === 0 || selected[0] === '') {
            alert('Hãy chọn ít nhất một audio cần xóa.');
            return;
        }

        if (audioDeleteForm.dataset.confirmPending === 'true') {
            return;
        }

        audioDeleteForm.dataset.confirmPending = 'true';
        const isSingle = selected.length === 1 && !selectAll;
        const confirmed = await requestAdminConfirmation(
            isSingle
                ? 'Audio này sẽ bị xóa khỏi thư viện nếu chưa được dùng trong câu hỏi hoặc nhóm.'
                : selectAll
                    ? 'Toàn bộ audio trong thư viện hiện tại sẽ bị xóa nếu chưa được dùng trong câu hỏi hoặc nhóm.'
                    : `${selected.length} audio sẽ bị xóa khỏi thư viện nếu chưa được dùng trong câu hỏi hoặc nhóm.`,
            { title: 'Xác nhận xóa audio', confirmLabel: isSingle ? 'Xóa audio' : (selectAll ? 'Xóa tất cả audio' : 'Xóa các audio đã chọn') },
        );
        delete audioDeleteForm.dataset.confirmPending;

        if (!confirmed) {
            audioSingleId.value = '';
            audioSingleId.disabled = true;
            audioAllSelectionActive = false;
            syncAudioSelection();
            return;
        }

        audioDeleteForm.querySelectorAll('button, input').forEach(control => { control.disabled = true; });
        HTMLFormElement.prototype.submit.call(audioDeleteForm);
    });
}

const questionBulkForm = document.querySelector('#question-bulk');
const questionBulkEndpoint = questionBulkForm?.getAttribute('action');
const questionApprovalForm = document.querySelector('#question-approval-bulk');
const questionReviewRevisionsElement = questionBulkForm?.querySelector('[data-question-review-revisions]');
let questionReviewRevisions = questionReviewRevisionsElement ? JSON.parse(questionReviewRevisionsElement.textContent) : {};
questionBulkForm?.querySelector('[data-approve-selected-questions]')?.addEventListener('click', async event => {
    const selectedIds = selectingAllMatchingQuestions
        ? [...questionBulkForm.querySelectorAll('input[name="matching_ids[]"]')].map(input => input.value)
        : [...questionBulkForm.querySelectorAll('input[form="question-bulk"][name="ids[]"]:checked')].map(input => input.value);
    const reviewableIds = [...new Set(selectedIds)].filter(id => questionReviewRevisions[id]);
    const button = event.currentTarget;

    if (reviewableIds.length === 0) {
        alert('Chọn ít nhất một câu ở trạng thái Chờ duyệt.');
        return;
    }
    if (reviewableIds.length > 100) {
        alert('Mỗi lượt duyệt tối đa 100 câu. Hãy chọn câu trên trang hiện tại hoặc thu hẹp bộ lọc.');
        return;
    }
    if (button.dataset.confirmPending === 'true') return;
    button.dataset.confirmPending = 'true';
    const confirmed = await requestAdminConfirmation(`Duyệt ${reviewableIds.length} câu hỏi đang chờ? Các câu hợp lệ sẽ chuyển sang trạng thái Đã duyệt.`, {
        title: 'Xác nhận duyệt câu hỏi',
        confirmLabel: `Duyệt ${reviewableIds.length} câu`,
        danger: false,
    });
    delete button.dataset.confirmPending;
    if (!confirmed || !questionApprovalForm) return;

    questionApprovalForm.querySelectorAll('input[name="ids[]"], input[name^="revision_ids["]').forEach(input => input.remove());
    reviewableIds.forEach(id => {
        const questionInput = document.createElement('input');
        questionInput.type = 'hidden';
        questionInput.name = 'ids[]';
        questionInput.value = id;
        const revisionInput = document.createElement('input');
        revisionInput.type = 'hidden';
        revisionInput.name = `revision_ids[${id}]`;
        revisionInput.value = questionReviewRevisions[id];
        questionApprovalForm.append(questionInput, revisionInput);
    });
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Đang duyệt…';
    questionApprovalForm.requestSubmit();
});

document.addEventListener('submit', event => {
    const form = event.target.closest('form[data-thpt-review-single]');
    if (!form) return;
    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    const button = form.querySelector('[data-thpt-review-single-submit]');
    if (button) {
        button.disabled = true;
        button.textContent = form.dataset.pendingLabel || 'Đang gửi duyệt…';
    }
});

questionBulkForm?.querySelector('[data-delete-selected-questions]')?.addEventListener('click', () => {
    if (!document.querySelector('input[form="question-bulk"][name="ids[]"]:checked') && !questionBulkForm.querySelector('input[name="matching_ids[]"]')) {
        alert('Hãy chọn ít nhất một câu hỏi cần xóa.');
        return;
    }

    questionBulkForm.querySelector('[name="action"]').value = 'delete_unused';
    questionBulkForm.requestSubmit();
});
questionBulkForm?.addEventListener('submit', async event => {
    const action = questionBulkForm.querySelector('[name="action"]');
    if (action.value !== 'delete_unused') {
        return;
    }

    event.preventDefault();

    const initialData = new FormData(questionBulkForm);
    const questionIds = [...new Set([...initialData.entries()]
        .filter(([name]) => name === 'ids[]' || name === 'matching_ids[]')
        .map(([, value]) => String(value)))];
    const progress = document.querySelector('[data-delete-progress]');
    const progressBar = progress?.querySelector('[data-delete-progress-bar]');
    const progressLabel = progress?.querySelector('[data-delete-progress-label]');
    const progressCount = progress?.querySelector('[data-delete-progress-count]');

    if (questionIds.length === 0) {
        action.value = '';
        alert('Hãy chọn ít nhất một câu hỏi cần xóa.');
        return;
    }

    if (questionBulkForm.dataset.confirmPending === 'true') {
        return;
    }

    questionBulkForm.dataset.confirmPending = 'true';
    const confirmed = await requestAdminConfirmation(`Xử lý ${questionIds.length} câu đã chọn theo trạng thái đề. Câu trong đề nháp sẽ được gỡ khỏi đề rồi xóa mềm. Câu trong đề đã phát hành sẽ được ngừng sử dụng nhưng vẫn giữ trong đề. Lịch sử làm bài được giữ nguyên.`, {
        title: 'Xác nhận xóa câu hỏi',
        confirmLabel: `Xóa / ngừng dùng ${questionIds.length} câu`,
    });
    delete questionBulkForm.dataset.confirmPending;

    if (!confirmed) {
        action.value = '';
        return;
    }

    if (progress) {
        progress.hidden = false;
    }

    questionBulkForm.querySelectorAll('button, input, select').forEach(control => { control.disabled = true; });
    document.querySelectorAll('input[form="question-bulk"][name="ids[]"], [data-select-all]').forEach(control => { control.disabled = true; });

    const operationKey = crypto.randomUUID();
    const batchSize = 10;
    const deleteNextBatch = async offset => {
        const batchIds = questionIds.slice(offset, offset + batchSize);
        const batchData = new FormData();
        initialData.forEach((value, name) => batchData.append(name, value));
        ['ids[]', 'matching_ids[]', 'operation_key', 'delete_offset', 'selection_total', 'final_batch'].forEach(name => batchData.delete(name));
        batchIds.forEach(id => batchData.append('ids[]', id));
        batchData.append('action', 'delete_unused');
        batchData.append('operation_key', operationKey);
        batchData.append('delete_offset', String(offset));
        batchData.append('selection_total', String(questionIds.length));
        const isFinalBatch = offset + batchIds.length === questionIds.length;
        batchData.append('final_batch', isFinalBatch ? '1' : '0');

        const response = await fetch(questionBulkEndpoint, {
            method: 'POST',
            body: batchData,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.message ?? 'Không thể xóa câu hỏi.');
        }

        const percent = Math.round((result.processed / result.total) * 100);
        if (progressBar) {
            progressBar.value = percent;
        }
        if (progressCount) {
            progressCount.textContent = `${percent}%`;
        }
        if (progressLabel) {
            progressLabel.textContent = `Đã xử lý ${result.processed}/${result.total} câu · xóa mềm ${result.deleted}, ngừng dùng ${result.disabled ?? 0}, bỏ qua ${result.skipped}`;
        }

        if (result.completed) {
            window.location.reload();
            return;
        }

        await deleteNextBatch(offset + batchIds.length);
    };

    deleteNextBatch(0).catch(error => {
        if (progressLabel) {
            progressLabel.textContent = `${error.message} Hãy tải lại trang để kiểm tra kết quả.`;
        }
        if (progressCount) {
            progressCount.textContent = 'Có lỗi';
        }
        if (progress) {
            progress.setAttribute('data-delete-error', 'true');
        }
    });
});

let questionSearchTimer;

document.querySelectorAll('[data-question-search]').forEach(form => {
    const search = form.querySelector('[name="q"]');

    if (!form.matches('[data-ajax-filter-form]')) {
        search.addEventListener('input', () => {
            window.clearTimeout(questionSearchTimer);
            questionSearchTimer = window.setTimeout(() => form.requestSubmit(), 450);
        });
    }

    form.addEventListener('submit', () => window.clearTimeout(questionSearchTimer));
});

document.querySelectorAll('[data-question-filters]').forEach(form => {
    const catalog = JSON.parse(form.querySelector('[data-question-filter-catalog]').textContent);
    const thptFilters = form.hasAttribute('data-thpt-filters');
    const exam = form.querySelector('[name="exam"]');
    const part = form.querySelector('[name="part"]');
    const type = form.querySelector('[name="type"]');
    const query = form.querySelector('[name="q"]');
    const search = document.querySelector('[data-question-search] [name="q"]');
    const replaceOptions = (select, options, placeholder) => {
        const selectedValue = select.value;
        select.replaceChildren(new Option(placeholder, ''), ...options.map(option => new Option(option.name, option.code)));
        select.value = options.some(option => option.code === selectedValue) ? selectedValue : '';
    };
    const availableParts = () => catalog.parts.filter(option => !exam.value || option.exam === exam.value);
    const updateTypes = () => {
        const allowedTypes = new Set(availableParts().filter(option => !part.value || option.code === part.value).flatMap(option => option.types));
        replaceOptions(type, catalog.types.filter(option => allowedTypes.has(option.code)), thptFilters ? 'Tất cả dạng' : 'Tất cả loại câu hỏi');
    };
    const updateParts = () => {
        replaceOptions(part, availableParts(), thptFilters ? 'Tất cả phần' : 'Tất cả Part / kỹ năng');
        updateTypes();
    };
    form.querySelectorAll('select').forEach(select => select.addEventListener('change', () => {
        if (select === exam) {
            updateParts();
        } else if (select === part) {
            updateTypes();
        }

        if (search && query) {
            query.value = search.value;
        }

        window.clearTimeout(questionSearchTimer);
        if (form.matches('[data-ajax-filter-form]')) form.dataset.ajaxHistoryMode = 'replace';
        form.requestSubmit();
    }));
    updateParts();
});

const questionEditor = document.querySelector('[data-question-editor]');
if (questionEditor) {
    const exam = questionEditor.querySelector('[data-editor-exam]'), skill = questionEditor.querySelector('[data-editor-skill]'), part = questionEditor.querySelector('[data-editor-part]'), type = questionEditor.querySelector('[data-question-type]');
    if (part.selectedOptions[0]) { exam.value = part.selectedOptions[0].dataset.exam; skill.value = part.selectedOptions[0].dataset.skill; }
    const filterParts = () => { [...part.options].forEach(option => { option.hidden = option.dataset.exam !== exam.value || option.dataset.skill !== skill.value; }); if (part.selectedOptions[0]?.hidden) { part.value = [...part.options].find(option => !option.hidden)?.value ?? ''; } };
    const changeType = () => {
        const code = type.selectedOptions[0].dataset.code;
        const family = ['multiple_choice', 'multiple_response', 'true_false_not_given', 'yes_no_not_given'].includes(code) ? 'choice' : code.startsWith('matching') || code === 'matching' ? 'matching' : ['essay', 'speaking'].includes(code) ? code : 'text';
        questionEditor.querySelectorAll('[data-type-family]').forEach(element => { const visible = element.dataset.typeFamily.split(' ').includes(family); element.hidden = !visible; element.querySelectorAll('input, textarea').forEach(input => { input.disabled = !visible; }); if (element.matches('textarea')) { element.disabled = !visible; } });
        questionEditor.querySelectorAll('[data-choice-answer]').forEach(element => { element.hidden = family !== 'choice'; element.querySelector('input').disabled = family !== 'choice'; });
    };
    const filterTypes = () => {
        const examCode = part.selectedOptions[0]?.dataset.examCode, currentSkill = part.selectedOptions[0]?.dataset.skill;
        [...type.options].forEach(option => {
            const code = option.dataset.code;
            option.hidden = examCode === 'toeic' ? code !== 'multiple_choice' : currentSkill === 'writing' ? code !== 'essay' : currentSkill === 'speaking' ? code !== 'speaking' : ['essay', 'speaking'].includes(code);
        });
        if (type.selectedOptions[0]?.hidden) { type.value = [...type.options].find(option => !option.hidden)?.value ?? ''; }
        changeType();
    };
    const updateCatalog = () => { filterParts(); filterTypes(); };
    exam.addEventListener('change', updateCatalog); skill.addEventListener('change', updateCatalog); part.addEventListener('change', filterTypes); type.addEventListener('change', changeType); updateCatalog();

    const addOption = questionEditor.querySelector('[data-add-question-option]');
    addOption?.addEventListener('click', () => {
        const next = [...questionEditor.querySelectorAll('[data-option-row]')].find(row => row.hidden);
        if (next) { next.hidden = false; next.querySelector('input[type="text"], input:not([type])').focus(); }
        if (![...questionEditor.querySelectorAll('[data-option-row]')].some(row => row.hidden)) { addOption.hidden = true; }
    });

    const imageSelect = questionEditor.querySelector('[data-image-select]'), audioSelect = questionEditor.querySelector('[data-audio-select]');
    const updatePreview = (select, selector, isImage) => {
        const preview = questionEditor.querySelector(selector), url = select.selectedOptions[0]?.dataset.url;
        preview.hidden = !url;
        if (!url) { return; }
        const media = preview.querySelector(isImage ? 'img' : 'audio');
        media.src = url;
    };
    imageSelect?.addEventListener('change', () => updatePreview(imageSelect, '[data-image-preview]', true));
    audioSelect?.addEventListener('change', () => updatePreview(audioSelect, '[data-audio-preview]', false));

    const questionGroupSelect = questionEditor.querySelector('[name="question_group_id"]');
    questionGroupSelect?.addEventListener('change', () => {
        document.querySelector('[data-media-upload]')?.setAttribute('data-question-group-id', questionGroupSelect.value);
    });
}

const mediaUpload = document.querySelector('[data-media-upload]');
if (mediaUpload) {
    const fileInput = mediaUpload.querySelector('[data-media-file]'), button = mediaUpload.querySelector('[data-upload-media]'), status = mediaUpload.querySelector('[data-media-upload-status]');
    const ownerForm = mediaUpload.closest('form'), formSubmitControls = ownerForm?.querySelectorAll('button[type="submit"]') ?? [];
    button.addEventListener('click', () => {
        const file = fileInput.files[0];
        if (!file) { status.textContent = 'Chọn ảnh hoặc audio trước.'; return; }
        const data = new FormData();
        const isImage = file.type.startsWith('image/');
        const questionGroupId = mediaUpload.dataset.questionGroupId;
        if (!isImage && !questionGroupId) { status.textContent = 'Chọn nhóm câu hỏi audio trước khi upload.'; return; }
        data.append('file', file); data.append('title', file.name); data.append('question_id', mediaUpload.dataset.questionId);
        if (!isImage) { data.append('question_group_id', questionGroupId); }
        const token = questionEditor.querySelector('[name="_token"]').value;
        const request = new XMLHttpRequest();
        ownerForm?.setAttribute('data-media-uploading', 'true');
        formSubmitControls.forEach(control => { control.disabled = true; });
        button.disabled = true; status.textContent = 'Đang upload…';
        request.open('POST', mediaUpload.dataset.uploadUrl);
        request.setRequestHeader('Accept', 'application/json'); request.setRequestHeader('X-CSRF-TOKEN', token);
        request.addEventListener('load', () => {
            ownerForm?.removeAttribute('data-media-uploading');
            formSubmitControls.forEach(control => { control.disabled = false; });
            button.disabled = false;
            if (request.status < 200 || request.status >= 300) {
                let error = 'Upload thất bại. Kiểm tra cấu hình lưu trữ và thử lại.';
                try { error = JSON.parse(request.responseText).message ?? error; } catch { /* Keep the concise upload error. */ }
                status.textContent = error;
                return;
            }
            const asset = JSON.parse(request.responseText);
            const select = questionEditor.querySelector(isImage ? '[data-image-select]' : '[data-audio-select]');
            const option = new Option(file.name, asset.id, true, true); option.dataset.url = asset.url; select.add(option); select.value = String(asset.id); select.dispatchEvent(new Event('change', { bubbles: isImage }));
            if (!isImage && asset.revision_counter !== undefined) {
                questionEditor.querySelector('[name="revision_counter"]').value = asset.revision_counter;
            }
            status.textContent = isImage ? 'Ảnh đã lưu trên Cloudinary.' : `Audio đã gán cho ${asset.assigned_questions ?? 0} câu trong nhóm.`; fileInput.value = '';
        });
        request.upload.addEventListener('progress', event => { if (event.lengthComputable) { status.textContent = `Đang upload ${Math.round((event.loaded / event.total) * 100)}%…`; } });
        request.addEventListener('error', () => { ownerForm?.removeAttribute('data-media-uploading'); formSubmitControls.forEach(control => { control.disabled = false; }); button.disabled = false; status.textContent = 'Mất kết nối khi upload. Thử lại.'; });
        request.send(data);
    });
}

const audioLiveSearch = document.querySelector('[data-audio-live-search]');
if (audioLiveSearch) {
    const audioLibraryPanel = document.querySelector('[data-audio-library-panel]');
    const audioLibraryQuery = audioLiveSearch.querySelector('[data-audio-live-query]');
    let audioSearchTimer;
    let audioSearchRequest = 0;

    const loadAudioLibrary = async () => {
        if (!audioLibraryPanel || !audioLibraryQuery) {
            return;
        }

        const requestId = ++audioSearchRequest;
        const url = new URL(audioLiveSearch.action, window.location.origin);
        const query = audioLibraryQuery.value.trim();
        if (query) {
            url.searchParams.set('q', query);
        } else {
            url.searchParams.delete('q');
        }

        audioLibraryPanel.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('Audio search failed');
            }

            const html = await response.text();
            if (requestId !== audioSearchRequest) {
                return;
            }

            const documentView = new DOMParser().parseFromString(html, 'text/html');
            const nextPanel = documentView.querySelector('[data-audio-library-panel]');
            const nextList = nextPanel?.querySelector('[data-audio-library-list]');
            const currentList = audioLibraryPanel.querySelector('[data-audio-library-list]');
            if (!nextList || !currentList) {
                throw new Error('Audio search response is invalid');
            }

            currentList.replaceWith(nextList);
            const currentSummary = audioLibraryPanel.querySelector('[data-audio-library-summary]');
            const nextSummary = nextPanel.querySelector('[data-audio-library-summary]');
            if (currentSummary && nextSummary) {
                currentSummary.textContent = nextSummary.textContent;
            }

            const currentPagination = audioLibraryPanel.querySelector('[data-audio-library-pagination]');
            const nextPagination = nextPanel.querySelector('[data-audio-library-pagination]');
            currentPagination?.remove();
            if (nextPagination) {
                audioLibraryPanel.append(nextPagination);
            }

            const deleteForm = audioLibraryPanel.querySelector('[data-audio-delete-form]');
            if (deleteForm && nextPanel.querySelector('[data-audio-delete-form]')) {
                deleteForm.dataset.audioTotal = nextPanel.querySelector('[data-audio-delete-form]').dataset.audioTotal;
                deleteForm.querySelector('[name="q"]').value = query;
            }

            window.history.replaceState({}, '', url);
            document.dispatchEvent(new CustomEvent('audio-library-updated'));
        } catch {
            // Keep the current result visible when a transient request fails.
        } finally {
            if (requestId === audioSearchRequest) {
                audioLibraryPanel.removeAttribute('aria-busy');
            }
        }
    };

    audioLiveSearch.addEventListener('submit', event => {
        event.preventDefault();
        window.clearTimeout(audioSearchTimer);
        loadAudioLibrary();
    });
    audioLibraryQuery.addEventListener('input', () => {
        window.clearTimeout(audioSearchTimer);
        audioSearchTimer = window.setTimeout(loadAudioLibrary, 280);
    });
}

const audioBatch = document.querySelector('[data-audio-batch]');
if (audioBatch) {
    const input = audioBatch.querySelector('[data-audio-batch-input]');
    const dropzone = audioBatch.querySelector('[data-audio-dropzone]');
    const selection = audioBatch.querySelector('[data-audio-file-list]');
    const fileCount = audioBatch.querySelector('[data-audio-file-count]');
    const fileItems = audioBatch.querySelector('[data-audio-file-items]');
    const dropzoneTitle = audioBatch.querySelector('[data-audio-dropzone-title]');
    const status = audioBatch.querySelector('[data-audio-upload-status]');
    const submit = audioBatch.querySelector('[data-audio-submit]');
    const clear = audioBatch.querySelector('[data-audio-clear]');
    const maxFilesPerRequest = 20;
    const configuredPostLimit = Number(audioBatch.dataset.audioPostLimitBytes);
    const postLimitBytes = Number.isFinite(configuredPostLimit) && configuredPostLimit > 0 ? configuredPostLimit : 8 * 1024 * 1024;
    const requestBudgetBytes = Math.max(1024 * 1024, Math.floor(postLimitBytes * 0.75));
    const multipartOverhead = 64 * 1024;
    const formatSize = bytes => bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    const isAudio = file => file.type.startsWith('audio/') || /\.(mp3|wav|m4a|ogg)$/i.test(file.name);

    const makeBatches = files => {
        const batches = [];
        let batch = [];
        let batchBytes = 0;

        files.forEach(file => {
            const estimatedSize = file.size + multipartOverhead;
            if (estimatedSize > requestBudgetBytes) {
                throw new Error(file.name + ' vượt giới hạn request hiện tại (' + formatSize(requestBudgetBytes) + ').');
            }

            if (batch.length >= maxFilesPerRequest || (batch.length > 0 && batchBytes + estimatedSize > requestBudgetBytes)) {
                batches.push(batch);
                batch = [];
                batchBytes = 0;
            }

            batch.push(file);
            batchBytes += estimatedSize;
        });

        if (batch.length > 0) {
            batches.push(batch);
        }

        return batches;
    };

    const renderFiles = files => {
        const selected = [...files];
        selection.hidden = selected.length === 0;
        fileCount.textContent = String(selected.length);
        fileItems.replaceChildren(...selected.map(file => {
            const item = document.createElement('li');
            const name = document.createElement('span');
            const size = document.createElement('small');
            name.textContent = file.name;
            size.textContent = formatSize(file.size);
            item.append(name, size);
            return item;
        }));
        dropzoneTitle.textContent = selected.length > 0
            ? String(selected.length) + ' file audio đã sẵn sàng'
            : 'Thả file audio vào đây';
        if (status && selected.length > 0) {
            status.innerHTML = '<span aria-hidden="true">●</span> Kiểm tra tên file rồi tải lên.';
        }
    };

    input.addEventListener('change', () => renderFiles(input.files));
    ['dragenter', 'dragover'].forEach(eventName => dropzone.addEventListener(eventName, event => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach(eventName => dropzone.addEventListener(eventName, event => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
    }));
    dropzone.addEventListener('drop', event => {
        const files = [...event.dataTransfer.files].filter(isAudio);
        if (files.length === 0) {
            status.textContent = 'Chỉ nhận file MP3, WAV, M4A hoặc OGG.';
            return;
        }
        const transfer = new DataTransfer();
        files.forEach(file => transfer.items.add(file));
        input.files = transfer.files;
        renderFiles(input.files);
    });
    clear?.addEventListener('click', () => {
        input.value = '';
        renderFiles([]);
        status.innerHTML = '<span aria-hidden="true">●</span> Sẵn sàng nhận file audio.';
    });
    audioBatch.addEventListener('submit', async event => {
        if (!input.files.length) {
            event.preventDefault();
            status.textContent = 'Chọn ít nhất một file audio trước khi tải lên.';
            return;
        }

        event.preventDefault();
        const files = [...input.files].filter(isAudio);
        let batches;

        try {
            batches = makeBatches(files);
        } catch (error) {
            status.textContent = error.message;
            return;
        }

        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        let uploaded = 0;

        try {
            for (let index = 0; index < batches.length; index += 1) {
                const data = new FormData();
                data.append('_token', audioBatch.querySelector('input[name="_token"]').value);
                batches[index].forEach(file => data.append('files[]', file, file.name));
                status.innerHTML = '<span aria-hidden="true">●</span> Đang tải đợt ' + (index + 1) + '/' + batches.length + '… (' + uploaded + '/' + files.length + ')';

                const response = await fetch(audioBatch.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: data,
                    credentials: 'same-origin',
                });
                let payload = {};
                try {
                    payload = await response.json();
                } catch {
                    payload = {};
                }

                if (!response.ok) {
                    const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                    const message = response.status === 413
                        ? 'Đợt upload vượt giới hạn máy chủ. Hãy giảm số file hoặc tăng post_max_size.'
                        : validationError || payload.message || ('Đợt ' + (index + 1) + ' không tải được.');
                    throw new Error(message);
                }

                uploaded += Number(payload.uploaded ?? batches[index].length);
            }

            status.innerHTML = '<span aria-hidden="true">●</span> Đã tải lên ' + uploaded + ' file. Đang cập nhật thư viện…';
            window.setTimeout(() => window.location.assign(audioBatch.dataset.successUrl), 450);
        } catch (error) {
            status.textContent = 'Đã tải ' + uploaded + '/' + files.length + ' file. ' + error.message;
            submit.disabled = false;
            submit.removeAttribute('aria-busy');
        }
    });
}

const audioEditorFile = document.querySelector('[data-audio-editor-file]');
if (audioEditorFile) {
    const status = document.querySelector('[data-audio-editor-file-status]');
    const duration = document.querySelector('[data-audio-duration]');
    audioEditorFile.addEventListener('change', () => {
        const file = audioEditorFile.files[0];
        if (!file) {
            status.textContent = 'Để trống nếu muốn giữ file hiện tại.';
            return;
        }
        status.textContent = file.name + ' · ' + Math.max(1, Math.round(file.size / 1024)) + ' KB sẽ thay file hiện tại.';
        if (duration && !duration.value) {
            const preview = document.createElement('audio');
            const objectUrl = URL.createObjectURL(file);
            preview.addEventListener('loadedmetadata', () => {
                if (Number.isFinite(preview.duration) && preview.duration > 0) {
                    duration.value = String(Math.round(preview.duration));
                }
                URL.revokeObjectURL(objectUrl);
            }, { once: true });
            preview.src = objectUrl;
        }
    });
}

const questionImport = document.querySelector('[data-question-import]');
if (questionImport) {
    const progress = questionImport.querySelector('[data-import-progress]');
    const bar = questionImport.querySelector('[data-import-progress-bar]');
    const label = questionImport.querySelector('[data-import-progress-label]');
    const count = questionImport.querySelector('[data-import-progress-count]');
    const feedback = questionImport.querySelector('[data-import-feedback]');
    const submit = questionImport.querySelector('button[type="submit"]');
    const thptCsvFile = questionImport.querySelector('[data-thpt-csv-file]');
    const thptImageSection = questionImport.querySelector('[data-thpt-csv-images]');
    const thptImageSummary = questionImport.querySelector('[data-thpt-csv-image-summary]');
    const thptImageGroups = questionImport.querySelector('[data-thpt-csv-image-groups]');
    const thptImageError = questionImport.querySelector('[data-thpt-csv-image-error]');
    let thptCsvScanBusy = false;
    let thptCsvScanError = '';

    if (thptCsvFile && thptImageSection && thptImageGroups) {
        const clearImageFields = () => {
            thptImageGroups.replaceChildren();
            thptImageSection.hidden = true;
            thptImageSummary.textContent = '';
            thptImageError.hidden = true;
            thptImageError.textContent = '';
        };
        const parseCsv = source => {
            const firstLine = source.split(/\r\n|\n|\r/, 1)[0] ?? '';
            const delimiters = [',', ';', '\t'];
            delimiters.sort((left, right) => firstLine.split(right).length - firstLine.split(left).length);
            const delimiter = delimiters[0];
            const rows = [];
            let row = [];
            let field = '';
            let quoted = false;
            for (let index = 0; index < source.length; index++) {
                const character = source[index];
                if (character === '"') {
                    if (quoted && source[index + 1] === '"') { field += '"'; index++; }
                    else if (quoted) quoted = false;
                    else if (field === '') quoted = true;
                    else field += character;
                } else if (!quoted && character === delimiter) {
                    row.push(field); field = '';
                } else if (!quoted && (character === '\n' || character === '\r')) {
                    if (character === '\r' && source[index + 1] === '\n') index++;
                    row.push(field); rows.push(row); row = []; field = '';
                } else {
                    field += character;
                }
            }
            if (field !== '' || row.length) { row.push(field); rows.push(row); }
            const headers = (rows.shift() ?? []).map(value => value.replace(/^\uFEFF/, '').trim().toLowerCase().replace(/[\s-]+/g, '_'));
            const normalizedHeaders = headers.map(header => header === 'part' ? 'exam_part' : header === 'question' || header === 'content' ? 'prompt' : header);
            return rows.filter(values => values.some(value => String(value ?? '').trim() !== ''))
                .map(values => Object.fromEntries(normalizedHeaders.map((header, index) => [header, values[index] ?? ''])));
        };

        thptCsvFile.addEventListener('change', async () => {
            clearImageFields();
            thptCsvScanError = '';
            feedback.hidden = true;
            const file = thptCsvFile.files?.[0];
            if (!file) return;
            thptCsvScanBusy = true;
            submit.disabled = true;
            try {
                const records = parseCsv(await file.text());
                const groups = new Map();
                const missingKeyByPart = new Map();
                const partNames = {
                    'tieng-anh-part-1': 'Phần 1 · Điền từ / văn bản chung',
                    'tieng-anh-part-2': 'Phần 2 · Điền từ / văn bản chung',
                };
                let detectedRows = 0;
                records.forEach(record => {
                    const examType = String(record.exam_type ?? '').trim().toLowerCase();
                    let partCode = String(record.exam_part ?? '').trim().toLowerCase();
                    if ((!examType || examType === 'thpt') && /^part-[12]$/.test(partCode)) partCode = `tieng-anh-${partCode}`;
                    if (examType && examType !== 'thpt') return;
                    if (!partNames[partCode]) return;
                    detectedRows++;
                    const readingKey = String(record.reading_key ?? '').trim();
                    if (!readingKey) {
                        missingKeyByPart.set(partCode, (missingKeyByPart.get(partCode) ?? 0) + 1);
                        return;
                    }
                    const key = `${partCode}\u0000${readingKey.toLowerCase()}`;
                    const isFirstQuestion = !groups.has(key);
                    const group = groups.get(key) ?? { partCode, readingKey, questionCount: 0, firstQuestionHasTitle: false, firstQuestionHasBody: false };
                    group.questionCount++;
                    if (isFirstQuestion) {
                        group.firstQuestionHasTitle = String(record.reading_title ?? '').trim() !== '';
                        group.firstQuestionHasBody = String(record.reading_body ?? '').trim() !== '';
                    }
                    groups.set(key, group);
                });
                if (!detectedRows) return;

                thptImageSection.hidden = false;
                thptImageSummary.textContent = `Phát hiện ${groups.size} nhóm văn bản · ${detectedRows} câu thuộc Phần 1–2.`;
                thptImageGroups.innerHTML = [...groups.values()].map((group, index) => `
                    <fieldset class="qb-csv-image-group">
                        <legend>${encode(partNames[group.partCode])}</legend>
                        <label>
                            <span><b>${encode(group.readingKey)}</b> · ${group.questionCount} câu dùng chung một ảnh</span>
                            <input type="hidden" name="thpt_image_refs[${index}][exam_part]" value="${encode(group.partCode)}">
                            <input type="hidden" name="thpt_image_refs[${index}][reading_key]" value="${encode(group.readingKey)}">
                            <input type="file" name="thpt_images[${index}]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required aria-label="Ảnh cho ${encode(partNames[group.partCode])} ${encode(group.readingKey)}">
                        </label>
                    </fieldset>`).join('');

                const scanProblems = [];
                if (groups.size > 50) scanProblems.push('Chỉ có thể đính kèm ảnh cho tối đa 50 nhóm văn bản trong một lần import.');
                for (const [partCode, count] of missingKeyByPart) {
                    scanProblems.push(`${partNames[partCode]}: ${count} dòng thiếu reading_key.`);
                }
                for (const group of groups.values()) {
                    if (!group.firstQuestionHasTitle) scanProblems.push(`${partNames[group.partCode]} · ${group.readingKey}: dòng câu hỏi đầu tiên cần reading_title.`);
                    if (!group.firstQuestionHasBody) scanProblems.push(`${partNames[group.partCode]} · ${group.readingKey}: dòng câu hỏi đầu tiên cần reading_body.`);
                }
                if (scanProblems.length) {
                    thptCsvScanError = scanProblems.join('\n');
                    thptImageError.textContent = `Bổ sung thông tin văn bản trong CSV trước khi import:\n${thptCsvScanError}`;
                    thptImageError.hidden = false;
                }
            } catch {
                thptCsvScanError = 'Không thể đọc cấu trúc CSV để nhận diện nhóm văn bản. Hãy kiểm tra hàng tiêu đề và định dạng file.';
                thptImageSection.hidden = false;
                thptImageError.textContent = thptCsvScanError;
                thptImageError.hidden = false;
            } finally {
                thptCsvScanBusy = false;
                submit.disabled = false;
            }
        });
    }

    questionImport.addEventListener('submit', event => {
        event.preventDefault();
        if (thptCsvScanBusy) { feedback.hidden = false; feedback.textContent = 'Đang nhận diện phần thi trong file CSV. Chờ một chút rồi thử lại.'; return; }
        if (thptCsvScanError) { feedback.hidden = false; feedback.textContent = thptCsvScanError; return; }
        if (!questionImport.reportValidity()) { return; }

        const token = crypto.randomUUID();
        questionImport.querySelector('[name="import_key"]').value = token;
        questionImport.querySelector('[name="offset"]').value = '0';
        progress.hidden = false;
        feedback.hidden = true;
        submit.disabled = true;
        label.textContent = 'Đang tải file CSV…';

        const updateProgress = value => {
            const percent = Math.max(0, Math.min(99, Math.round(value)));
            bar.value = percent;
            count.textContent = `${percent}%`;
        };

        const showFailure = message => {
            submit.disabled = false;
            feedback.hidden = false;
            feedback.textContent = message;
            label.textContent = 'Import bị gián đoạn';
        };

        const sendBatch = offset => {
            questionImport.querySelector('[name="offset"]').value = String(offset);
            const request = new XMLHttpRequest();
            const formData = new FormData(questionImport);

            request.open('POST', questionImport.action);
            request.setRequestHeader('Accept', 'application/json');
            if (offset === 0) {
                request.upload.addEventListener('progress', event => {
                    if (event.lengthComputable) { updateProgress((event.loaded / event.total) * 20); }
                });
                request.upload.addEventListener('load', () => { label.textContent = 'Đang phân tích và lưu câu hỏi…'; });
            }
            request.addEventListener('load', () => {
                let result;
                try { result = JSON.parse(request.responseText); }
                catch { showFailure('Máy chủ không trả về kết quả import hợp lệ. Hãy kiểm tra log trước khi tải lại file để tránh nhập trùng.'); return; }

                if (request.status >= 200 && request.status < 300 && result.status === 'processing') {
                    label.textContent = `Đang lưu câu hỏi ${result.processed} / ${result.total}…`;
                    updateProgress(20 + (result.processed / result.total) * 75);
                    sendBatch(result.next_offset);
                    return;
                }

                if (request.status >= 200 && request.status < 300 && result.summary) {
                    bar.value = 100;
                    count.textContent = '100%';
                    label.textContent = `Hoàn tất: ${result.summary.imported} câu hỏi đã nhập.`;
                    window.location.assign(result.redirect);
                    return;
                }

                showFailure(result.message ?? Object.values(result.errors ?? {}).flat().join(' ') ?? 'Import không hoàn tất. Hãy kiểm tra file CSV và thử lại.');
            });
            request.addEventListener('error', () => showFailure('Mất kết nối trong khi import. Một phần câu hỏi có thể đã được lưu; hãy kiểm tra trạng thái trước khi tải lại file.'));
            request.send(formData);
        };

        sendBatch(0);
    });
}

const matchingIdsScript = questionBulkForm?.querySelector('[data-question-matching-ids]');
let matchingQuestionIds = matchingIdsScript ? JSON.parse(matchingIdsScript.textContent) : [];
const questionSelectionSummary = questionBulkForm?.querySelector('[data-question-selection-summary]');
const thptDraftIdsScript = questionBulkForm?.querySelector('[data-question-draft-ids]');
let thptDraftIds = new Set(thptDraftIdsScript ? JSON.parse(thptDraftIdsScript.textContent).map(String) : []);
const thptReviewForm = document.querySelector('#thpt-review-bulk');
const thptReviewButton = questionBulkForm?.querySelector('[data-submit-selected-thpt-review]');
let selectingAllMatchingQuestions = false;

function getSelectedQuestionIds() {
    if (!questionBulkForm) return [];

    return selectingAllMatchingQuestions
        ? [...questionBulkForm.querySelectorAll('input[name="matching_ids[]"]')].map(input => String(input.value))
        : [...questionBulkForm.querySelectorAll('input[form="question-bulk"][name="ids[]"]:checked')].map(input => String(input.value));
}

function updateQuestionSelectionSummary() {
    const selectAllQuestions = document.querySelector('[data-select-all]');
    if (!questionBulkForm || !selectAllQuestions || !questionSelectionSummary) {
        return;
    }

    const visibleQuestionCheckboxes = [...document.querySelectorAll('input[form="question-bulk"][name="ids[]"]')];
    const selectedVisibleQuestions = visibleQuestionCheckboxes.filter(checkbox => checkbox.checked).length;
    const selectedMatchingQuestions = questionBulkForm.querySelectorAll('input[name="matching_ids[]"]').length;
    const selectedCount = selectingAllMatchingQuestions ? selectedMatchingQuestions : selectedVisibleQuestions;
    const allMatchingSelected = selectingAllMatchingQuestions && selectedMatchingQuestions === matchingQuestionIds.length;

    selectAllQuestions.checked = allMatchingSelected;
    selectAllQuestions.indeterminate = selectedCount > 0 && !allMatchingSelected;
    questionSelectionSummary.textContent = selectingAllMatchingQuestions
        ? `Đã chọn ${selectedMatchingQuestions}/${matchingQuestionIds.length} câu phù hợp bộ lọc.`
        : selectedVisibleQuestions > 0 ? `Đã chọn ${selectedVisibleQuestions} câu trên trang này.` : '';
    const approveButton = questionBulkForm.querySelector('[data-approve-selected-questions]');
    if (approveButton) approveButton.disabled = selectedCount === 0;

    if (thptReviewButton) {
        const draftCount = [...new Set(getSelectedQuestionIds())].filter(id => thptDraftIds.has(id)).length;
        thptReviewButton.disabled = draftCount === 0;
        thptReviewButton.textContent = draftCount ? `Gửi duyệt câu nháp đã chọn (${draftCount})` : 'Gửi duyệt câu nháp đã chọn';
    }
}

function setMatchingQuestionSelection(isSelected) {
    selectingAllMatchingQuestions = isSelected;
    questionBulkForm?.querySelectorAll('input[name="matching_ids[]"]').forEach(input => input.remove());

    if (isSelected && questionBulkForm) {
        matchingQuestionIds.forEach(questionId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'matching_ids[]';
            input.value = questionId;
            questionBulkForm.append(input);
        });
    }

    document.querySelectorAll('input[form="question-bulk"][name="ids[]"]').forEach(input => { input.checked = isSelected; });
    updateQuestionSelectionSummary();
}

document.addEventListener('change', event => {
    const control = event.target;
    if (control.matches('[data-select-all]')) {
        setMatchingQuestionSelection(control.checked);
        return;
    }

    const checkbox = control.closest('input[form="question-bulk"][name="ids[]"]');
    if (!checkbox) return;
    if (selectingAllMatchingQuestions && questionBulkForm) {
        const existingInput = [...questionBulkForm.querySelectorAll('input[name="matching_ids[]"]')].find(input => input.value === checkbox.value);
        if (checkbox.checked && !existingInput) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'matching_ids[]';
            input.value = checkbox.value;
            questionBulkForm.append(input);
        } else if (!checkbox.checked) {
            existingInput?.remove();
        }
    }

    updateQuestionSelectionSummary();
});

document.addEventListener('vistiq:ajax-results-updated', event => {
    if (event.detail?.selector !== '#qb-results' || !questionBulkForm) return;
    const results = event.detail.target;
    const updateJson = (target, selector, value) => {
        const element = target?.querySelector(selector);
        if (element) element.textContent = JSON.stringify(value);
    };
    const matchingIdsElement = results.querySelector('[data-ajax-qb-matching-ids]');
    const revisionsElement = results.querySelector('[data-ajax-qb-review-revisions]');
    const draftIdsElement = results.querySelector('[data-ajax-qb-draft-ids]');

    matchingQuestionIds = matchingIdsElement ? JSON.parse(matchingIdsElement.textContent) : [];
    questionReviewRevisions = revisionsElement ? JSON.parse(revisionsElement.textContent) : {};
    thptDraftIds = new Set(draftIdsElement ? JSON.parse(draftIdsElement.textContent).map(String) : []);
    updateJson(questionBulkForm, '[data-question-matching-ids]', matchingQuestionIds);
    updateJson(questionBulkForm, '[data-question-review-revisions]', questionReviewRevisions);
    updateJson(questionBulkForm, '[data-question-draft-ids]', [...thptDraftIds]);

    questionBulkForm.querySelectorAll('input[form="question-bulk"][name="ids[]"]').forEach(input => input.remove());
    const pageIds = new Set();
    results.querySelectorAll('[data-qb-select-entry]').forEach(control => {
        try {
            JSON.parse(control.dataset.questionIds).forEach(id => pageIds.add(String(id)));
        } catch {
            // Ignore malformed row metadata and leave that row unavailable for bulk actions.
        }
    });
    pageIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = 'ids[]';
        input.value = id;
        input.hidden = true;
        input.setAttribute('form', 'question-bulk');
        input.setAttribute('aria-label', `Chọn câu ${id}`);
        questionBulkForm.append(input);
    });

    questionBulkForm.querySelectorAll('input[name="matching_ids[]"]').forEach(input => input.remove());
    selectingAllMatchingQuestions = false;
    const count = results.querySelector('[data-ajax-qb-result-count]');
    const countTarget = document.querySelector('[data-qb-visible-count]');
    if (count && countTarget) countTarget.textContent = count.textContent.trim();
    updateQuestionSelectionSummary();
});

thptReviewButton?.addEventListener('click', () => {
    if (!thptReviewForm || thptReviewForm.dataset.submitting === 'true') return;

    const draftIds = [...new Set(getSelectedQuestionIds())].filter(id => thptDraftIds.has(id));
    if (draftIds.length === 0) return;
    if (draftIds.length > 5000) {
        alert('Mỗi lượt gửi duyệt tối đa 5.000 câu. Hãy thu hẹp bộ lọc rồi thử lại.');
        return;
    }

    thptReviewForm.querySelectorAll('input[name="ids[]"], input[name="matching_ids[]"]').forEach(input => input.remove());
    const fieldName = draftIds.length <= 100 ? 'ids[]' : 'matching_ids[]';
    draftIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = fieldName;
        input.value = id;
        thptReviewForm.append(input);
    });
    thptReviewForm.requestSubmit();
});

thptReviewForm?.addEventListener('submit', event => {
    if (thptReviewForm.dataset.submitting === 'true' || !thptReviewForm.querySelector('input[name="ids[]"], input[name="matching_ids[]"]')) {
        event.preventDefault();
        return;
    }

    thptReviewForm.dataset.submitting = 'true';
    thptReviewForm.setAttribute('aria-busy', 'true');
    if (thptReviewButton) {
        thptReviewButton.disabled = true;
        thptReviewButton.textContent = thptReviewForm.dataset.pendingLabel || 'Đang gửi duyệt…';
    }
});

function updateReadingReviewSelection() {
    const readingReviewForm = document.querySelector('#reading-review-bulk');
    if (!readingReviewForm) return;

    const readingReviewRoot = document.querySelector('[data-reading-library]');
    const readingReviewSelectAll = readingReviewRoot?.querySelector('[data-reading-review-select-all]');
    const readingReviewCheckboxes = [...(readingReviewRoot?.querySelectorAll('[data-reading-review-checkbox]') ?? [])];
    const readingReviewSummary = readingReviewForm.querySelector('[data-reading-review-summary]');
    const readingReviewSubmit = readingReviewForm.querySelector('[data-reading-review-submit]');
    const readingReviewCount = readingReviewForm.querySelector('[data-reading-review-count]');
    if (readingReviewSelectAll) readingReviewSelectAll.disabled = readingReviewCheckboxes.length === 0;
    const selected = readingReviewCheckboxes.filter(checkbox => checkbox.checked);
    readingReviewForm.querySelectorAll('[data-reading-review-value]').forEach(input => input.remove());
    selected.forEach(checkbox => {
        const id = checkbox.value;
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'ids[]';
        idInput.value = id;
        idInput.dataset.readingReviewValue = '';
        readingReviewForm.append(idInput);

        const revisionInput = document.createElement('input');
        revisionInput.type = 'hidden';
        revisionInput.name = `revision_ids[${id}]`;
        revisionInput.value = checkbox.dataset.revisionId;
        revisionInput.dataset.readingReviewValue = '';
        readingReviewForm.append(revisionInput);
    });

    if (readingReviewSelectAll) {
        readingReviewSelectAll.checked = selected.length > 0 && selected.length === readingReviewCheckboxes.length;
        readingReviewSelectAll.indeterminate = selected.length > 0 && selected.length < readingReviewCheckboxes.length;
    }
    if (readingReviewSummary) {
        readingReviewSummary.textContent = selected.length
            ? `Đã chọn ${selected.length} bài Reading trên trang này.`
            : 'Chọn bài đọc nháp để gửi duyệt.';
    }
    if (readingReviewCount) readingReviewCount.textContent = selected.length ? `(${selected.length})` : '';
    if (readingReviewSubmit) readingReviewSubmit.disabled = selected.length === 0;
}

document.addEventListener('change', event => {
    const selectAll = event.target.closest('[data-reading-review-select-all]');
    if (selectAll) {
        document.querySelector('[data-reading-library]')?.querySelectorAll('[data-reading-review-checkbox]').forEach(checkbox => { checkbox.checked = selectAll.checked; });
        updateReadingReviewSelection();
    } else if (event.target.closest('[data-reading-review-checkbox]')) {
        updateReadingReviewSelection();
    }
});
document.addEventListener('submit', event => {
    const readingReviewForm = event.target.closest('#reading-review-bulk');
    if (!readingReviewForm) return;
    const readingReviewCheckboxes = [...(document.querySelector('[data-reading-library]')?.querySelectorAll('[data-reading-review-checkbox]') ?? [])];
    if (!readingReviewCheckboxes.some(checkbox => checkbox.checked)) {
        event.preventDefault();
        return;
    }

    readingReviewForm.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
});
document.addEventListener('vistiq:ajax-results-updated', event => {
    if (event.detail.targets?.some(target => target.id === 'reading-list-results')) updateReadingReviewSelection();
});
updateReadingReviewSelection();

document.addEventListener('click', event => {
    const addButton = event.target.closest('[data-knowledge-add]');
    if (addButton) {
        const form = addButton.closest('form');
        const listName = addButton.dataset.knowledgeAdd;
        const list = form?.querySelector(`[data-knowledge-list="${listName}"]`);
        const template = form?.querySelector(`template[data-knowledge-template="${listName}"]`);
        if (!list || !template) { return; }

        const index = Number(list.dataset.nextIndex || 0);
        list.dataset.nextIndex = String(index + 1);
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        list.querySelector('[data-knowledge-row]:last-child input, [data-knowledge-row]:last-child select')?.focus();
        return;
    }

    const removeButton = event.target.closest('[data-knowledge-remove]');
    if (removeButton) {
        removeButton.closest('[data-knowledge-row]')?.remove();
    }
});

document.querySelectorAll('[data-listening-player]').forEach(player => {
    const audio = player.querySelector('[data-listening-audio]');
    const toggle = player.querySelector('[data-listening-toggle]');
    const seek = player.querySelector('[data-listening-seek]');
    const currentTime = player.querySelector('[data-listening-current]');
    const duration = player.querySelector('[data-listening-duration]');
    const speed = player.querySelector('[data-listening-speed]');
    const bars = [...player.querySelectorAll('.listening-waveform i')];

    if (!(audio instanceof HTMLAudioElement) || !(toggle instanceof HTMLButtonElement) || !(seek instanceof HTMLInputElement)) {
        return;
    }

    const formatTime = seconds => {
        if (!Number.isFinite(seconds) || seconds < 0) {
            return '00:00';
        }

        const wholeSeconds = Math.floor(seconds);
        return `${String(Math.floor(wholeSeconds / 60)).padStart(2, '0')}:${String(wholeSeconds % 60).padStart(2, '0')}`;
    };

    const syncPlayer = () => {
        const length = Number.isFinite(audio.duration) ? audio.duration : 0;
        const progress = length > 0 ? Math.min(1, audio.currentTime / length) : 0;
        seek.value = String(progress * 100);
        seek.setAttribute('aria-valuetext', `${formatTime(audio.currentTime)} / ${formatTime(length)}`);
        if (currentTime) { currentTime.textContent = formatTime(audio.currentTime); }
        if (duration && length > 0) { duration.textContent = formatTime(length); }
        bars.forEach((bar, index) => bar.classList.toggle('is-played', index / bars.length < progress));
        toggle.classList.toggle('is-playing', !audio.paused && !audio.ended);
        toggle.setAttribute('aria-label', audio.paused || audio.ended ? 'Phát audio' : 'Tạm dừng audio');
    };

    toggle.addEventListener('click', () => {
        if (audio.paused || audio.ended) {
            audio.play().catch(() => {});
        } else {
            audio.pause();
        }
    });

    seek.addEventListener('input', () => {
        if (Number.isFinite(audio.duration) && audio.duration > 0) {
            audio.currentTime = audio.duration * (Number(seek.value) / 100);
        }
        syncPlayer();
    });

    speed?.addEventListener('change', () => { audio.playbackRate = Number(speed.value) || 1; });
    ['loadedmetadata', 'durationchange', 'timeupdate', 'play', 'pause', 'ended', 'seeked'].forEach(eventName => audio.addEventListener(eventName, syncPlayer));
    syncPlayer();
});
