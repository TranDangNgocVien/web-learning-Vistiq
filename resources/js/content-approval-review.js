import { requestAdminConfirmation } from './confirmation-dialog.js';
import { notify } from './ui-feedback.js';

const dialog = document.querySelector('[data-content-review-dialog]');
if (dialog) {
    const content = dialog.querySelector('[data-review-dialog-content]');
    let activeButton = null;

    const setMessage = (message, failure = false) => {
        content.replaceChildren();
        const notice = document.createElement('p');
        notice.className = failure ? 'admin-alert admin-alert-error' : 'content-review-loading';
        notice.setAttribute('role', failure ? 'alert' : 'status');
        notice.textContent = message;
        content.append(notice);
    };

    const refreshQueue = async () => {
        const current = document.querySelector('[data-review-queue-results]');
        const response = await fetch(window.location.href, {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('Không thể cập nhật hàng đợi sau khi xử lý. Hãy tải lại trang.');
        const documentView = new DOMParser().parseFromString(await response.text(), 'text/html');
        const updated = documentView.querySelector('[data-review-queue-results]');
        if (!current || !updated) throw new Error('Không tìm thấy hàng đợi để cập nhật. Hãy tải lại trang.');
        current.replaceWith(updated);
        initializeBulkSelection(updated);
    };

    const initializeBulkSelection = root => {
        const form = root.querySelector('[data-review-bulk-approve]');
        if (!form) return;
        const selectAll = form.querySelector('[data-review-select-all]');
        const toolbar = form.querySelector('[data-review-bulk-toolbar]');
        const countMessage = form.querySelector('[data-review-selection-count]');
        const submitButton = form.querySelector('[data-review-bulk-button]');
        const modeInput = form.querySelector('[data-review-selection-mode]');
        const progressBar = form.querySelector('[data-review-progress-bar]');
        const progressCount = form.querySelector('[data-review-progress-count]');
        const progressStatus = form.querySelector('[data-review-progress-status]');
        const total = Number(form.dataset.reviewTotal ?? 0);
        const selected = new Set();
        const excluded = new Set();
        let allFiltered = false;
        let pendingUrl = form.dataset.reviewResumeUrl || null;
        let batchProgress = JSON.parse(form.dataset.reviewProgressState || 'null');
        const progressMessage = progress => `Đã xử lý ${progress.processed}/${progress.total} revision · Đã duyệt ${progress.approved} · Bỏ qua ${progress.skipped}.`;
        const showProgress = progress => {
            progressBar.max = Math.max(1, progress.total);
            progressBar.value = progress.processed;
            progressCount.textContent = `${progress.processed}/${progress.total}`;
            progressStatus.textContent = progressMessage(progress);
        };

        const rows = () => [...form.querySelectorAll('[data-review-select-row]')];
        const syncHiddenValues = () => {
            form.querySelectorAll('[data-review-selection-value]').forEach(input => input.remove());
            const values = allFiltered ? [...excluded] : [...selected];
            const name = allFiltered ? 'excluded[]' : 'selected[]';
            values.forEach(value => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                input.dataset.reviewSelectionValue = '';
                form.append(input);
            });
            modeInput.value = allFiltered ? 'all_filtered' : 'selected';
        };
        const update = () => {
            if (pendingUrl) {
                toolbar.hidden = false;
                countMessage.textContent = 'Có đợt duyệt chưa hoàn tất. Tiếp tục để xử lý các revision còn lại.';
                if (batchProgress) showProgress(batchProgress);
                submitButton.textContent = 'Tiếp tục duyệt';
                return;
            }
            const count = allFiltered ? Math.max(0, total - excluded.size) : selected.size;
        const filteredKind = form.querySelector('[name="kind"]')?.value;
        const selectedKinds = allFiltered
            ? new Set(filteredKind ? [filteredKind] : ['question', 'stimulus', 'exam'])
            : new Set([...selected].map(value => value.split(':', 1)[0]));
        const approvalOutcome = allFiltered && !filteredKind
            ? 'Bài Reading (nếu có) sẽ chuyển sang Đã duyệt; câu hỏi và đề thi (nếu có) sẽ chuyển sang Chờ xuất bản.'
            : selectedKinds.has('stimulus')
            ? (selectedKinds.has('question') || selectedKinds.has('exam')
                ? 'Bài Reading sẽ chuyển sang Đã duyệt; câu hỏi và đề thi sẽ chuyển sang Chờ xuất bản.'
                : 'Bài Reading sẽ chuyển sang Đã duyệt, không cần xuất bản thêm.')
            : 'Nội dung sẽ chuyển sang Chờ xuất bản, chưa hiển thị cho người học.';
            if (batchProgress && count === 0) showProgress(batchProgress);
            else {
                progressBar.max = Math.max(1, count || total);
                progressBar.value = 0;
                progressCount.textContent = `0/${count || total}`;
                progressStatus.textContent = total ? 'Chưa bắt đầu duyệt.' : 'Không có nội dung chờ duyệt.';
            }
            if (selectAll) {
                selectAll.checked = allFiltered;
                selectAll.indeterminate = allFiltered ? excluded.size > 0 : selected.size > 0;
                selectAll.setAttribute('aria-checked', selectAll.indeterminate ? 'mixed' : String(selectAll.checked));
            }
            rows().forEach(row => {
                row.checked = allFiltered ? !excluded.has(row.value) : selected.has(row.value);
            });
            toolbar.hidden = count === 0;
            countMessage.textContent = allFiltered
                ? `Đã chọn ${count} / ${total} revision phù hợp qua mọi trang${excluded.size ? `, bỏ chọn ${excluded.size} mục` : ''}.`
                : `Đã chọn ${count} revision trên trang này.`;
            submitButton.textContent = allFiltered ? `Duyệt tất cả (${count})` : `Duyệt mục đã chọn (${count})`;
            submitButton.dataset.reviewConfirmMessage = allFiltered
                ? `Bạn sắp duyệt ${count} revision trong toàn bộ kết quả phù hợp với bộ lọc hiện tại${excluded.size ? `, đã bỏ chọn ${excluded.size} mục` : ''}. Hệ thống chỉ duyệt revision còn chờ duyệt và đủ điều kiện; mục có lỗi nội dung, đã thay đổi hoặc bạn tự gửi (trừ Super Admin) sẽ được bỏ qua và nêu rõ lý do sau thao tác. ${approvalOutcome} Tiếp tục?`
                : `Bạn sắp duyệt ${count} revision đã chọn trên trang này. Hệ thống chỉ duyệt revision còn chờ duyệt và đủ điều kiện; mục có lỗi nội dung, đã thay đổi hoặc bạn tự gửi (trừ Super Admin) sẽ được bỏ qua và nêu rõ lý do sau thao tác. ${approvalOutcome} Tiếp tục?`;
            syncHiddenValues();
        };

        selectAll?.addEventListener('change', () => {
            allFiltered = selectAll.checked;
            selected.clear();
            excluded.clear();
            update();
        });
        rows().forEach(row => row.addEventListener('change', () => {
            if (allFiltered) {
                if (row.checked) excluded.delete(row.value);
                else excluded.add(row.value);
            } else if (row.checked) {
                selected.add(row.value);
            } else {
                selected.delete(row.value);
            }
            update();
        }));
        form.addEventListener('submit', async event => {
            if (form.dataset.submitting) {
                event.preventDefault();
                return;
            }
            event.preventDefault();
            const count = allFiltered ? Math.max(0, total - excluded.size) : selected.size;
            if (!pendingUrl && count === 0) return;
            syncHiddenValues();
            form.dataset.submitting = 'true';
            const post = async (url, body) => {
                const response = await fetch(url, {
                    method: 'POST', body,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const error = new Error(Object.values(result.errors ?? {}).flat().join(' ')
                        || result.message || 'Không thể tiếp tục duyệt. Hãy thử lại để xử lý các mục còn lại.');
                    error.status = response.status;
                    throw error;
                }
                return result;
            };
            const selectionControls = [selectAll, ...rows()].filter(input => input && !input.disabled);
            try {
                if (!pendingUrl) {
                    const confirmed = await requestAdminConfirmation(submitButton.dataset.reviewConfirmMessage, {
                        title: 'Duyệt nội dung', confirmLabel: submitButton.textContent, danger: false,
                    });
                    if (!confirmed) return;
                }
                form.setAttribute('aria-busy', 'true');
                selectionControls.forEach(input => { input.disabled = true; });
                submitButton.disabled = true;
                submitButton.textContent = submitButton.dataset.pendingLabel ?? 'Đang duyệt…';
                let progress;
                if (!pendingUrl) {
                    progress = await post(form.action, new FormData(form));
                    batchProgress = progress;
                    pendingUrl = progress.next_url;
                    showProgress(progress);
                }
                while (!progress?.done) {
                    const body = new FormData();
                    body.set('_token', form.querySelector('[name="_token"]').value);
                    progress = await post(pendingUrl, body);
                    batchProgress = progress;
                    showProgress(progress);
                }
                window.location.assign(progress.redirect_url);
            } catch (error) {
                if ([403, 410].includes(error.status)) pendingUrl = null;
                notify(error.message, true);
            } finally {
                delete form.dataset.submitting;
                form.removeAttribute('aria-busy');
                submitButton.disabled = false;
                selectionControls.forEach(input => { input.disabled = false; });
                update();
            }
        });
        update();
    };

    initializeBulkSelection(document);

    document.addEventListener('vistiq:ajax-results-updated', event => {
        const refreshedQueue = event.detail.targets?.find(target => target.matches('[data-review-queue-results]'));
        if (refreshedQueue) initializeBulkSelection(refreshedQueue);
    });

    const showConfirmation = async (form, button, inDialog) => {
        const wasOpen = inDialog && dialog.open;
        const trigger = activeButton;
        if (wasOpen) dialog.close();

        let confirmed;
        try {
            confirmed = await requestAdminConfirmation(form.dataset.reviewConfirmMessage, {
                title: 'Duyệt nội dung',
                confirmLabel: 'Xác nhận duyệt',
                danger: false,
            });
        } finally {
            if (wasOpen) {
                activeButton = trigger;
                dialog.showModal();
                button.focus({ preventScroll: true });
            }
        }

        return confirmed;
    };

    const submitDecision = async (form, inDialog) => {
        if (form.dataset.submitting) return;
        const action = form.dataset.reviewAction ?? 'approve';
        const button = form.querySelector('[type="submit"]');
        const originalLabel = button.textContent;
        const errorSummary = inDialog ? content.querySelector('[data-review-form-error]') : null;
        const noteError = form.querySelector('[data-review-field-error="note"]');
        form.dataset.submitting = 'true';
        if (errorSummary) {
            errorSummary.hidden = true;
            errorSummary.textContent = '';
        }
        if (noteError) noteError.textContent = '';

        try {
            if (action === 'approve') {
                const confirmed = await showConfirmation(form, button, inDialog);
                if (!confirmed) return;
            }

            form.setAttribute('aria-busy', 'true');
            button.disabled = true;
            button.textContent = button.dataset.pendingLabel ?? 'Đang xử lý…';
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                const fieldMessages = result.errors?.note ?? [];
                if (noteError && fieldMessages.length) noteError.textContent = fieldMessages.join(' ');
                throw new Error(Object.values(result.errors ?? {}).flat().join(' ')
                    || result.message
                    || (response.status === 403 ? 'Bạn chưa có quyền thực hiện thao tác này.' : 'Không thể xử lý revision. Kiểm tra nội dung rồi thử lại.'));
            }

            const successMessage = action === 'approve'
                ? 'Đã duyệt revision. Nội dung đang chờ xuất bản.'
                : 'Đã từ chối và trả revision về bản nháp.';
            const rowKey = form.dataset.reviewEntry ?? content.querySelector('[data-review-item]')?.dataset.reviewItem;
            if (inDialog) dialog.close();
            try {
                await refreshQueue();
            } catch {
                if (rowKey) document.querySelector(`[data-review-entry="${CSS.escape(rowKey)}"]`)?.remove();
            }
            notify(successMessage);
        } catch (error) {
            if (errorSummary) {
                errorSummary.hidden = false;
                errorSummary.textContent = error.message;
            } else {
                notify(error.message, true);
            }
        } finally {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            button.disabled = false;
            button.textContent = originalLabel;
        }
    };

    document.addEventListener('click', async event => {
        const openButton = event.target.closest('[data-review-open]');
        if (openButton) {
            event.preventDefault();
            if (openButton.disabled) return;
            activeButton = openButton;
            openButton.disabled = true;
            openButton.setAttribute('aria-busy', 'true');
            content.innerHTML = '<p class="content-review-loading" role="status">Đang tải nội dung revision…</p>';
            dialog.showModal();

            try {
                const response = await fetch(openButton.dataset.reviewUrl, {
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error(response.status === 403
                    ? 'Bạn chưa có quyền xem nội dung cần duyệt.'
                    : 'Không tải được nội dung revision. Hãy đóng cửa sổ và thử lại.');
                const html = await response.text();
                const documentView = new DOMParser().parseFromString(html, 'text/html');
                const review = documentView.querySelector('[data-review-item]');
                if (!review) throw new Error('Phiên đăng nhập có thể đã hết hạn. Hãy đăng nhập lại rồi thử mở revision.');
                content.replaceChildren(review);
                content.scrollTop = 0;
                content.querySelector('button, textarea, a[href]')?.focus({ preventScroll: true });
            } catch (error) {
                setMessage(error.message, true);
            } finally {
                openButton.disabled = false;
                openButton.removeAttribute('aria-busy');
            }
            return;
        }

        if (event.target.closest('[data-review-close]')) {
            dialog.close();
            return;
        }

        if (event.target === dialog) dialog.close();
    });

    dialog.addEventListener('submit', event => {
        const form = event.target.closest('[data-content-review-decision]');
        if (!form) return;
        event.preventDefault();
        submitDecision(form, true);
    });

    dialog.addEventListener('close', () => {
        if (activeButton?.isConnected) activeButton.focus({ preventScroll: true });
        activeButton = null;
    });
}
