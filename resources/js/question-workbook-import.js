const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);

document.querySelectorAll('[data-workbook-import]').forEach(container => {
    const uploadForm = container.querySelector('[data-workbook-upload]');
    const preview = container.querySelector('[data-workbook-preview]');
    const summary = container.querySelector('[data-workbook-summary]');
    const sheetsRoot = container.querySelector('[data-workbook-sheets]');
    const readingKind = container.dataset.kind === 'reading';
    const listeningKind = container.dataset.kind === 'listening';
    const subjectiveKind = ['writing', 'speaking'].includes(container.dataset.kind);
    const audioRoot = container.querySelector('[data-workbook-audio]');
    const imageRoot = container.querySelector('[data-workbook-images]');
    const errors = container.querySelector('[data-workbook-errors]');
    const confirmButton = container.querySelector('[data-workbook-confirm]');
    const resetButton = container.querySelector('[data-workbook-reset]');
    const progressPanel = container.querySelector('[data-workbook-progress]');
    const progressBar = container.querySelector('[data-workbook-progress-bar]');
    const progressLabel = container.querySelector('[data-workbook-progress-label]');
    const progressValue = container.querySelector('[data-workbook-progress-value]');
    const progressMessage = container.querySelector('[data-workbook-progress-message]');
    let token = null;
    let hasImportableQuestions = false;
    let totalQuestions = 0;
    let maxAudioMegabytes = 50;
    let maxAudioFileBytes = 50 * 1024 * 1024;
    let audioSheets = [];
    let audioFilesByCode = new Map();
    let autoMatchedAudioCodes = new Set();
    let unmatchedAudioFiles = [];
    let bulkAudioFileCount = 0;
    let invalidBulkAudioFiles = [];
    let mappingIssues = new Map();
    let manualAudioIssues = new Map();
    let stagedAudioCodes = new Set();

    const showError = message => {
        errors.hidden = !message;
        errors.textContent = message || '';
    };
    const setProgress = ({ visible = true, value = null, label = '', message = '' } = {}) => {
        progressPanel.hidden = !visible;
        progressLabel.textContent = label;
        progressMessage.textContent = message;
        if (value === null) {
            progressBar.removeAttribute('value');
            progressValue.textContent = '…';
        } else {
            const percentage = Math.max(0, Math.min(100, Number(value) || 0));
            progressBar.value = percentage;
            progressValue.textContent = `${Math.round(percentage)}%`;
        }
    };
    const formatBytes = bytes => {
        if (bytes <= 0) return '0 KB';
        if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    };
    const jsonPayload = (text, status) => {
        try {
            return JSON.parse(text);
        } catch {
            const plain = String(text || '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/gi, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
            if (/post_max_size|content-length/.test(plain)) {
                throw new Error('Tổng file vượt giới hạn nhận của máy chủ. Audio đang được gửi riêng từng bài; hãy kiểm tra giới hạn PHP nếu lỗi còn lặp lại.');
            }
            if (/upload_max_filesize|upload_max_files/.test(plain)) {
                throw new Error('Một file audio vượt giới hạn tải lên của PHP. Hãy giảm dung lượng file hoặc tăng upload_max_filesize.');
            }
            throw new Error(`Máy chủ trả trang lỗi thay vì JSON (HTTP ${status}). Hãy thử lại; nếu lỗi tiếp diễn, kiểm tra nhật ký PHP/Laravel.`);
        }
    };
    const xhrPost = (url, data, onProgress = () => {}) => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.upload.addEventListener('progress', event => {
            if (event.lengthComputable) onProgress(event.loaded, event.total);
        });
        xhr.addEventListener('load', () => {
            try {
                resolve({ status: xhr.status, ok: xhr.status >= 200 && xhr.status < 300, result: jsonPayload(xhr.responseText, xhr.status) });
            } catch (error) {
                reject(error);
            }
        });
        xhr.addEventListener('error', () => reject(new Error('Mất kết nối khi gửi file. Hãy kiểm tra mạng rồi thử xác nhận lại.')));
        xhr.addEventListener('abort', () => reject(new Error('Đã hủy tải file.')));
        xhr.send(data);
    });
    const normalizeAudioName = value => String(value ?? '')
        .replace(/\.[^.]+$/, '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '')
        .replace(/^audio/, '')
        .replace(/audio$/, '');
    const audioSequence = value => normalizeAudioName(value).match(/(\d+)$/)?.[1].replace(/^0+(?=\d)/, '') || null;
    const sourceSheetName = sheet => String(sheet.source_sheet || '').trim()
        || String(sheet.title || '').split('→')[0].replace(/^Sheet\s*/i, '').trim()
        || String(sheet.code || '');
    const audioFileIssue = file => {
        const extension = file.name.split('.').pop()?.toLowerCase() || '';
        if (!['mp3', 'wav', 'm4a', 'ogg'].includes(extension)) return 'chỉ nhận MP3, WAV, M4A hoặc OGG';
        if (file.size > maxAudioMegabytes * 1024 * 1024) return `vượt giới hạn ${maxAudioMegabytes} MB`;
        if (file.size > maxAudioFileBytes) return `vượt giới hạn tải hiện tại ${formatBytes(maxAudioFileBytes)} mỗi file`;
        return '';
    };
    const updateAudioRows = () => {
        sheetsRoot.querySelectorAll('[data-audio-row]').forEach(row => {
            const file = audioFilesByCode.get(row.dataset.sheetCode);
            const status = row.querySelector('[data-audio-status]');
            const chooseButton = row.querySelector('[data-audio-choose]');
            status.textContent = file?.name || 'Chưa ghép audio';
            row.classList.toggle('is-matched', Boolean(file));
            chooseButton.textContent = file ? 'Thay file' : 'Chọn file';
            chooseButton.setAttribute('aria-label', `${file ? 'Thay' : 'Chọn'} audio cho bài ${row.dataset.sourceSheet}`);
        });
    };
    const updateAudioSummary = () => {
        const summaryElement = audioRoot.querySelector('[data-workbook-audio-summary]');
        const orderButton = audioRoot.querySelector('[data-audio-map-order]');
        if (!summaryElement || !listeningKind) return;

        const matched = audioSheets.filter(sheet => audioFilesByCode.has(sheet.code)).length;
        const missing = audioSheets.length - matched;
        if (orderButton) {
            const canMapByOrder = missing > 0 && missing === unmatchedAudioFiles.length;
            orderButton.hidden = !canMapByOrder;
            orderButton.textContent = canMapByOrder
                ? `Ghép ${missing} file còn lại theo thứ tự tên`
                : 'Ghép file theo thứ tự tên';
        }
        const message = [];
        message.push(bulkAudioFileCount
            ? `Đã chọn ${bulkAudioFileCount} file · ghép ${matched}/${audioSheets.length} bài (tự động ${autoMatchedAudioCodes.size}).`
            : `Đã ghép ${matched}/${audioSheets.length} bài.`);
        if (missing) message.push(`Còn thiếu audio cho ${missing} bài; xem nhãn từng bài trong danh sách.`);
        if (unmatchedAudioFiles.length) {
            const names = unmatchedAudioFiles.slice(0, 4).map(file => file.name).join(', ');
            message.push(`File chưa ghép: ${names}${unmatchedAudioFiles.length > 4 ? ` và ${unmatchedAudioFiles.length - 4} file khác` : ''}.`);
        }
        const issues = [...invalidBulkAudioFiles, ...mappingIssues.values(), ...manualAudioIssues.values()];
        if (issues.length) message.push(issues.join(' · '));
        summaryElement.textContent = message.join(' ');

        if (hasImportableQuestions) {
            const invalidFiles = invalidBulkAudioFiles.length > 0 || manualAudioIssues.size > 0;
            confirmButton.disabled = missing > 0 || invalidFiles;
            confirmButton.textContent = missing
                ? `Còn thiếu audio cho ${missing} bài`
                : invalidFiles
                    ? 'Sửa file audio không hợp lệ'
                    : `Xác nhận import ${totalQuestions} câu`;
        }
    };
    const mapBulkAudioFiles = files => {
        audioFilesByCode = new Map();
        autoMatchedAudioCodes = new Set();
        stagedAudioCodes.clear();
        unmatchedAudioFiles = [];
        invalidBulkAudioFiles = [];
        mappingIssues = new Map();
        manualAudioIssues = new Map();
        bulkAudioFileCount = files.length;

        const availableFiles = [];
        files.forEach(file => {
            const issue = audioFileIssue(file);
            if (issue) invalidBulkAudioFiles.push(`${file.name}: ${issue}.`);
            else availableFiles.push(file);
        });
        const assignedFiles = new Set();
        const ambiguousCodes = new Set();

        audioSheets.forEach(sheet => {
            const aliases = new Set([normalizeAudioName(sheet.sourceSheet), normalizeAudioName(sheet.code)].filter(Boolean));
            const matches = availableFiles.filter(file => !assignedFiles.has(file) && aliases.has(normalizeAudioName(file.name)));
            if (matches.length === 1) {
                audioFilesByCode.set(sheet.code, matches[0]);
                autoMatchedAudioCodes.add(sheet.code);
                assignedFiles.add(matches[0]);
            } else if (matches.length > 1) {
                mappingIssues.set(sheet.code, `${sheet.sourceSheet}: có nhiều file trùng tên, hãy chọn audio cho bài này.`);
                ambiguousCodes.add(sheet.code);
            }
        });

        audioSheets.forEach(sheet => {
            if (audioFilesByCode.has(sheet.code) || ambiguousCodes.has(sheet.code)) return;
            const sequence = audioSequence(sheet.sourceSheet) || audioSequence(sheet.code);
            if (!sequence) return;
            const matches = availableFiles.filter(file => !assignedFiles.has(file) && audioSequence(file.name) === sequence);
            if (matches.length === 1) {
                audioFilesByCode.set(sheet.code, matches[0]);
                autoMatchedAudioCodes.add(sheet.code);
                assignedFiles.add(matches[0]);
            } else if (matches.length > 1) {
                mappingIssues.set(sheet.code, `${sheet.sourceSheet}: có nhiều file cùng số thứ tự, hãy chọn audio cho bài này.`);
            }
        });

        unmatchedAudioFiles = availableFiles.filter(file => !assignedFiles.has(file));
        updateAudioRows();
        updateAudioSummary();
    };

    audioRoot.addEventListener('change', event => {
        const input = event.target.closest('[data-workbook-audio-batch], [data-workbook-audio-folder]');
        if (input) mapBulkAudioFiles([...(input.files || [])]);
    });
    audioRoot.addEventListener('click', event => {
        const folderButton = event.target.closest('[data-workbook-audio-folder-pick]');
        if (folderButton) {
            audioRoot.querySelector('[data-workbook-audio-folder]')?.click();
            return;
        }

        const button = event.target.closest('[data-audio-map-order]');
        if (!button) return;

        const missingSheets = audioSheets.filter(sheet => !audioFilesByCode.has(sheet.code));
        const orderedFiles = [...unmatchedAudioFiles].sort((left, right) => left.name.localeCompare(right.name, 'vi', { numeric: true, sensitivity: 'base' }));
        if (!missingSheets.length || missingSheets.length !== orderedFiles.length) return;

        missingSheets.forEach((sheet, index) => {
            audioFilesByCode.set(sheet.code, orderedFiles[index]);
            autoMatchedAudioCodes.add(sheet.code);
        });
        unmatchedAudioFiles = [];
        updateAudioRows();
        updateAudioSummary();
    });
    sheetsRoot.addEventListener('click', event => {
        const button = event.target.closest('[data-audio-choose]');
        if (!button) return;
        button.closest('[data-audio-row]')?.querySelector('[data-audio-override]')?.click();
    });
    sheetsRoot.addEventListener('change', event => {
        const input = event.target.closest('[data-audio-override]');
        if (!input) return;
        const file = input.files?.[0];
        if (!file) return;
        const row = input.closest('[data-audio-row]');
        const code = row.dataset.sheetCode;
        const issue = audioFileIssue(file);
        if (issue) {
            manualAudioIssues.set(code, `${file.name}: ${issue}.`);
            input.value = '';
            updateAudioSummary();
            return;
        }

        audioFilesByCode.set(code, file);
        stagedAudioCodes.delete(code);
        autoMatchedAudioCodes.delete(code);
        mappingIssues.delete(code);
        manualAudioIssues.delete(code);
        unmatchedAudioFiles = unmatchedAudioFiles.filter(unmatched => unmatched !== file);
        updateAudioRows();
        updateAudioSummary();
    });
    sheetsRoot.addEventListener('input', event => {
        const editor = event.target.closest('[data-reading-sheet-editor]');
        if (!editor) return;

        const title = editor.querySelector('[name="title"]')?.value.trim() || 'Chưa có tiêu đề bài đọc';
        const body = editor.querySelector('[name="body"]')?.value || '';
        editor.querySelector('.workbook-reading-card-heading b').textContent = title;
        editor.querySelector('.workbook-reading-card-heading span').textContent = `${body.trim() ? body.trim().split(/\s+/).length : 0} từ`;
        const excerpt = body.replace(/\s+/g, ' ').trim();
        editor.querySelector('.workbook-reading-excerpt').textContent = excerpt
            ? `${excerpt.slice(0, 260)}${excerpt.length > 260 ? '…' : ''}`
            : 'Workbook chưa có đoạn văn. Thêm nội dung tại đây trước khi import.';
    });
    const csrf = () => uploadForm.querySelector('[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const post = async (url, data) => fetch(url, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
    const stageMappedAudioFiles = async () => {
        if (!listeningKind) return;

        const entries = [...audioFilesByCode.entries()];
        const pending = entries.filter(([code]) => !stagedAudioCodes.has(code));
        const totalBytes = entries.reduce((sum, [, file]) => sum + file.size, 0);
        const transferred = new Map(entries.map(([code, file]) => [code, stagedAudioCodes.has(code) ? file.size : 0]));
        const total = entries.length;
        let completed = entries.length - pending.length;
        let nextIndex = 0;
        let uploadError = null;

        const update = () => {
            const loaded = [...transferred.values()].reduce((sum, value) => sum + value, 0);
            setProgress({
                value: totalBytes ? loaded / totalBytes * 100 : 100,
                label: `Đang tải audio ${completed}/${total}`,
                message: `${formatBytes(loaded)} / ${formatBytes(totalBytes)} đã tải lên`,
            });
        };
        update();

        const worker = async () => {
            while (nextIndex < pending.length && !uploadError) {
                const [code, file] = pending[nextIndex++];
                const data = new FormData();
                data.append('_token', csrf());
                data.append('token', token);
                data.append('code', code);
                data.append('audio', file, file.name);
                try {
                    const response = await xhrPost(container.dataset.audioUrl, data, (loaded, requestBytes) => {
                        transferred.set(code, Math.min(file.size, Math.round(file.size * loaded / requestBytes)));
                        update();
                    });
                    if (!response.ok) {
                        const details = Object.values(response.result.errors || {}).flat().join('\n');
                        throw new Error(details || response.result.message || `Không tải được audio cho ${code}.`);
                    }
                    stagedAudioCodes.add(code);
                    transferred.set(code, file.size);
                    completed++;
                    update();
                } catch (error) {
                    uploadError = new Error(`Audio ${file.name} (${code}): ${error.message}`);
                }
            }
        };

        await Promise.all(Array.from({ length: Math.min(4, pending.length) }, () => worker()));
        if (uploadError) throw uploadError;
        setProgress({ value: 100, label: `Đã tải ${total} audio`, message: 'Đang lưu câu hỏi và liên kết audio…' });
    };

    uploadForm.addEventListener('submit', async event => {
        event.preventDefault();
        const uploadButton = uploadForm.querySelector('[type="submit"]');
        if (uploadButton.disabled) return;
        uploadButton.disabled = true;
        const uploadLabel = uploadButton.textContent;
        uploadButton.textContent = 'Đang đọc workbook…';
        showError('');
        setProgress({ visible: false });
        confirmButton.disabled = true;
        confirmButton.textContent = 'Đang đọc workbook…';
        const data = new FormData(uploadForm);
        data.append('kind', container.dataset.kind);
        try {
            const response = await post(container.dataset.previewUrl, data);
            const result = jsonPayload(await response.text(), response.status);
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join('\n') || result.message || 'Không thể đọc workbook.');
            token = result.token;
            const sheets = result.sheets || [];
            totalQuestions = Number(result.total_questions || 0);
            maxAudioMegabytes = Number(result.max_audio_megabytes) || 50;
            maxAudioFileBytes = Number(result.max_audio_file_bytes) || maxAudioMegabytes * 1024 * 1024;
            audioSheets = listeningKind
                ? sheets
                    .filter(sheet => !sheet.blocked && Number(sheet.question_count) > 0)
                    .map(sheet => ({ code: String(sheet.code), sourceSheet: sourceSheetName(sheet) }))
                : [];
            hasImportableQuestions = (container.dataset.kind === 'thpt_english' || subjectiveKind)
                ? result.can_import === true
                : sheets.some(sheet => !sheet.blocked && sheet.question_count > 0);
            summary.textContent = container.dataset.kind === 'thpt_english'
                ? `Đã đọc ${sheets.length} phần · ${result.total_questions || 0}/40 câu hợp lệ${hasImportableQuestions ? '' : ' · cần xử lý lỗi trước khi import'}.`
                : `Đã nhận diện ${sheets.length} sheet · ${result.total_questions || 0} câu hợp lệ.`;
            sheetsRoot.innerHTML = sheets.map(sheet => {
                const errorList = sheet.errors?.length
                    ? `<ul>${sheet.errors.map(error => `<li>Dòng ${Number(error.row)}: ${escapeHtml(error.message)}</li>`).join('')}</ul>`
                    : '<small>Không có lỗi dữ liệu.</small>';
                const sheetTitle = escapeHtml(sheet.title || sheet.code);
                const questionCount = `${Number(sheet.question_count || 0)}${sheet.expected_count ? `/${Number(sheet.expected_count)}` : ''} câu`;
                const code = escapeHtml(sheet.code);
                const sourceSheet = escapeHtml(sourceSheetName(sheet));
                const audioField = listeningKind && !sheet.blocked && sheet.question_count > 0
                    ? `<div class="workbook-audio-row" data-audio-row data-sheet-code="${code}" data-source-sheet="${sourceSheet}"><span data-audio-status>Chưa ghép audio</span><button class="button button-quiet" type="button" data-audio-choose aria-label="Chọn audio cho bài ${sourceSheet}">Chọn file</button><input type="file" data-audio-override accept=".mp3,.wav,.m4a,.ogg,audio/*" aria-label="Chọn audio cho bài ${sourceSheet}" hidden></div>`
                    : '';
                const sheetHeading = `<div class="workbook-sheet-heading"><div class="workbook-sheet-title"><b>${sheetTitle}</b><span>${questionCount}</span></div>${audioField}</div>`;
                if (subjectiveKind) {
                    const questions = (sheet.questions || []).map(question => `<li><b>Dòng ${Number(question.row)} · ${escapeHtml(question.part_name)}</b><p>${escapeHtml(question.prompt)}</p>${question.min_words ? `<small>Tối thiểu ${Number(question.min_words)} từ</small>` : ''}</li>`).join('');
                    return `<article class="workbook-sheet-row">${sheetHeading}${errorList}${questions ? `<details><summary>Xem ${Number(sheet.question_count)} câu hỏi</summary><ol>${questions}</ol></details>` : ''}</article>`;
                }
                if (!readingKind || sheet.blocked || !sheet.question_count) {
                    return `<article class="workbook-sheet-row">${sheetHeading}${errorList}</article>`;
                }

                const title = String(sheet.reading_title || '');
                const body = String(sheet.reading_body || '');
                const excerpt = body.replace(/\s+/g, ' ').trim();
                const previewText = excerpt ? `${excerpt.slice(0, 260)}${excerpt.length > 260 ? '…' : ''}` : 'Workbook chưa có đoạn văn. Thêm nội dung tại đây trước khi import.';
                const wordCount = body.trim() ? body.trim().split(/\s+/).length : 0;
                const open = !title || !body ? ' open' : '';
                return `<article class="workbook-sheet-row workbook-reading-sheet"><div><b>${escapeHtml(sheet.code)}</b><span>${Number(sheet.question_count)} câu</span></div>${errorList}<section class="workbook-reading-card" data-reading-sheet-editor data-sheet-code="${code}"><div class="workbook-reading-card-heading"><b>${escapeHtml(title || 'Chưa có tiêu đề bài đọc')}</b><span>${wordCount} từ</span></div><p class="workbook-reading-excerpt">${escapeHtml(previewText)}</p><details class="workbook-reading-fields"${open}><summary>Xem / sửa nội dung bài đọc</summary><label>Tiêu đề bài đọc<input name="title" value="${escapeHtml(title)}" maxlength="255" placeholder="Nhập tiêu đề bài đọc" required></label><label>Đoạn văn<textarea name="body" rows="7" maxlength="50000" placeholder="Dán hoặc nhập đoạn văn cần đọc" required>${escapeHtml(body)}</textarea></label></details></section></article>`;
            }).join('');
            audioFilesByCode.clear();
            autoMatchedAudioCodes.clear();
            stagedAudioCodes.clear();
            unmatchedAudioFiles = [];
            invalidBulkAudioFiles = [];
            mappingIssues.clear();
            manualAudioIssues.clear();
            bulkAudioFileCount = 0;
            audioRoot.innerHTML = listeningKind && audioSheets.length
                ? `<div class="workbook-audio-batch"><div class="workbook-audio-batch-pickers"><label><span>Chọn nhiều file (Ctrl+A)</span><input type="file" data-workbook-audio-batch multiple accept=".mp3,.wav,.m4a,.ogg,audio/*"></label><button class="button button-quiet" type="button" data-workbook-audio-folder-pick>Chọn cả thư mục audio</button><input type="file" data-workbook-audio-folder webkitdirectory directory multiple accept=".mp3,.wav,.m4a,.ogg,audio/*" hidden></div><small>Tự ghép theo tên sheet/mã bài, ví dụ <code>LISTENING_001.mp3</code>. Trong hộp thoại file, nhấn Ctrl+A để chọn hết; hoặc chọn cả thư mục audio. File còn thiếu có thể ghép theo thứ tự tên hoặc chọn riêng từng bài. MP3, WAV, M4A, OGG · tối đa ${formatBytes(Math.min(maxAudioMegabytes * 1024 * 1024, maxAudioFileBytes))}/file theo giới hạn hệ thống.</small><p data-workbook-audio-summary role="status">Chọn nhiều file hoặc cả thư mục audio một lần.</p><button class="button button-quiet" type="button" data-audio-map-order hidden>Ghép file theo thứ tự tên</button></div>`
                : '';
            const groupImages = sheets
                .filter(sheet => sheet.requires_group_image && !sheet.blocked && sheet.question_count > 0)
                .map(sheet => `<fieldset class="workbook-image-group"><legend>Ảnh văn bản · ${escapeHtml(sheet.title || sheet.code)}</legend><div><label class="workbook-image-row"><span>Ảnh dùng chung cho ${Number(sheet.question_count)} câu <em>*</em><small>JPG, PNG hoặc WEBP</small></span><input type="file" name="group_images[${escapeHtml(sheet.part_code || sheet.exam_part)}]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></label></div></fieldset>`);
            const questionImages = sheets
                .filter(sheet => sheet.requires_question_images && !sheet.blocked && sheet.question_count > 0)
                .map(sheet => `<fieldset class="workbook-image-group"><legend>Ảnh · TOEIC Listening Part 1 · ${escapeHtml(sheet.code)}</legend><div>${(sheet.questions || []).map(question => `<label class="workbook-image-row"><span><b>Dòng ${Number(question.row)}</b> · ${escapeHtml(question.prompt)} <em>*</em></span><input type="file" name="images[${escapeHtml(sheet.code)}][${Number(question.row)}]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></label>`).join('')}</div></fieldset>`);
            imageRoot.innerHTML = [...groupImages, ...questionImages].join('');
            confirmButton.hidden = !hasImportableQuestions;
            confirmButton.disabled = listeningKind && audioSheets.length > 0;
            confirmButton.textContent = listeningKind && audioSheets.length > 0
                ? `Còn thiếu audio cho ${audioSheets.length} bài`
                : `Xác nhận import ${totalQuestions} câu`;
            updateAudioRows();
            updateAudioSummary();
            preview.hidden = false;
            uploadForm.hidden = true;
        } catch (error) {
            showError(error.message || 'Không thể đọc workbook.');
            preview.hidden = false;
            confirmButton.hidden = true;
        } finally {
            uploadButton.disabled = false;
            uploadButton.textContent = uploadLabel;
        }
    });

    confirmButton.addEventListener('click', async () => {
        if (!token || !hasImportableQuestions) return;
        showError('');
        const data = new FormData();
        data.append('_token', csrf());
        data.append('token', token);
        const readingEditors = [...sheetsRoot.querySelectorAll('[data-reading-sheet-editor]')];
        const emptyReading = readingEditors.find(editor => !editor.querySelector('[name="title"]')?.value.trim() || !editor.querySelector('[name="body"]')?.value.trim());
        if (emptyReading) {
            const missingInput = !emptyReading.querySelector('[name="title"]')?.value.trim()
                ? emptyReading.querySelector('[name="title"]')
                : emptyReading.querySelector('[name="body"]');
            emptyReading.querySelector('.workbook-reading-fields')?.setAttribute('open', '');
            missingInput?.focus();
            showError(`Bổ sung tiêu đề và đoạn văn cho bài ${emptyReading.dataset.sheetCode} trước khi import.`);
            return;
        }
        readingEditors.forEach(editor => {
            const code = editor.dataset.sheetCode;
            data.append(`readings[${code}][title]`, editor.querySelector('[name="title"]').value);
            data.append(`readings[${code}][body]`, editor.querySelector('[name="body"]').value);
        });
        if (listeningKind) {
            const missingAudio = audioSheets.filter(sheet => !audioFilesByCode.has(sheet.code));
            if (missingAudio.length) {
                showError(`Vui lòng ghép audio cho ${missingAudio.length} bài còn thiếu trong danh sách.`);
                return;
            }
        }
        const uploadInputs = [...imageRoot.querySelectorAll('input[type="file"]')];
        const missingUpload = uploadInputs.find(input => !input.files?.[0]);
        if (missingUpload) {
            const label = missingUpload.closest('label')?.querySelector('span')?.textContent?.replace('*', '').trim();
            showError(`Vui lòng đính kèm ${label || 'đầy đủ file media'} trước khi xác nhận.`);
            return;
        }
        uploadInputs.forEach(input => {
            if (input.files?.[0]) data.append(input.name, input.files[0]);
        });
        confirmButton.disabled = true;
        confirmButton.textContent = listeningKind ? 'Đang tải audio…' : 'Đang import…';
        try {
            await stageMappedAudioFiles();
            const response = await xhrPost(container.dataset.confirmUrl, data, (loaded, requestBytes) => {
                const percentage = loaded / requestBytes * 100;
                setProgress(percentage >= 100
                    ? { value: null, label: 'Đang xử lý import…', message: subjectiveKind ? 'Đang lưu câu hỏi bản nháp. Vui lòng giữ cửa sổ này mở.' : 'Đang lưu câu hỏi và liên kết audio. Vui lòng giữ cửa sổ này mở.' }
                    : { value: percentage, label: 'Đang gửi dữ liệu import…', message: `${formatBytes(loaded)} / ${formatBytes(requestBytes)}` });
            });
            const result = response.result;
            if (!response.ok) {
                const sheetErrors = Object.entries(result.sheets || {}).flatMap(([code, messages]) => messages.map(message => `${code}: ${message}`));
                const fieldErrors = Object.values(result.errors || {}).flat().join('\n');
                throw new Error(sheetErrors.join('\n') || fieldErrors || result.message || 'Không thể xác nhận import.');
            }
            const failed = Object.entries(result.errors || {}).flatMap(([code, messages]) => messages.map(message => `${code}: ${message}`));
            summary.textContent = `Đã import ${result.summary?.imported || 0}/${result.summary?.total || 0} câu ở trạng thái bản nháp.`;
            if (failed.length) showError(failed.join('\n'));
            setProgress({ value: 100, label: 'Import hoàn tất', message: `${result.summary?.imported || 0}/${result.summary?.total || 0} câu đã được lưu.` });
            confirmButton.hidden = true;
            if (result.redirect && !failed.length) window.location.assign(result.redirect);
        } catch (error) {
            showError(error.message || 'Không thể xác nhận import.');
            setProgress({ value: null, label: 'Import chưa hoàn tất', message: subjectiveKind ? 'Kiểm tra lỗi hiển thị rồi thử lại. Nếu lỗi nằm trong file, chọn “Tải workbook khác” để tải bản đã sửa.' : 'Các audio đã nhận được giữ lại; thử xác nhận lại để tiếp tục.' });
            confirmButton.disabled = false;
            confirmButton.textContent = 'Thử xác nhận lại';
        }
    });

    resetButton.addEventListener('click', () => {
        const discardedToken = token;
        if (discardedToken && listeningKind && container.dataset.discardAudioUrl) {
            const data = new FormData();
            data.append('_token', csrf());
            data.append('token', discardedToken);
            post(container.dataset.discardAudioUrl, data).catch(() => {});
        }
        token = null;
        hasImportableQuestions = false;
        totalQuestions = 0;
        maxAudioMegabytes = 50;
        maxAudioFileBytes = 50 * 1024 * 1024;
        audioSheets = [];
        audioFilesByCode.clear();
        autoMatchedAudioCodes.clear();
        stagedAudioCodes.clear();
        unmatchedAudioFiles = [];
        bulkAudioFileCount = 0;
        invalidBulkAudioFiles = [];
        mappingIssues.clear();
        manualAudioIssues.clear();
        uploadForm.reset();
        uploadForm.hidden = false;
        preview.hidden = true;
        setProgress({ visible: false });
        confirmButton.hidden = true;
        confirmButton.disabled = false;
        confirmButton.textContent = 'Xác nhận import';
        sheetsRoot.replaceChildren();
        audioRoot.replaceChildren();
        imageRoot.replaceChildren();
        showError('');
    });
});
