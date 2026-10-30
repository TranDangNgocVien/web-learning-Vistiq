import { createIcons, Plus, Search, RotateCcw, FileText, Files, Volume2, Eye, SquarePen, ExternalLink, X, Clock3, List, Star, UserRound, CalendarDays, RefreshCw, Settings2, Database, Info, Archive, ArrowLeft, ArrowUp, ArrowDown, Save, CircleCheck, Link, ChevronLeft, ChevronRight, Trash2, Headphones, BookOpen, Target, Check, SlidersHorizontal, ClipboardCheck, Trophy, CircleAlert } from 'lucide';

const icons = { Plus, Search, RotateCcw, FileText, Files, Volume2, Eye, SquarePen, ExternalLink, X, Clock3, List, Star, UserRound, CalendarDays, RefreshCw, Settings2, Database, Info, Archive, ArrowLeft, ArrowUp, ArrowDown, Save, CircleCheck, Link, ChevronLeft, ChevronRight, Trash2, Headphones, BookOpen, Target, Check, SlidersHorizontal, ClipboardCheck, Trophy, CircleAlert };
const refreshIcons = (root = document) => {
    root.querySelectorAll('i[data-ex-icon]').forEach(element => { element.dataset.exPending = element.dataset.exIcon; });
    createIcons({ icons, nameAttr: 'data-ex-pending', attrs: { 'aria-hidden': 'true' } });
    root.querySelectorAll('svg[data-ex-pending]').forEach(element => element.removeAttribute('data-ex-pending'));
};
const encode = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const icon = name => `<i data-ex-icon="${name}"></i>`;
const colors = ['#3980ff', '#5c8bf5', '#2bb0b4', '#5885fb', '#a475f7', '#38a6c2', '#ffbf43'];
const difficultyNames = { easy: 'Dễ', medium: 'Trung bình', hard: 'Khó' };
const questionTypeNames = { multiple_choice: 'Trắc nghiệm', multiple_response: 'Trắc nghiệm nhiều đáp án', sentence_completion: 'Điền câu', note_completion: 'Điền ghi chú', matching: 'Nối cặp', matching_features: 'Ghép thông tin', matching_headings: 'Nối tiêu đề', matching_information: 'Nối thông tin', matching_sentence_endings: 'Nối phần cuối câu', short_answer: 'Trả lời ngắn', essay: 'Tự luận', diagram_label_completion: 'Ghi nhãn sơ đồ', flow_chart_completion: 'Hoàn thành lưu đồ', form_completion: 'Điền biểu mẫu', plan_map_labelling: 'Ghi nhãn bản đồ', summary_completion: 'Hoàn thành tóm tắt', table_completion: 'Hoàn thành bảng', true_false_not_given: 'Đúng / Sai / Không có thông tin', yes_no_not_given: 'Có / Không / Không có thông tin', speaking: 'Nói' };
const thptEnglishPartNames = {
    'tieng-anh-part-1': 'Điền từ ngắn · Văn bản 1',
    'tieng-anh-part-2': 'Điền từ ngắn · Văn bản 2',
    'tieng-anh-part-3': 'Sắp xếp câu / lượt lời',
    'tieng-anh-part-4': 'Điền cụm / mệnh đề',
    'tieng-anh-part-5': 'Đọc hiểu · 8 câu',
    'tieng-anh-part-6': 'Đọc hiểu · 10 câu',
};
const partName = part => part?.code?.startsWith('part-') ? part.code.replace('part-', 'Part ') : thptEnglishPartNames[part?.code] ?? part?.name ?? 'Phần thi';
const partLabel = part => {
    if (!part) return 'part';
    const code = String(part.code || '');
    if (part.type_code === 'toeic' && /^part-\d+$/.test(code)) return `TOEIC Part ${code.slice(5)}`;
    if (part.type_code === 'ielts') {
        const skillName = { listening: 'Listening', reading: 'Reading', writing: 'Writing', speaking: 'Speaking' }[part.skill] || partName(part);
        const partNumber = code.match(/^(?:listening|reading|speaking)-part-(\d+)$/)?.[1];
        const taskNumber = code.match(/^writing-task-(\d+)$/)?.[1];
        if (partNumber) return `IELTS ${skillName} Part ${partNumber}`;
        if (taskNumber) return `IELTS Writing Task ${taskNumber}`;
        return `IELTS ${partName(part)}`;
    }
    return partName(part);
};

const list = document.querySelector('[data-exam-list]');
if (list) {
    const filters = list.querySelector('[data-exam-filters]');
    const syncFilterButtons = () => filters.querySelectorAll('[data-exam-filter]').forEach(button => {
        const input = filters.elements.namedItem(button.dataset.examFilter);
        const active = input?.value === button.dataset.filterValue;
        button.classList.toggle('is-active', active && button.dataset.examFilter === 'type');
        button.setAttribute('aria-pressed', String(active));
    });
    syncFilterButtons();
    filters.addEventListener('change', event => { if (event.target.matches('[data-filter-auto]')) filters.requestSubmit(); });
    filters.addEventListener('click', event => {
        const button = event.target.closest('[data-exam-filter]');
        if (!button) return;
        const input = filters.elements.namedItem(button.dataset.examFilter);
        input.value = button.getAttribute('aria-pressed') === 'true' ? '' : button.dataset.filterValue;
        syncFilterButtons();
        filters.requestSubmit();
    });
    const detailPanel = () => list.querySelector('[data-exam-detail-panel]');
    const openDetail = id => {
        const panel = detailPanel();
        if (!panel) return;
        panel.hidden = false;
        list.classList.remove('is-detail-closed');
        list.querySelectorAll('[data-exam-row]').forEach(row => row.classList.toggle('is-active', row.dataset.examRow === id));
        list.querySelectorAll('[data-exam-detail-body]').forEach(body => { body.hidden = body.dataset.examDetailBody !== id; });
        if (matchMedia('(max-width: 900px)').matches) panel.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
    };
    list.addEventListener('click', event => {
        const button = event.target.closest('[data-exam-detail]');
        if (button) openDetail(button.dataset.examDetail);
        const row = event.target.closest('[data-exam-row]');
        if (row && !event.target.closest('a,button,input')) openDetail(row.dataset.examRow);
        if (event.target.closest('[data-exam-close-detail]')) { const panel = detailPanel(); if (panel) panel.hidden = true; list.classList.add('is-detail-closed'); }
    });
    const syncSelection = () => {
        const selected = [...list.querySelectorAll('[data-exam-select]')];
        const all = list.querySelector('[data-exam-select-all]');
        const count = selected.filter(input => input.checked).length;
        if (all) {
            all.checked = count > 0 && count === selected.length;
            all.indeterminate = count > 0 && count < selected.length;
        }
        const summary = list.querySelector('[data-exam-selection-count]');
        if (summary) summary.textContent = count ? `· Đã chọn ${count}` : '';
    };
    list.addEventListener('change', event => {
        const all = event.target.closest('[data-exam-select-all]');
        if (all) {
            list.querySelectorAll('[data-exam-select]').forEach(input => { input.checked = all.checked; });
            syncSelection();
            return;
        }
        if (event.target.closest('[data-exam-select]')) syncSelection();
        const pageSize = event.target.closest('[data-exam-page-size]');
        if (pageSize) {
            filters.elements.namedItem('per_page').value = pageSize.value;
            filters.dataset.ajaxHistoryMode = 'replace';
            filters.requestSubmit();
        }
    });
    document.addEventListener('vistiq:ajax-results-updated', event => {
        if (!event.detail?.selector?.includes('#admin-exam-results')) return;
        syncFilterButtons();
        syncSelection();
        event.detail.targets?.forEach(refreshIcons);
    });
}

