import { initializeExamRunner } from './exam-runner.js';

const firstValidationError = errors => Object.values(errors ?? {}).flat()[0];

document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-exam-start]');
    if (!form || (form.dataset.requiresFullscreen !== 'true' && form.dataset.allowMobile !== 'false')) return;

    event.preventDefault();
    const button = event.submitter ?? form.querySelector('[type="submit"]');
    const feedback = form.parentElement.querySelector('[data-start-feedback]');
    const previousButtonHtml = button?.innerHTML;
    const announce = message => {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.hidden = false;
    };

    if (form.dataset.allowMobile === 'false' && window.matchMedia('(max-width: 600px)').matches) {
        announce('Đề này chỉ làm được trên tablet hoặc máy tính. Bài thi chưa bắt đầu.');
        return;
    }

    let attemptUrl = null;
    if (button) {
        button.disabled = true;
        button.textContent = 'Đang mở bài thi…';
    }
    form.setAttribute('aria-busy', 'true');
    announce(form.dataset.requiresFullscreen === 'true' ? 'Đang bật toàn màn hình và chuẩn bị bài thi…' : 'Đang chuẩn bị bài thi…');

    try {
        if (form.dataset.requiresFullscreen === 'true' && !document.fullscreenElement) {
            if (!document.fullscreenEnabled || typeof document.documentElement.requestFullscreen !== 'function') {
                throw new Error('Trình duyệt này không hỗ trợ chế độ toàn màn hình bắt buộc. Bài thi chưa bắt đầu.');
            }
            await document.documentElement.requestFullscreen({ navigationUI: 'hide' });
            if (!document.fullscreenElement) throw new Error('Không bật được toàn màn hình. Bài thi chưa bắt đầu.');
        }

        const startResponse = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const startData = await startResponse.json().catch(() => ({}));
        if (!startResponse.ok) {
            throw new Error(startData.message ?? firstValidationError(startData.errors) ?? 'Không thể bắt đầu bài thi. Hãy thử lại.');
        }
        attemptUrl = startData.url;

        const runnerResponse = await fetch(attemptUrl, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        const runnerHtml = await runnerResponse.text();
        const runnerPage = new DOMParser().parseFromString(runnerHtml, 'text/html');
        const runner = runnerPage.querySelector('[data-exam-runner]');
        if (!runner) {
            if (runnerResponse.redirected) {
                window.location.assign(runnerResponse.url);
                return;
            }
            throw new Error('Không tải được giao diện làm bài. Đang mở lại lượt thi…');
        }

        for (const stylesheet of runnerPage.head.querySelectorAll('link[rel="stylesheet"]')) {
            if (stylesheet.href && ![...document.head.querySelectorAll('link[rel="stylesheet"]')].some(existing => existing.href === stylesheet.href)) {
                document.head.append(stylesheet.cloneNode(true));
            }
        }
        document.title = runnerPage.title;
        const confirmationDialog = document.body.querySelector('[data-admin-confirm-dialog]');
        document.body.replaceChildren(runner, ...(confirmationDialog ? [confirmationDialog] : []));
        window.history.pushState({ examAttempt: true }, '', attemptUrl);
        window.scrollTo(0, 0);
        initializeExamRunner(runner);
    } catch (error) {
        if (attemptUrl) {
            window.location.assign(attemptUrl);
            return;
        }
        if (document.fullscreenElement && typeof document.exitFullscreen === 'function') {
            await document.exitFullscreen().catch(() => {});
        }
        if (button) {
            button.disabled = false;
            button.innerHTML = previousButtonHtml;
        }
        form.removeAttribute('aria-busy');
        announce(error.message);
    }
});
