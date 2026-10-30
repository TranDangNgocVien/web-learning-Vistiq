const library = document.querySelector('[data-reading-library]');
if (library?.dataset.editorUrl) {
    const target = library.querySelector('[data-reading-editor]'), heading = library.querySelector('[data-reading-editor-heading]'), feedback = library.querySelector('[data-reading-feedback]');
    let controller, request;
    const showError = message => { feedback.textContent = message; feedback.hidden = false; };
    async function openEditor(id) {
        if (controller && !controller.canLeave()) return;
        request?.abort(); request = new AbortController(); const current = request;
        feedback.hidden = true; target.setAttribute('aria-busy', 'true');
        try {
            const url = new URL(library.dataset.editorUrl, location.origin); if (id) url.searchParams.set('question', id);
            const response = await fetch(url, { signal: current.signal, headers: { Accept: 'text/html' } });
            if (!response.ok || response.redirected) throw new Error('Không tải được câu hỏi. Hãy thử lại hoặc tải lại trang.');
            const [html, { initQuestionEditor }] = await Promise.all([response.text(), import('./question-editor.js')]); if (request !== current) return;
            controller?.destroy(); target.querySelectorAll('audio').forEach(audio => audio.pause()); target.innerHTML = html; controller = initQuestionEditor(target.querySelector('[data-qe-root]'));
            heading.hidden = false; heading.querySelector('[data-reading-editor-title]').textContent = id ? `Câu hỏi Q-${String(id).padStart(5, '0')}` : 'Thêm câu hỏi';
            library.querySelectorAll('[data-reading-edit-question]').forEach(button => button.classList.toggle('is-active', button.dataset.readingEditQuestion === String(id)));
            heading.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
        } catch (error) { if (error.name !== 'AbortError') showError(error.message); }
        finally { if (request === current) target.removeAttribute('aria-busy'); }
    }
    library.addEventListener('click', event => { const trigger = event.target.closest('[data-reading-edit-question]'); if (trigger) openEditor(trigger.dataset.readingEditQuestion); });
    library.querySelector('[data-reading-close-editor]').addEventListener('click', () => {
        if (controller && !controller.canLeave()) return;
        request?.abort(); controller?.destroy(); controller = null; target.querySelectorAll('audio').forEach(audio => audio.pause()); target.replaceChildren(); heading.hidden = true; library.querySelector('[data-reading-edit-question]')?.focus();
    });
    library.addEventListener('qe:saved', event => {
        if (event.detail.questions_html) library.querySelector('[data-reading-questions]').innerHTML = event.detail.questions_html;
        const count = library.querySelectorAll('.reading-question-row').length;
        library.querySelector('[data-selected-reading-count]')?.replaceChildren(document.createTextNode(`${count} câu hỏi`));
        heading.querySelector('[data-reading-editor-title]').textContent = `Câu hỏi Q-${String(event.detail.id).padStart(5, '0')}`;
    });
    library.addEventListener('qe:complete', event => { if (event.detail.next && !event.detail.dirty) openEditor(''); });
    if (library.dataset.initialQuestion) openEditor(library.dataset.initialQuestion); else if (library.dataset.autoAdd === '1') openEditor('');
}