const builder = document.querySelector('[data-exam-builder]');
if (builder) {
    const catalog = JSON.parse(builder.querySelector('[data-builder-catalog]').textContent);
    const field = selector => builder.querySelector(selector);
    const fields = selector => [...builder.querySelectorAll(selector)];
    const serialized = field('[data-sections-json]'), generationInput = field('[data-generation-json]');
    const template = field('[data-structure-template]'), examType = field('[data-exam-type]');
    const variant = field('[name="variant"]'), mode = field('[name="mode"]'), policiesInput = field('[data-policies-json]'), duration = field('[data-exam-duration]');
    const feedback = field('[data-builder-feedback]');
    const questionById = new Map(catalog.questions.map(question => [Number(question.id), question]));
    const partById = new Map(catalog.parts.map(part => [Number(part.id), part]));
    const getPart = section => partById.get(Number(section?.exam_part_id));
    let sections = JSON.parse(serialized.value || '[]').map(section => ({ ...section, exam_part_id: Number(section.exam_part_id), question_ids: (section.question_ids || []).map(Number) }));
    let generation = generationInput.value ? JSON.parse(generationInput.value) : null;
    let activeThptPartId = Number(field('[data-thpt-subject]')?.value || 0);
    let activePart = null, previewIndex = 0, activeStep = Number(builder.dataset.initialStep || 0), visualTemplate = builder.dataset.initialTemplateCard || 'toeic-full';
    const isThptEnglishTemplate = () => visualTemplate.startsWith('thpt-english');
    const isThptPresetTemplate = () => visualTemplate === 'thpt' || isThptEnglishTemplate();
    const normalizeTemplateId = id => id === 'thpt-english' ? 'thpt-english-2025' : id;
    let bankPage = 1, bankPageSize = 10, quickFilter = 'all', questionTab = 'question', previewCandidateId = null;
    const currentType = () => Number(examType.value);
    const typeCode = () => catalog.parts.find(part => Number(part.type) === currentType())?.type_code ?? 'custom';
    const isThptEnglishExam = () => typeCode() === 'thpt';
    const partsForType = type => catalog.parts.filter(part => Number(part.type) === Number(type));
    const defaultDifficulty = count => ({ easy: Math.floor(count * .3), medium: count - Math.floor(count * .3) - Math.floor(count * .2), hard: Math.floor(count * .2) });
    const serverTemplate = key => catalog.templates[key] ?? null;
    const defaultCount = part => {
        const thptCount = serverTemplate('thpt')?.subjects?.[part?.code]?.count;
        if (typeCode() === 'thpt' && thptCount) return Number(thptCount);
        const base = serverTemplate(typeCode() === 'ielts' ? (variant.value === 'general' ? 'ielts-general' : 'ielts-academic') : 'toeic');
        const count = base?.sections?.find(rule => Number(rule.exam_part_id) === Number(part?.id))?.count;
        return Number(count || (part?.skill === 'listening' ? 10 : 10));
    };
    const selectedUnits = section => (section?.question_ids ?? []).reduce((sum, id) => sum + (questionById.get(Number(id))?.units ?? 1), 0);
    const partMinutes = part => Number(serverTemplate('thpt')?.subjects?.[part?.code]?.duration_minutes || ({ 'part-1': 6, 'part-2': 10, 'part-3': 20, 'part-4': 15, 'part-5': 12, 'part-6': 8, 'part-7': 29 })[part?.code] || 0);
    const makeRule = (partId, count, minutes = 0) => ({ exam_part_id: Number(partId), difficulty: defaultDifficulty(Number(count || 1)), ...(minutes > 0 ? { duration_minutes: minutes } : {}) });
    let rules = generation?.sections?.map(rule => ({ ...rule, exam_part_id: Number(rule.exam_part_id), difficulty: { ...rule.difficulty } })) ?? sections.map(section => makeRule(section.exam_part_id, Number(section.target_count || selectedUnits(section) || defaultCount(getPart(section))), Number(section.duration_minutes || 0)));
    const setText = (selector, value) => fields(selector).forEach(element => { element.textContent = value; });
    const setValue = (selector, value) => { const element = field(selector); if (element) element.value = value; };
    const readPolicies = () => {
        try { return { ...JSON.parse(policiesInput.value || '{}') }; } catch { return {}; }
    };
    const writePolicies = changes => {
        const next = { ...readPolicies(), ...changes };
        policiesInput.value = JSON.stringify(next);
        if (Object.hasOwn(changes, 'duration_minutes')) duration.value = next.duration_minutes;
        fields('[data-policy-toggle]').forEach(input => { input.checked = Boolean(next[input.dataset.policyToggle]); });
        fields('[data-policy-select]').forEach(select => { select.value = next[select.dataset.policySelect] ?? select.value; });
    };
    const enforceFullSimulationPolicies = () => {
        const required = mode.value === 'full';
        if (required) {
            writePolicies({ pause_allowed: false, auto_submit: true, answer_review: 'after_submit', explanation_review: 'after_submit' });
        }
        fields('[data-policy-toggle="pause_allowed"], [data-policy-select="answer_review"], [data-policy-select="explanation_review"]').forEach(control => { control.disabled = required; });
        const note = field('[data-full-simulation-policy-note]');
        if (note) note.hidden = !required;
    };
    const questionSection = q => sections.find(section => Number(section.exam_part_id) === Number(q.part));
    const allRows = () => sections.flatMap((section, index) => section.question_ids.map((id, order) => ({ question: questionById.get(Number(id)), id: Number(id), index, order }))).filter(row => row.question);
    const allQuestions = () => allRows().map(row => row.question);
    const totalTarget = () => rules.reduce((sum, rule) => sum + Object.values(rule.difficulty ?? {}).reduce((n, count) => n + Number(count || 0), 0), 0);
    const totalSelected = () => allQuestions().reduce((sum, q) => sum + Number(q.units ?? 1), 0);
    const ruleCount = rule => Object.values(rule?.difficulty ?? {}).reduce((sum, count) => sum + Number(count || 0), 0);
    const buildPresetGeneration = () => {
        if (!isThptPresetTemplate()) return null;
        sections.forEach((section, index) => {
            const rule = rules[index], target = ruleCount(rule);
            if (!rule || target < 1 || selectedUnits(section) !== target) return;
            const difficulty = { easy: 0, medium: 0, hard: 0 };
            section.question_ids.forEach(id => {
                const question = questionById.get(Number(id));
                if (question && Object.hasOwn(difficulty, question.difficulty)) difficulty[question.difficulty] += Number(question.units || 1);
            });
            rules[index] = { ...rule, difficulty };
        });
        return { template: normalizeTemplateId(visualTemplate), sections: rules.map(rule => ({ ...rule, difficulty: { ...rule.difficulty } })) };
    };
    const configuredSectionMinutes = () => fields('[data-section-duration]').reduce((sum, input) => sum + Math.max(0, Number(input.value || 0)), 0);
    const validateSectionDurationBudget = () => {
        const inputs = fields('[data-section-duration]');
        const sectionMinutes = configuredSectionMinutes();
        const totalMinutes = Number(duration.value || 0);
        const exceeded = totalMinutes > 0 && sectionMinutes > totalMinutes;
        const summary = field('[data-section-duration-summary]');
        const message = `Tổng thời gian các phần (${sectionMinutes} phút) không được vượt thời gian đề (${totalMinutes} phút).`;

        inputs.forEach(input => input.classList.remove('is-invalid'));
        if (exceeded) [...inputs].reverse().find(input => Number(input.value) > 0)?.classList.add('is-invalid');
        if (summary) {
            summary.classList.toggle('is-error', exceeded);
            summary.textContent = exceeded
                ? message
                : `Thời gian các phần đã cấu hình: ${sectionMinutes} / ${totalMinutes || '—'} phút.`;
        }

        return !exceeded;
    };
    const visualCard = id => field(`[data-template-card="${id}"]`);
    const currentPresetSections = () => sections.map((section, index) => ({ section, rule: rules[index], part: getPart(section) }));
    const flash = message => { feedback.hidden = false; feedback.textContent = message; field('[data-wizard-message]').textContent = message; };
    const errorMessage = data => Object.values(data.errors ?? {}).flat().join('\n') || data.message || 'Không thể hoàn tất thao tác.';
    const findPart = (code, type = currentType()) => partsForType(type).find(part => part.code === code);
    const presetDefinition = id => {
        if (id === 'toeic-full') return { template: 'toeic', typeCode: 'toeic', variant: '', duration: 120, mode: 'full' };
        if (id === 'ielts-academic' || id === 'ielts-general') return { template: id, typeCode: 'ielts', variant: id.slice(6), duration: 164, mode: 'full' };
        if (id.startsWith('thpt-english')) {
            const templateKey = normalizeTemplateId(id);
            const preset = serverTemplate(templateKey);
            if (!preset?.sections?.length || preset.sections.some(section => !section.exam_part_id)) return null;
            return { template: templateKey, typeCode: 'thpt', variant: '', duration: 50, mode: 'full', scoreScaleId: preset.score_scales?.[40] };
        }
        if (id === 'thpt') {
            const part = partById.get(Number(field('[data-thpt-subject]')?.value));
            const subject = part ? serverTemplate('thpt')?.subjects?.[part.code] : null;
            if (!part || !subject) return null;
            return { template: 'thpt', typeCode: 'thpt', variant: '', duration: subject.duration_minutes, mode: 'full', counts: [[part.code, subject.count]], scoreScaleId: serverTemplate('thpt')?.score_scales?.[subject.count] };
        }
        const defaults = {
            'toeic-mini': { counts: [['part-2', 15], ['part-5', 20], ['part-7', 15]], duration: 30 },
            'toeic-part5': { counts: [['part-5', 30]], duration: 20 },
            'toeic-part7': { counts: [['part-7', 54]], duration: 45 },
            'toeic-listening': { counts: [['part-1', 6], ['part-2', 25], ['part-3', 39], ['part-4', 30]], duration: 45 },
            'toeic-reading': { counts: [['part-5', 30], ['part-6', 16], ['part-7', 54]], duration: 75 },
        };
        if (defaults[id]) return { template: 'custom', typeCode: 'toeic', variant: '', mode: 'skill', ...defaults[id] };
        if (id === 'custom') return { template: 'custom', typeCode: typeCode(), variant: '', mode: 'mini', counts: [], duration: 30 };
        return null;
    };
    const applyTemplate = (id, { preserveQuestions = false } = {}) => {
        id = normalizeTemplateId(id);
        const definition = presetDefinition(id);
        if (!definition) return;
        if (!preserveQuestions && allQuestions().length && id !== visualTemplate && !window.confirm('Đổi template sẽ thay cấu trúc hiện tại và bỏ câu hỏi đã chọn. Tiếp tục?')) return;
        visualTemplate = id;
        quickFilter = 'all';
        fields('[data-quick-filter]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.quickFilter === 'all')));
        const type = catalog.parts.find(part => part.type_code === definition.typeCode);
        if (type) examType.value = type.type;
        if (['toeic', 'ielts', 'thpt'].includes(definition.typeCode)) field('[data-bank-exam]').value = definition.typeCode;
        field('[data-bank-part]').value = '';
        field('[data-thpt-subject-field]').hidden = id !== 'thpt';
        if (definition.typeCode === 'thpt') activeThptPartId = Number(field('[data-thpt-subject]').value);
        template.value = definition.template;
        variant.value = definition.variant || '';
        mode.value = definition.mode;
        if (definition.scoreScaleId) field('[data-score-scale]').value = String(definition.scoreScaleId);
        const typeChoice = id === 'custom' ? 'custom' : definition.typeCode;
        fields('[data-type-choice]').forEach(button => button.classList.toggle('is-active', button.dataset.typeChoice === typeChoice));
        duration.value = definition.duration;
        const server = serverTemplate(definition.template);
        let rows = [];
        if (server && definition.template !== 'custom' && definition.template !== 'thpt') {
            rows = server.sections.map(section => ({ part: partById.get(Number(section.exam_part_id)), count: Number(section.count) }));
        } else {
            rows = (definition.counts || []).map(([code, count]) => ({ part: findPart(code, type?.type), count }));
        }
        if (rows.some(row => !row.part)) {
            flash('Danh mục chưa có đầy đủ phần thi của template này.');
            return;
        }
        sections = rows.map(({ part, count }) => ({ exam_part_id: Number(part.id), question_ids: [], target_count: Number(count), ...(partMinutes(part) ? { duration_minutes: partMinutes(part) } : {}) }));
        rules = rows.map(({ part, count }) => makeRule(part.id, count, partMinutes(part)));
        generation = buildPresetGeneration();
        activePart = null; previewIndex = 0;
        writePolicies({ duration_minutes: definition.duration, pause_allowed: false, auto_submit: true, answer_review: 'after_submit', explanation_review: 'after_submit' });
        showTemplateCategory(id.startsWith('ielts') ? 'ielts' : id === 'custom' ? 'custom' : id.startsWith('thpt') ? 'thpt' : 'toeic');
        render();
    };
    const showTemplateCategory = category => {
        fields('[data-template-category]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.templateCategory === category)));
        fields('[data-template-card]').forEach(card => { card.hidden = card.dataset.category !== category; });
    };
    const maybeInitialTemplate = () => {
        if (generation?.template === 'toeic') visualTemplate = 'toeic-full';
        else if (generation?.template === 'thpt') visualTemplate = 'thpt';
        else if (generation?.template?.startsWith('thpt-english')) visualTemplate = normalizeTemplateId(generation.template);
        else if (generation?.template?.startsWith('ielts-')) visualTemplate = generation.template;
        else if (sections.length) visualTemplate = presetDefinition(visualTemplate) ? visualTemplate : 'custom';
        else if (builder.dataset.initialStep === '0') {
            const toeic = catalog.parts.find(part => part.type_code === 'toeic');
            if (toeic) applyTemplate(presetDefinition(visualTemplate) ? visualTemplate : 'toeic-full', { preserveQuestions: true });
            else visualTemplate = 'custom';
        }
    };

    const previewBarsHtml = () => sections.map((section, index) => {
        const count = section.question_ids.reduce((sum, id) => sum + (questionById.get(Number(id))?.units ?? 1), 0);
        const target = ruleCount(rules[index]);
        const percent = totalTarget() ? Math.round(target / totalTarget() * 100) : 0;
        const part = getPart(section);
        return `<div class="ex-part-bar" style="--part-color:${colors[index % colors.length]}"><span class="ex-part-number">${index + 1}</span><b title="${encode(part?.name)}">${encode(partName(part))}</b><span class="ex-bar-track"><i style="width:${Math.min(100, percent)}%"></i></span><span>${count || target} câu</span><small>${percent}%</small></div>`;
    }).join('') || '<p class="ex-muted">Chưa có phần thi.</p>';
    const difficultyCounts = () => {
        const totals = { easy: 0, medium: 0, hard: 0 };
        allQuestions().forEach(question => { totals[question.difficulty] = (totals[question.difficulty] || 0) + (question.units || 1); });
        return totals;
    };
    const renderQuestionPreview = () => {
        const rows = allRows();
        if (previewCandidateId && !rows.some(row => row.id === Number(previewCandidateId))) {
            const question = questionById.get(Number(previewCandidateId));
            if (question) rows.splice(Math.min(previewIndex, rows.length), 0, { question, id: Number(question.id), index: sections.findIndex(section => Number(section.exam_part_id) === Number(question.part)), order: -1 });
        } else if (previewCandidateId) {
            previewIndex = rows.findIndex(row => row.id === Number(previewCandidateId));
            previewCandidateId = null;
        }
        previewIndex = rows.length ? Math.max(0, Math.min(previewIndex, rows.length - 1)) : 0;
        const row = rows[previewIndex], q = row?.question;
        fields('[data-question-prev]').forEach(button => { button.disabled = !q || previewIndex === 0; });
        fields('[data-question-next]').forEach(button => { button.disabled = !q || previewIndex >= rows.length - 1; });
        setText('[data-question-position]', q ? `${previewIndex + 1} / ${rows.length}` : '0 / 0');
        const body = !q ? '<div class="ex-empty"><strong>Chưa chọn câu hỏi</strong><span>Chọn câu hỏi từ ngân hàng hoặc dùng tính năng tự động bốc.</span></div>' : (() => {
            const part = partById.get(Number(q.part));
            if (questionTab === 'passage') return q.passage ? `<article class="ex-passage"><strong>${encode(q.passage.title)}</strong><p>${encode(q.passage.body)}</p></article>` : '<p class="ex-muted">Câu hỏi này không có passage đính kèm.</p>';
            if (questionTab === 'audio') return q.audio ? `<div class="ex-audio-preview">${icon('volume-2')}<div><b>${encode(q.audio_title || 'Audio liên kết')}</b><small>Audio được đính kèm với câu hỏi này.</small></div></div>${q.audio_url ? `<audio class="ex-audio-player" controls preload="none" src="${encode(q.audio_url)}">Trình duyệt không hỗ trợ phát audio.</audio>` : '<p class="ex-muted">Không có liên kết phát trực tiếp cho audio này.</p>'}` : '<p class="ex-muted">Câu hỏi này không có audio đính kèm.</p>';
            if (questionTab === 'stats') return `<dl class="ex-question-stats"><dt>ID câu hỏi</dt><dd>Q${q.id}</dd><dt>Phần thi</dt><dd>${encode(partName(part))}</dd><dt>Dạng câu</dt><dd>${encode(questionTypeNames[q.type] || q.type || 'Câu hỏi')}</dd><dt>Độ khó</dt><dd>${encode(difficultyNames[q.difficulty] || 'Trung bình')}</dd><dt>Nhóm câu</dt><dd>${q.group ? `Nhóm ${q.group}` : 'Câu độc lập'}</dd><dt>Đơn vị trả lời</dt><dd>${q.units || 1}</dd></dl>`;
            return `<div class="ex-preview-question-meta"><span class="ex-tag is-primary">Q${q.id}</span><span>${encode(partName(part))} · ${encode(difficultyNames[q.difficulty] || 'Trung bình')}</span><span class="ex-tag">${encode(questionTypeNames[q.type] || q.type || 'Câu hỏi')}</span>${q.audio ? icon('volume-2') : ''}</div><div class="ex-preview-prompt">${encode(q.prompt)}</div>${q.options?.length ? `<ol class="ex-question-options">${q.options.map(option => `<li><b>${encode(option.key)}.</b> ${encode(option.text)}</li>`).join('')}</ol>` : ''}<a class="ex-text-link" href="${encode(q.edit_url)}" target="_blank" rel="noopener">Mở câu hỏi trong ngân hàng ${icon('external-link')}</a>`;
        })();
        field('[data-question-preview]').innerHTML = body;
        field('[data-final-question-preview]').innerHTML = q ? `<div class="ex-preview-question-meta"><span class="ex-tag is-primary">Q${q.id}</span><span>${encode(partName(partById.get(Number(q.part))))}</span></div><div class="ex-preview-prompt">${encode(q.prompt)}</div>${q.options?.length ? `<ol class="ex-question-options">${q.options.slice(0, 4).map(option => `<li>${encode(option.key)}. ${encode(option.text)}</li>`).join('')}</ol>` : ''}` : '<p class="ex-muted">Chưa có câu hỏi để xem trước.</p>';
        refreshIcons(builder);
    };
    const syncPreview = () => {
        const questions = allQuestions(), total = totalSelected(), target = totalTarget();
        const typeName = field('[data-exam-type]').selectedOptions?.[0]?.textContent || catalog.parts.find(part => Number(part.type) === currentType())?.type_code?.toUpperCase() || 'Đề thi';
        const passages = new Set(questions.map(q => q.passage?.id).filter(Boolean)).size;
        const audio = questions.filter(q => q.audio).length;
        const title = field('[name="title"]')?.value || 'Đề thi mới';
        setText('[data-preview-title]', title);
        setText('[data-final-title]', title);
        setText('[data-preview-type]', typeName);
        setText('[data-final-type]', typeName);
        setText('[data-preview-mode]', mode.selectedOptions?.[0]?.textContent || (mode.value === 'full' ? 'Mock Test' : mode.value === 'skill' ? 'Luyện kỹ năng' : 'Đề ngắn'));
        setText('[data-preview-count]', total);
        setText('[data-preview-duration]', duration.value || 0);
        setText('[data-preview-parts]', sections.length);
        const scoreScale = field('[data-score-scale]');
        setText('[data-preview-score]', typeCode() === 'thpt' ? '10' : scoreScale?.selectedOptions?.[0]?.textContent?.split('·')[0]?.trim() || (/TOEIC/i.test(typeName) ? '990' : /IELTS/i.test(typeName) ? '9.0' : '—'));
        setValue('[data-exam-max-score]', typeCode() === 'thpt' ? '10' : /TOEIC/i.test(typeName) ? '990' : 'Theo thang điểm');
        setText('[data-preview-source]', `Ngân hàng câu hỏi${passages ? ` · ${passages} passage` : ''}${audio ? ` · ${audio} audio` : ''}`);
        setValue('[data-exam-target]', target);
        setText('[data-selected-total]', `${total} câu`);
        setText('[data-selected-passages]', passages);
        setText('[data-selected-audio]', audio);
        setText('[data-bank-selected-count]', total);
        setText('[data-bank-target-note]', `/ mục tiêu ${target} câu`);
        fields('[data-preview-bars]').forEach(element => { element.innerHTML = previewBarsHtml(); });
        const diff = difficultyCounts(), diffTotal = Math.max(1, Object.values(diff).reduce((sum, value) => sum + value, 0));
        fields('[data-difficulty-distribution]').forEach(element => {
            element.innerHTML = Object.entries(difficultyNames).map(([key, name]) => `<span class="ex-status is-${key}">${name} ${Math.round(diff[key] / diffTotal * 100)}% · ${diff[key]}</span>`).join('');
        });
        const percentages = ['easy', 'medium', 'hard'].map(key => Math.round(diff[key] / diffTotal * 100));
        fields('[data-difficulty-donut]').forEach(element => { element.style.setProperty('--easy', `${percentages[0]}%`); element.style.setProperty('--medium', `${percentages[0] + percentages[1]}%`); });
        setText('[data-final-code]', builder.dataset.examId ? `Mã đề ${builder.dataset.examId}` : 'Mã đề sẽ tạo sau khi lưu');
        renderQuestionPreview();
        renderFinalOverview();
    };
    const sync = (dirty = true) => {
        sections.forEach((section, index) => { section.target_count = ruleCount(rules[index]); });
        serialized.value = JSON.stringify(sections);
        generationInput.value = generation ? JSON.stringify(generation) : '';
        field('[data-visual-template]').value = visualTemplate;
        fields('[data-template-card]').forEach(card => card.setAttribute('aria-pressed', String(card.dataset.templateCard === visualTemplate)));
        syncPreview();
        if (dirty) builder.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const changeToCustomIfNeeded = () => {
        generation = null;
        template.value = 'custom';
        const wasThptTemplate = visualTemplate === 'thpt' || isThptEnglishTemplate();
        visualTemplate = 'custom';
        field('[data-thpt-subject-field]').hidden = true;
        showTemplateCategory('custom');
        if (wasThptTemplate) {
            field('[data-score-scale]').value = '';
        }
        if (mode.value === 'full') mode.value = 'mini';
    };
    const renderAddSectionControl = () => {
        const select = field('[data-add-section-part]');
        const candidates = partsForType(currentType()).filter(part => (!isThptEnglishExam() || part.code === 'tieng-anh' || part.code.startsWith('tieng-anh-part-')) && !sections.some(section => Number(section.exam_part_id) === Number(part.id)));
        const previous = Number(select.value);
        select.innerHTML = candidates.map(part => `<option value="${part.id}">${encode(partLabel(part))}</option>`).join('');
        if (candidates.some(part => Number(part.id) === previous)) select.value = String(previous);
        const selectedPart = candidates.find(part => Number(part.id) === Number(select.value)) || candidates[0];
        const enabled = (template.value === 'custom' || visualTemplate === 'custom') && Boolean(selectedPart);
        field('[data-add-section-controls]').hidden = !enabled;
        field('[data-add-section]').disabled = !enabled;
        field('[data-add-section-label]').textContent = selectedPart ? `Thêm ${partLabel(selectedPart)}` : 'Thêm part';
    };
    const renderSectionCards = () => {
        renderAddSectionControl();
        field('[data-template-note]').textContent = isThptEnglishTemplate() ? `Blueprint ${visualTemplate.endsWith('2026') ? '2026 · mã 1101' : 'tham khảo 2025'}: 40 câu, 50 phút, 10 điểm. Chọn nguyên nhóm văn bản khi tạo đề.` : visualTemplate === 'thpt' ? 'Bản luyện tập dùng câu hỏi trắc nghiệm 4 lựa chọn; chọn một môn và bốc đủ số câu theo cấu trúc.' : generation ? 'Template chỉ dùng câu hỏi đã được duyệt; câu nháp cần duyệt trước khi được chọn.' : template.value === 'custom' ? 'Có thể chỉnh số câu và bốc tự động từ ngân hàng theo phân bố độ khó.' : 'Cấu trúc chuẩn theo template. Bạn vẫn có thể chỉnh cơ cấu khi tạo đề thủ công.';
        field('[data-builder-sections]').innerHTML = sections.length ? sections.map((section, index) => {
            const part = getPart(section), rule = rules[index], target = ruleCount(rule), selected = selectedUnits(section);
            const colorClass = part?.skill === 'reading' ? 'is-reading' : part?.skill === 'listening' ? 'is-listening' : 'is-other';
            return `<article class="ex-part-config-card ${colorClass}" data-section-index="${index}"><header><span class="ex-part-label">${encode(partName(part))}</span><span class="ex-part-card-actions"><button type="button" class="ex-icon-button" data-move-section="-1" aria-label="Đưa ${encode(partName(part))} lên" ${index === 0 ? 'disabled' : ''}>${icon('arrow-up')}</button><button type="button" class="ex-icon-button" data-move-section="1" aria-label="Đưa ${encode(partName(part))} xuống" ${index === sections.length - 1 ? 'disabled' : ''}>${icon('arrow-down')}</button><button type="button" class="ex-icon-button" data-remove-section aria-label="Bỏ ${encode(partName(part))}">${icon('x')}</button></span></header><small>${encode(part?.type_code === 'thpt' ? 'Môn thi' : part?.skill || 'Kỹ năng')}</small><label>Số câu<input type="number" min="1" max="200" data-section-count value="${target}"></label><label>Thời gian (phút)<input type="number" min="1" max="240" data-section-duration value="${section.duration_minutes ?? rule.duration_minutes ?? ''}" placeholder="Theo đề"></label><div class="ex-part-card-meta"><span>${selected} câu đã chọn</span><button type="button" class="ex-text-button" data-toggle-quota aria-expanded="false">Độ khó</button></div><div class="ex-quota-detail" hidden>${Object.entries(difficultyNames).map(([key, name]) => `<label>${name}<input type="number" min="0" max="200" data-difficulty="${key}" value="${Number(rule.difficulty[key] || 0)}"></label>`).join('')}</div></article>`;
        }).join('') : `<div class="ex-empty"><strong>Chưa có phần thi</strong><span>Chọn template hoặc thêm một phần để cấu hình số câu.</span></div>`;
        if (isThptEnglishTemplate()) {
            fields('[data-builder-sections] [data-section-count]').forEach(input => { input.readOnly = true; });
            fields('[data-builder-sections] [data-section-duration]').forEach(input => input.closest('label')?.remove());
            fields('[data-builder-sections] [data-move-section], [data-builder-sections] [data-remove-section]').forEach(button => button.hidden = true);
        }
        refreshIcons(field('[data-builder-sections]'));
        validateSectionDurationBudget();
    };
    const renderTemplateDetails = () => {
        const definition = presetDefinition(visualTemplate) || presetDefinition('custom');
        const server = serverTemplate(definition.template);
        let rows = server && definition.template !== 'custom' && definition.template !== 'thpt' ? server.sections.map(section => ({ part: partById.get(Number(section.exam_part_id)), count: section.count })) : (definition.counts || []).map(([code, count]) => ({ part: findPart(code, catalog.parts.find(part => part.type_code === definition.typeCode)?.type), count }));
        if (!rows.length && visualTemplate === 'custom') rows = sections.map((section, index) => ({ part: getPart(section), count: ruleCount(rules[index]) }));
        field('[data-template-structure]').innerHTML = rows.map((row, index) => `<tr><td><span class="ex-part-number" style="--part-color:${colors[index % colors.length]}">${encode(partName(row.part))}</span></td><td>${encode(isThptEnglishTemplate() ? (row.part?.code?.includes('part-3') ? 'Arrangement' : row.part?.code?.includes('part-4') ? 'Extended completion' : row.part?.code?.includes('part-5') || row.part?.code?.includes('part-6') ? 'Reading' : 'Short cloze') : row.part?.type_code === 'thpt' ? 'Môn thi' : row.part?.skill || '—')}</td><td>${row.count}</td><td>${isThptEnglishTemplate() ? '—' : `${serverTemplate('thpt')?.subjects?.[row.part?.code]?.duration_minutes ?? ({ 'part-1': 6, 'part-2': 10, 'part-3': 15, 'part-4': 15, 'part-5': 12, 'part-6': 8, 'part-7': 29 })[row.part?.code] ?? 10} phút`}</td><td>${encode(isThptEnglishTemplate() && ['tieng-anh-part-1', 'tieng-anh-part-2', 'tieng-anh-part-4', 'tieng-anh-part-5', 'tieng-anh-part-6'].includes(row.part?.code) ? 'Một văn bản dùng chung cho nhóm câu' : row.part?.name || 'Tùy chỉnh')}</td></tr>`).join('') || '<tr><td colspan="5">Chọn một template để xem cấu trúc.</td></tr>';
        setText('[data-template-count]', rows.reduce((sum, row) => sum + Number(row.count || 0), 0) + ' câu');
        setText('[data-template-duration]', `${visualTemplate === 'custom' ? Number(duration.value || definition.duration || 30) : definition.duration || 30} phút`);
        setText('[data-template-type]', definition.typeCode?.toUpperCase() || 'Custom');
        setText('[data-template-description]', visualTemplate === 'toeic-full' ? 'Cấu trúc TOEIC Listening & Reading chuẩn ETS.' : visualTemplate.startsWith('ielts') ? 'Đề IELTS mô phỏng đầy đủ bốn kỹ năng.' : isThptEnglishTemplate() ? `${visualTemplate.endsWith('2026') ? 'Mẫu B theo đề 2026 mã 1101' : 'Mẫu A tham khảo 2025'}; 40 câu A–D, 50 phút, thang 10.` : visualTemplate === 'thpt' ? 'Một môn theo số câu và thời gian THPT; mọi câu đều có 4 lựa chọn, quy đổi điểm tuyến tính trên thang 10.' : 'Phần thi và số câu có thể chỉnh sửa theo mục tiêu.');
    };
    const selectedIds = () => new Set(sections.flatMap(section => section.question_ids).map(Number));
    const sectionIndexForQuestion = q => sections.findIndex(section => Number(section.exam_part_id) === Number(q.part));
    const groupQuestions = q => {
        if (isThptEnglishExam() && q.passage?.id) {
            return catalog.questions.filter(item => Number(item.part) === Number(q.part) && Number(item.passage?.id) === Number(q.passage.id));
        }
        return q.group ? catalog.questions.filter(item => Number(item.group) === Number(q.group) && Number(item.part) === Number(q.part)) : [q];
    };
    const toggleQuestion = (q, checked) => {
        const questionPart = partById.get(Number(q.part));
        if (checked && q.status === 'archived') {
            flash('Câu hỏi đã lưu trữ không thể thêm vào đề.');
            renderBank();
            return;
        }
        if (checked && Number(questionPart?.type) !== currentType()) {
            flash('Câu hỏi thuộc kỳ thi khác. Hãy đổi loại đề trước khi thêm.');
            renderBank();
            return;
        }
        if (checked && questionPart?.type_code === 'thpt' && (q.type !== 'multiple_choice' || q.options?.length !== 4)) {
            flash('Đề THPT chỉ nhận câu trắc nghiệm có đúng 4 lựa chọn.');
            renderBank();
            return;
        }
        const group = groupQuestions(q);
        if (checked && generation && group.some(item => !item.has_approved_content)) {
            flash('Template chỉ nhận câu hỏi đã được duyệt. Hãy duyệt câu hỏi trước khi chọn.');
            renderBank();
            return;
        }
        const thptTextParts = ['tieng-anh-part-1', 'tieng-anh-part-2', 'tieng-anh-part-4', 'tieng-anh-part-5', 'tieng-anh-part-6'];
        if (checked && isThptEnglishExam() && thptTextParts.includes(questionPart?.code)) {
            const sourceKey = item => item.passage?.id ? `passage:${item.passage.id}` : null;
            const nextKey = sourceKey(q);
            if (!nextKey) {
                flash('Liên kết đủ câu trong nhóm với cùng văn bản trước khi thêm vào đề THPT.');
                renderBank();
                return;
            }
            const section = sections.find(item => Number(item.exam_part_id) === Number(q.part));
            const selectedKeys = new Set((section?.question_ids ?? []).map(id => questionById.get(Number(id))).filter(Boolean).map(sourceKey).filter(Boolean));
            if ([...selectedKeys].some(key => key !== nextKey)) {
                flash('Mỗi nhóm điền hoặc đọc hiểu chỉ dùng một văn bản chung. Bỏ nhóm hiện tại trước khi chọn văn bản khác.');
                renderBank();
                return;
            }
        }
        if (checked) {
            const missingPart = group.find(item => sectionIndexForQuestion(item) < 0);
            if (missingPart) {
                flash(`Phần ${partName(partById.get(Number(missingPart.part)))} chưa có trong đề. Hãy thêm phần thi trước khi chọn câu hỏi.`);
                renderBank();
                return;
            }
            group.forEach(item => {
                const index = sectionIndexForQuestion(item);
                if (index >= 0 && !sections[index].question_ids.includes(Number(item.id))) sections[index].question_ids.push(Number(item.id));
            });
        } else {
            const remove = new Set(group.map(item => Number(item.id)));
            sections.forEach(section => { section.question_ids = section.question_ids.filter(id => !remove.has(Number(id))); });
        }
        generation = buildPresetGeneration();
        previewIndex = 0;
        sync();
        renderBank();
        renderSectionCards();
    };
    const bankFilterState = () => ({
        query: field('[data-bank-search]').value.trim().toLocaleLowerCase(),
        exam: field('[data-bank-exam]').value,
        part: field('[data-bank-part]').value,
        type: field('[data-bank-type]').value,
        difficulty: field('[data-bank-difficulty]').value,
        status: field('[data-bank-status]').value,
        tag: field('[data-bank-tag]').value,
    });
    const thptFamilyForPart = code => {
        if (['tieng-anh-part-1', 'tieng-anh-part-2'].includes(code)) return 'short_cloze';
        if (code === 'tieng-anh-part-3') return 'arrangement';
        if (code === 'tieng-anh-part-4') return 'extended_completion';
        if (['tieng-anh-part-5', 'tieng-anh-part-6'].includes(code)) return 'reading';
        return '';
    };
    const questionsForSelectedExam = () => catalog.questions.filter(question => {
        const part = partById.get(Number(question.part));
        if (field('[data-bank-exam]').value && part?.type_code !== field('[data-bank-exam]').value) return false;
        if (part?.type_code === 'thpt' && (question.type !== 'multiple_choice' || question.options?.length !== 4 || (part.code !== 'tieng-anh' && !part.code.startsWith('tieng-anh-part-')))) return false;
        if (visualTemplate === 'thpt' && sections[0] && Number(question.part) !== Number(sections[0].exam_part_id)) return false;
        if (isThptEnglishTemplate() && !serverTemplate(visualTemplate)?.sections?.some(section => Number(section.exam_part_id) === Number(question.part))) return false;
        return true;
    });
    const filterQuestions = () => {
        const filters = bankFilterState();
        return questionsForSelectedExam().filter(q => {
            const part = partById.get(Number(q.part));
            if (filters.exam && part?.type_code !== filters.exam) return false;
            if (filters.part && Number(q.part) !== Number(filters.part)) return false;
            if (filters.type && q.type !== filters.type) return false;
            if (filters.difficulty && q.difficulty !== filters.difficulty) return false;
            if (filters.status && q.status !== filters.status) return false;
            if (filters.tag && !(q.tags || []).includes(filters.tag)) return false;
            if (quickFilter === 'multiple_choice' && q.type !== 'multiple_choice') return false;
            if (isThptEnglishExam() && ['short_cloze', 'arrangement', 'extended_completion', 'reading'].includes(quickFilter) && thptFamilyForPart(part?.code) !== quickFilter) return false;
            if (!isThptEnglishExam() && quickFilter === 'listening' && part?.skill !== 'listening') return false;
            if (!isThptEnglishExam() && quickFilter === 'reading' && part?.skill !== 'reading') return false;
            if (quickFilter === 'audio' && !q.audio) return false;
            if (quickFilter === 'passage' && !q.passage) return false;
            if (filters.query && !`${q.id} ${q.prompt} ${(q.tags || []).join(' ')} ${partName(part)} ${q.type || ''}`.toLocaleLowerCase().includes(filters.query)) return false;
            return true;
        });
    };
    const renderBankFilterOptions = () => {
        const select = field('[data-bank-part]'), current = select.value;
        const englishPartIds = new Set((serverTemplate(visualTemplate)?.sections ?? []).map(section => Number(section.exam_part_id)));
        const parts = catalog.parts.filter(part => (!field('[data-bank-exam]').value || part.type_code === field('[data-bank-exam]').value) && (!isThptEnglishTemplate() || englishPartIds.has(Number(part.id))) && (!isThptEnglishExam() || part.type_code !== 'thpt' || part.code === 'tieng-anh' || part.code.startsWith('tieng-anh-part-')));
        select.innerHTML = '<option value="">Tất cả</option>' + parts.map(part => `<option value="${part.id}">${encode(partName(part))} · ${encode(part.skill || '')}</option>`).join('');
        if (parts.some(part => String(part.id) === current)) select.value = current;
        const tagSelect = field('[data-bank-tag]'), selectedTag = tagSelect.value;
        const tags = [...new Set(catalog.questions.flatMap(question => question.tags || []))].sort((a, b) => a.localeCompare(b, 'vi'));
        tagSelect.innerHTML = '<option value="">Tất cả</option>' + tags.map(tag => `<option value="${encode(tag)}">${encode(tag)}</option>`).join('');
        if (tags.includes(selectedTag)) tagSelect.value = selectedTag;
        const typeSelect = field('[data-bank-type]'), selectedType = typeSelect.value;
        const types = [...new Set(questionsForSelectedExam().map(question => question.type).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        typeSelect.innerHTML = '<option value="">Tất cả dạng</option>' + types.map(type => `<option value="${encode(type)}">${encode(questionTypeNames[type] || type.replaceAll('_', ' '))}</option>`).join('');
        if (types.includes(selectedType)) typeSelect.value = selectedType;
    };
    const renderBankQuickCounts = () => {
        const all = questionsForSelectedExam();
        const counts = {
            all: all.length,
            thpt_all: all.length,
            multiple_choice: all.filter(q => q.type === 'multiple_choice').length,
            listening: all.filter(q => partById.get(Number(q.part))?.skill === 'listening').length,
            reading: all.filter(q => partById.get(Number(q.part))?.skill === 'reading').length,
            short_cloze: all.filter(q => thptFamilyForPart(partById.get(Number(q.part))?.code) === 'short_cloze').length,
            arrangement: all.filter(q => thptFamilyForPart(partById.get(Number(q.part))?.code) === 'arrangement').length,
            extended_completion: all.filter(q => thptFamilyForPart(partById.get(Number(q.part))?.code) === 'extended_completion').length,
            thpt_reading: all.filter(q => thptFamilyForPart(partById.get(Number(q.part))?.code) === 'reading').length,
            audio: all.filter(q => q.audio).length,
            passage: all.filter(q => q.passage).length,
        };
        Object.entries(counts).forEach(([key, count]) => setText(`[data-bank-count="${key}"]`, `${count.toLocaleString('vi-VN')} câu`));
    };
    const renderBank = () => {
        const results = filterQuestions();
        const pages = Math.max(1, Math.ceil(results.length / bankPageSize));
        bankPage = Math.max(1, Math.min(bankPage, pages));
        const visible = results.slice((bankPage - 1) * bankPageSize, bankPage * bankPageSize);
        const selected = selectedIds();
        field('[data-bank-rows]').innerHTML = visible.map(q => {
            const part = partById.get(Number(q.part));
            const isSelected = selected.has(Number(q.id));
            const status = q.status === 'published' || q.status === 'approved' ? 'Đã duyệt' : q.status === 'review' ? 'Chờ duyệt' : q.status === 'archived' ? 'Đã lưu trữ' : 'Bản nháp';
            const requiresApproval = Boolean(generation) && !q.has_approved_content;
            const unavailable = q.status === 'archived' || Number(part?.type) !== currentType() || (requiresApproval && !isSelected);
            return `<tr data-bank-row="${q.id}" class="${isSelected ? 'is-selected' : ''}"><td><input type="checkbox" data-bank-pick="${q.id}" ${isSelected ? 'checked' : ''} ${unavailable ? 'disabled' : ''} aria-label="${isSelected ? 'Bỏ câu' : 'Chọn câu'} ${q.id}"></td><td><button type="button" class="ex-text-button" data-bank-preview="${q.id}">Q${q.id}</button></td><td>${encode(part?.type_code?.toUpperCase() || '—')}</td><td>${encode(partName(part))}</td><td><span class="ex-tag">${encode(questionTypeNames[q.type] || q.type || 'Câu hỏi')}</span></td><td class="ex-bank-question-cell"><button type="button" class="ex-text-button" data-bank-preview="${q.id}">${encode(q.prompt)}</button>${q.group ? '<small>Câu thuộc nhóm, sẽ chọn cùng nhóm</small>' : ''}</td><td>${q.passage ? `${icon('files')} Passage` : q.audio ? `${icon('volume-2')} Audio` : 'Question Bank'}</td><td><span class="ex-status is-${q.difficulty}">${encode(difficultyNames[q.difficulty] || 'Trung bình')}</span></td><td><span class="ex-status is-${q.status}">${status}</span></td><td><button type="button" class="ex-icon-button" data-bank-preview="${q.id}" aria-label="Xem câu ${q.id}">${icon('eye')}</button></td></tr>`;
        }).join('') || '<tr><td colspan="10"><div class="ex-empty"><strong>Không tìm thấy câu hỏi</strong><span>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.</span></div></td></tr>';
        visible.filter(q => generation && !q.has_approved_content).forEach(q => {
            const row = field('[data-bank-rows]').querySelector(`[data-bank-row="${q.id}"]`);
            const cell = row?.querySelector('.ex-bank-question-cell');
            if (cell) {
                const note = document.createElement('small');
                note.textContent = 'Template cần câu hỏi đã được duyệt';
                cell.append(note);
            }
        });
        const selectableVisible = visible.filter(q => !generation || q.has_approved_content);
        field('[data-bank-select-visible]').checked = selectableVisible.length > 0 && selectableVisible.every(q => selected.has(Number(q.id)));
        field('[data-bank-select-visible]').disabled = selectableVisible.length === 0;
        setText('[data-bank-result-count]', `${results.length.toLocaleString('vi-VN')} câu hỏi · Hiển thị ${visible.length ? (bankPage - 1) * bankPageSize + 1 : 0}–${Math.min(bankPage * bankPageSize, results.length)}`);
        field('[data-bank-pages]').innerHTML = Array.from({ length: Math.min(pages, 7) }, (_, i) => {
            const page = pages <= 7 ? i + 1 : Math.min(pages, Math.max(1, bankPage - 3) + i);
            return `<button type="button" class="${bankPage === page ? 'is-active' : ''}" data-bank-page="${page}">${page}</button>`;
        }).join('') + (pages > 7 ? `<span>… ${pages}</span>` : '');
        refreshIcons(field('[data-bank-rows]'));
    };
    const renderFinalOverview = () => {
        const total = totalSelected(), target = totalTarget(), questions = allQuestions();
        const parts = currentPresetSections();
        field('[data-final-structure]').innerHTML = parts.map(({ section, rule, part }, index) => `<div class="ex-final-part"><span class="ex-part-number" style="--part-color:${colors[index % colors.length]}">${encode(partName(part))}</span><span>${encode(part?.name || '')}</span><b>${selectedUnits(section)} / ${ruleCount(rule)} câu</b>${isThptEnglishTemplate() ? '' : `<small>~ ${section.duration_minutes || '—'} phút</small>`}</div>`).join('') || '<p class="ex-muted">Chưa cấu hình phần thi.</p>';
        const policies = readPolicies();
        const applied = [policies.lock_previous && 'Khóa câu trước', policies.review_later && 'Được đánh dấu xem lại', policies.randomize_options && 'Xáo trộn đáp án', policies.strict_audio && 'Audio phát một lần', policies.track_focus && 'Theo dõi rời màn hình'].filter(Boolean);
        field('[data-final-policies]').innerHTML = applied.map(text => `<span>${icon('circle-check')}${encode(text)}</span>`).join('') || '<span>Chưa bật quy tắc bổ sung</span>';
        const audio = questions.filter(q => q.audio).length, passages = new Set(questions.map(q => q.passage?.id).filter(Boolean)).size;
        field('[data-final-resources]').innerHTML = `<div><b>${audio}</b><span>câu có audio</span></div><div><b>${passages}</b><span>passage liên kết</span></div><div><b>${questions.filter(q => q.group).length}</b><span>câu thuộc nhóm</span></div>`;
        const thptPassagesPreserved = !isThptEnglishTemplate() || sections.every(section => {
            const part = getPart(section);
            if (!['tieng-anh-part-1', 'tieng-anh-part-2', 'tieng-anh-part-4', 'tieng-anh-part-5', 'tieng-anh-part-6'].includes(part?.code)) return true;
            const keys = section.question_ids.map(id => questionById.get(Number(id))).filter(Boolean).map(question => question.passage?.id ? `passage:${question.passage.id}` : null);
            return keys.every(Boolean) && new Set(keys).size <= 1;
        });
        const checks = [
            [Boolean(field('[name="title"]').value.trim()), 'Đã nhập tên đề thi'],
            [sections.length > 0, 'Đã cấu hình phần thi'],
            [target > 0 && total === target, `Đủ số câu theo mục tiêu (${total}/${target})`],
            [questions.length > 0 && questions.every(q => q.has_approved_content), 'Câu hỏi đã được duyệt'],
            [!isThptEnglishTemplate() || questions.every(q => q.type === 'multiple_choice' && q.options?.length === 4), 'Mỗi câu có đúng bốn lựa chọn A–D'],
            [thptPassagesPreserved, 'Mỗi nhóm điền / đọc hiểu giữ nguyên một văn bản'],
            [questions.every(q => !q.group || groupQuestions(q).every(item => selectedIds().has(Number(item.id)))), 'Đã chọn đủ câu trong nhóm'],
            [isThptEnglishTemplate() || questions.every(q => !(partById.get(Number(q.part))?.skill === 'listening') || q.audio), 'Câu Listening có audio liên kết'],
        ];
        field('[data-final-checks]').innerHTML = checks.map(([ok, message]) => `<li class="${ok ? 'is-ok' : 'is-pending'}">${icon(ok ? 'circle-check' : 'circle-alert')}<span>${encode(message)}</span></li>`).join('');
        setText('[data-final-title]', field('[name="title"]').value || 'Đề thi mới');
        refreshIcons(field('[data-final-policies]'));
        refreshIcons(field('[data-final-resources]'));
        refreshIcons(field('[data-final-checks]'));
    };
    const render = (dirty = true) => {
        enforceFullSimulationPolicies();
        renderSectionCards();
        renderTemplateDetails();
        field('[data-thpt-guidance]').hidden = !isThptEnglishTemplate();
        duration.readOnly = isThptEnglishTemplate();
        if (isThptEnglishTemplate() && Number(duration.value) !== 50) writePolicies({ duration_minutes: 50 });
        fields('[data-quick-group]').forEach(button => { button.hidden = isThptEnglishExam() ? button.dataset.quickGroup !== 'thpt' : button.dataset.quickGroup === 'thpt'; });
        renderBankFilterOptions();
        if (isThptEnglishTemplate() && !field('[data-bank-part]').value) field('[data-bank-part]').value = String(sections[0]?.exam_part_id ?? '');
        renderBankQuickCounts();
        renderBank();
        sync(dirty);
    };
    const showStep = step => {
        activeStep = Math.max(0, Math.min(3, Number(step)));
        fields('[data-wizard-panel]').forEach(panel => { panel.hidden = Number(panel.dataset.wizardPanel) !== activeStep; });
        fields('[data-step-link]').forEach(button => {
            const index = Number(button.dataset.stepLink);
            button.classList.toggle('is-complete', index < activeStep);
            button.classList.toggle('is-current', index === activeStep);
            if (index === activeStep) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current');
        });
        field('[data-step-back]').hidden = activeStep === 0;
        field('[data-step-next]').hidden = activeStep === 3;
        const finishButton = field('[data-finish-exam]');
        if (finishButton) finishButton.hidden = activeStep !== 3;
        field('[data-side-title]').textContent = activeStep === 2 ? 'Xem trước câu hỏi' : activeStep === 3 ? 'Xem trước đề thi' : activeStep === 1 ? 'Xem trước cấu hình' : 'Xem trước template';
        fields('[data-preview-stage]').forEach(stage => { stage.hidden = stage.dataset.previewStage !== (activeStep === 2 ? 'question' : activeStep === 3 ? 'final' : 'template'); });
        field('[data-side-templates]').hidden = activeStep !== 0;
        field('.ex-preview-bottom').hidden = activeStep === 0;
        if (activeStep === 3) renderFinalOverview();
        refreshIcons(builder);
        if (matchMedia('(max-width: 900px)').matches) builder.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
    };
    const validateBeforeContinue = () => {
        if (activeStep === 0) {
            if (!presetDefinition(visualTemplate)) applyTemplate('toeic-full', { preserveQuestions: true });
            return true;
        }
        if (activeStep === 1) {
            const title = field('[name="title"]');
            if (!title.value.trim()) { flash('Vui lòng nhập tên đề thi trước khi tiếp tục.'); title.focus(); return false; }
            const length = Number(duration.value);
            if (!length || length < 1 || length > 480) { flash('Thời gian làm bài cần từ 1 đến 480 phút.'); duration.focus(); return false; }
            if (!sections.length) { flash('Hãy thêm ít nhất một phần thi để tiếp tục.'); return false; }
            if (!validateSectionDurationBudget()) { flash(`Tổng thời gian các phần không được vượt quá ${length} phút.`); fields('[data-section-duration].is-invalid').at(-1)?.focus(); return false; }
        }
        return true;
    };

    fields('[data-template-card]').forEach(card => card.addEventListener('click', () => applyTemplate(card.dataset.templateCard)));
    fields('[data-template-card-link]').forEach(button => button.addEventListener('click', () => applyTemplate(button.dataset.templateCardLink)));
    fields('[data-template-category]').forEach(button => button.addEventListener('click', () => showTemplateCategory(button.dataset.templateCategory)));
    field('[data-add-section-part]').addEventListener('change', renderAddSectionControl);
    field('[data-template-create]').addEventListener('click', () => {
        fields('[data-template-category]').find(button => button.dataset.templateCategory === 'custom')?.click();
        applyTemplate('custom');
    });
    fields('[data-type-choice]').forEach(button => button.addEventListener('click', () => {
        const code = button.dataset.typeChoice;
        const id = code === 'toeic' ? 'toeic-full' : code === 'ielts' ? 'ielts-academic' : code === 'thpt' ? 'thpt' : 'custom';
        fields('[data-type-choice]').forEach(item => item.classList.toggle('is-active', item === button));
        if (typeCode() === code && (code === 'custom' || visualTemplate === id)) return;
        applyTemplate(id);
    }));
    field('[data-thpt-subject]')?.addEventListener('change', event => {
        const nextPartId = Number(event.currentTarget.value);
        if (nextPartId === activeThptPartId) return;
        if (allQuestions().length && !window.confirm('Đổi môn thi sẽ bỏ các câu hỏi đã chọn. Tiếp tục?')) {
            event.currentTarget.value = String(activeThptPartId);
            return;
        }
        activeThptPartId = nextPartId;
        if (visualTemplate === 'thpt') applyTemplate('thpt', { preserveQuestions: true });
    });
    fields('[data-step-link]').forEach(button => button.addEventListener('click', () => {
        const step = Number(button.dataset.stepLink);
        if (step <= activeStep || (step === activeStep + 1 && validateBeforeContinue())) showStep(step);
    }));
    field('[data-step-next]').addEventListener('click', () => { if (validateBeforeContinue()) showStep(activeStep + 1); });
    field('[data-step-back]').addEventListener('click', () => showStep(activeStep - 1));
    fields('[data-jump-step]').forEach(button => button.addEventListener('click', () => showStep(Number(button.dataset.jumpStep))));
    field('[data-save-draft]')?.addEventListener('click', () => {
        const title = field('[name="title"]');
        if (!title.value.trim()) { flash('Nhập tên đề thi ở bước cấu hình trước khi lưu.'); showStep(1); title.focus(); return; }
        if (!Number(duration.value)) { flash('Nhập thời gian làm bài trước khi lưu.'); showStep(1); duration.focus(); return; }
        if (!validateSectionDurationBudget()) { showStep(1); flash(`Tổng thời gian các phần không được vượt quá ${Number(duration.value)} phút.`); fields('[data-section-duration].is-invalid').at(-1)?.focus(); return; }
        sync(false);
        builder.requestSubmit();
    });
    fields('[data-policy-toggle]').forEach(input => input.addEventListener('change', () => { writePolicies({ [input.dataset.policyToggle]: input.checked }); sync(); }));
    fields('[data-policy-select]').forEach(select => select.addEventListener('change', () => { writePolicies({ [select.dataset.policySelect]: select.value }); sync(); }));
    duration.addEventListener('input', () => { writePolicies({ duration_minutes: Number(duration.value) }); validateSectionDurationBudget(); syncPreview(); });
    builder.addEventListener('input', event => {
        if (event.target.matches('[name="title"]')) syncPreview();
        if (event.target.matches('[data-section-duration]')) {
            const card = event.target.closest('[data-section-index]');
            const index = Number(card.dataset.sectionIndex), section = sections[index], rule = rules[index], value = Number(event.target.value);
            const subject = serverTemplate('thpt')?.subjects?.[getPart(section)?.code];
            if (visualTemplate === 'thpt' && subject && value !== subject.duration_minutes) changeToCustomIfNeeded();
            if (value > 0) { section.duration_minutes = value; rule.duration_minutes = value; }
            else { delete section.duration_minutes; delete rule.duration_minutes; }
            generation = null;
            validateSectionDurationBudget();
            sync();
        }
        const frame = field('[data-preview-frame]');
        if (!frame.hidden) { frame.hidden = true; field('[data-preview-feedback]').textContent = 'Nội dung đã thay đổi. Bấm xem toàn bộ để cập nhật.'; }
    });
    builder.addEventListener('change', event => {
        const card = event.target.closest('[data-section-index]');
        if (card) {
            const index = Number(card.dataset.sectionIndex), section = sections[index], rule = rules[index];
            if (event.target.matches('[data-section-count]')) {
                const count = Math.max(1, Number(event.target.value || 1));
                rule.difficulty = defaultDifficulty(count); section.target_count = count;
                changeToCustomIfNeeded();
                render();
                return;
            }
            if (event.target.matches('[data-difficulty]')) {
                rule.difficulty[event.target.dataset.difficulty] = Number(event.target.value || 0); section.target_count = ruleCount(rule);
                if (section.target_count < 1) { rule.difficulty[event.target.dataset.difficulty] = 1; section.target_count = 1; flash('Mỗi phần cần tối thiểu 1 câu. Bỏ phần nếu không sử dụng.'); }
                changeToCustomIfNeeded(); render(); return;
            }
            if (event.target.matches('[data-section-duration]')) {
                const value = Number(event.target.value);
                const subject = serverTemplate('thpt')?.subjects?.[getPart(section)?.code];
                if (visualTemplate === 'thpt' && subject && value !== subject.duration_minutes) changeToCustomIfNeeded();
                if (value > 0) { section.duration_minutes = value; rule.duration_minutes = value; } else { delete section.duration_minutes; delete rule.duration_minutes; }
                generation = null; validateSectionDurationBudget(); sync(); return;
            }
        }
        if (event.target.matches('[data-bank-select-visible]')) {
            const visible = filterQuestions().slice((bankPage - 1) * bankPageSize, bankPage * bankPageSize);
            if (generation && event.target.checked) {
                const selectable = visible.filter(question => question.has_approved_content);
                selectable.forEach(question => toggleQuestion(question, true));
                if (selectable.length < visible.length) flash('Một số câu chưa được duyệt nên không thể thêm vào template.');
                return;
            }
            const ids = new Set();
            visible.forEach(q => groupQuestions(q).forEach(item => ids.add(Number(item.id))));
            if (event.target.checked) {
                if (visible.some(q => Number(partById.get(Number(q.part))?.type) !== currentType())) flash('Câu khác kỳ thi hiện tại không được thêm vào đề.');
                const omittedPart = catalog.questions.find(question => ids.has(Number(question.id)) && sectionIndexForQuestion(question) < 0 && question.status !== 'archived');
                if (omittedPart) flash(`Một số câu thuộc phần ${partName(partById.get(Number(omittedPart.part)))} chưa được thêm. Hãy thêm phần thi trước.`);
                catalog.questions.filter(q => ids.has(Number(q.id)) && q.status !== 'archived').forEach(q => {
                    const index = sectionIndexForQuestion(q);
                    if (index >= 0 && !sections[index].question_ids.includes(Number(q.id))) sections[index].question_ids.push(Number(q.id));
                });
            } else {
                sections.forEach(section => { section.question_ids = section.question_ids.filter(id => !ids.has(Number(id))); });
            }
            generation = buildPresetGeneration(); sync(); renderBank(); renderSectionCards(); return;
        }
        if (event.target.matches('[data-bank-pick]')) {
            const q = questionById.get(Number(event.target.dataset.bankPick));
            if (q) toggleQuestion(q, event.target.checked);
            return;
        }
        if (event.target.matches('[data-bank-exam]')) { renderBankFilterOptions(); renderBankQuickCounts(); bankPage = 1; renderBank(); return; }
        if (event.target.matches('[data-bank-part], [data-bank-type], [data-bank-difficulty], [data-bank-status], [data-bank-tag]')) { bankPage = 1; renderBank(); }
        if (event.target.matches('[data-bank-page-size]')) { bankPageSize = Number(event.target.value); bankPage = 1; renderBank(); }
    });
    builder.addEventListener('click', async event => {
        const button = event.target.closest('button'); if (!button) return;
        if (button.matches('[data-toggle-quota]')) {
            const detail = button.closest('[data-section-index]').querySelector('.ex-quota-detail');
            detail.hidden = !detail.hidden; button.setAttribute('aria-expanded', String(!detail.hidden)); return;
        }
        if (button.matches('[data-remove-section]')) {
            const index = Number(button.closest('[data-section-index]').dataset.sectionIndex);
            sections.splice(index, 1); rules.splice(index, 1); changeToCustomIfNeeded(); render(); return;
        }
        if (button.matches('[data-move-section]')) {
            const index = Number(button.closest('[data-section-index]').dataset.sectionIndex), next = index + Number(button.dataset.moveSection);
            if (next >= 0 && next < sections.length) {
                [sections[index], sections[next]] = [sections[next], sections[index]];
                [rules[index], rules[next]] = [rules[next], rules[index]];
                changeToCustomIfNeeded(); render();
            }
            return;
        }
        if (button.matches('[data-add-section]')) {
            const part = partById.get(Number(field('[data-add-section-part]').value));
            if (!part || sections.some(section => Number(section.exam_part_id) === Number(part.id))) { renderAddSectionControl(); return; }
            sections.push({ exam_part_id: Number(part.id), question_ids: [], target_count: 10 }); rules.push(makeRule(part.id, 10)); changeToCustomIfNeeded(); render(); return;
        }
        if (button.matches('[data-quick-filter]')) {
            quickFilter = button.dataset.quickFilter;
            fields('[data-quick-filter]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
            bankPage = 1; renderBank(); return;
        }
        if (button.matches('[data-bank-page]')) { bankPage = Number(button.dataset.bankPage); renderBank(); return; }
        if (button.matches('[data-bank-preview]')) {
            const id = Number(button.dataset.bankPreview);
            previewCandidateId = id;
            previewIndex = Math.max(0, allRows().findIndex(row => row.id === id));
            questionTab = 'question';
            fields('[data-question-tab]').forEach(tab => tab.setAttribute('aria-pressed', String(tab.dataset.questionTab === questionTab)));
            renderQuestionPreview(); showStep(2); return;
        }
        if (button.matches('[data-clear-selection]')) {
            sections.forEach(section => { section.question_ids = []; }); generation = buildPresetGeneration(); previewIndex = 0; render(); return;
        }
        if (button.matches('[data-question-tab]')) {
            questionTab = button.dataset.questionTab;
            fields('[data-question-tab]').forEach(tab => tab.setAttribute('aria-pressed', String(tab === button)));
            renderQuestionPreview(); return;
        }
        if (button.matches('[data-question-prev], [data-question-next]')) {
            previewCandidateId = null; previewIndex += button.matches('[data-question-prev]') ? -1 : 1; renderQuestionPreview(); return;
        }
        if (button.matches('[data-reset-bank]')) {
            field('[data-bank-search]').value = ''; field('[data-bank-exam]').value = ''; field('[data-bank-difficulty]').value = ''; field('[data-bank-status]').value = ''; field('[data-bank-part]').value = ''; field('[data-bank-type]').value = ''; field('[data-bank-tag]').value = ''; quickFilter = 'all';
            fields('[data-quick-filter]').forEach(item => item.setAttribute('aria-pressed', String(item.dataset.quickFilter === 'all')));
            renderBankFilterOptions(); renderBankQuickCounts(); bankPage = 1; renderBank(); return;
        }
        if (button.matches('[data-generate-exam]')) {
            const snapshot = JSON.stringify({ exam_type_id: currentType(), variant: variant.value, template: template.value, rules, sections });
            const sent = JSON.stringify({ exam_type_id: currentType(), variant: variant.value || null, generation: { template: template.value, sections: rules } });
            button.disabled = true; flash('Đang bốc câu hỏi theo cấu trúc và độ khó…');
            try {
                const response = await fetch(builder.dataset.generateUrl, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': field('[name="_token"]').value }, body: sent });
                const data = await response.json(); if (!response.ok) throw new Error(errorMessage(data));
                if (snapshot !== JSON.stringify({ exam_type_id: currentType(), variant: variant.value, template: template.value, rules, sections })) throw new Error('Cấu hình đã thay đổi trong lúc bốc câu. Hãy thử lại.');
                sections = data.sections.map(section => ({ ...section, exam_part_id: Number(section.exam_part_id), question_ids: section.question_ids.map(Number) }));
                generation = data.generation; rules = generation.sections; previewIndex = 0; render(); flash('Đã bốc câu hỏi theo cấu trúc. Bạn có thể rà soát và lưu bản nháp.');
            } catch (error) { flash(error.message); } finally { button.disabled = false; }
            return;
        }
        if (button.matches('[data-preview-exam]')) {
            const title = field('[name="title"]');
            if (!title.value.trim()) { showStep(1); flash('Nhập tên đề thi trước khi xem trước.'); title.focus(); return; }
            sync(false);
            const output = field('[data-preview-feedback]'), frame = field('[data-preview-frame]'), data = new FormData(builder);
            data.delete('_method'); button.disabled = true; frame.hidden = true; output.textContent = 'Đang tải bản xem trước…';
            try {
                const response = await fetch(builder.dataset.previewUrl, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': data.get('_token') } });
                if (!response.ok) throw new Error(errorMessage(await response.json()));
                frame.srcdoc = await response.text(); frame.hidden = false; output.textContent = 'Bản xem trước nội dung hiện tại.';
            } catch (error) { output.textContent = error.message; } finally { button.disabled = false; }
        }
    });
    field('[data-bank-search]').addEventListener('input', () => { bankPage = 1; renderBank(); });
    field('[data-score-scale]')?.addEventListener('change', () => syncPreview());
    builder.addEventListener('submit', event => {
        if (!validateSectionDurationBudget()) {
            event.preventDefault();
            showStep(1);
            flash(`Tổng thời gian các phần không được vượt quá ${Number(duration.value)} phút.`);
            fields('[data-section-duration].is-invalid').at(-1)?.focus();
            return;
        }
        if (generation && allQuestions().some(question => !question.has_approved_content)) {
            event.preventDefault();
            showStep(2);
            flash('Template không thể lưu với câu hỏi chưa được duyệt. Hãy duyệt câu hỏi hoặc bỏ câu đó khỏi đề.');
            return;
        }
        sync(false);
    });
    field('[data-exam-validate]')?.addEventListener('click', async event => {
        const output = field('[data-validation-output]');
        try {
            const response = await fetch(event.currentTarget.dataset.examValidate, { headers: { Accept: 'application/json' } });
            const data = await response.json(); output.textContent = response.ok ? data.errors.length ? data.errors.join('\n') : 'Cấu trúc hợp lệ.' : errorMessage(data);
        } catch { output.textContent = 'Không thể kiểm tra. Hãy kiểm tra kết nối.'; }
    });

    maybeInitialTemplate();
    if (['toeic', 'ielts', 'thpt'].includes(typeCode())) field('[data-bank-exam]').value = typeCode();
    field('[data-thpt-subject-field]').hidden = visualTemplate !== 'thpt';
    showTemplateCategory(visualTemplate.startsWith('ielts') ? 'ielts' : visualTemplate === 'custom' ? 'custom' : visualTemplate.startsWith('thpt') ? 'thpt' : 'toeic');
    writePolicies({});
    render(false);
    showStep(activeStep);
}
refreshIcons();
