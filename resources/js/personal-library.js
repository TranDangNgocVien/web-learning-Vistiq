import { notify } from './ui-feedback.js';

let pronunciation;
document.addEventListener('click', async event => {
    const button = event.target.closest('[data-library-audio]');
    if (!button) return;
    pronunciation?.pause();
    pronunciation = new Audio(button.dataset.libraryAudio);
    button.disabled=true;
    try { await pronunciation.play(); }
    catch { notify('Không phát được audio. Hãy thử lại hoặc mở mục trong Tra cứu.',true); }
    finally { button.disabled=false; }
});

let selectionRequest;
document.addEventListener('click', async event => {
    const link = event.target.closest('[data-library-select]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    selectionRequest?.abort();
    const request = selectionRequest = new AbortController();
    const library = document.querySelector('[data-knowledge-library]');
    library.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(link.href, { signal:request.signal, headers:{ Accept:'text/html' } });
        if (!response.ok || response.redirected) throw new Error('Không tải được mục đã lưu. Hãy tải lại trang hoặc đăng nhập lại.');
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = html.querySelector('[data-knowledge-library]');
        if (!replacement) throw new Error('Không tải được chi tiết. Hãy tải lại trang.');
        if (selectionRequest !== request) return;
        library.replaceWith(replacement);
        history.pushState({}, '', link.href);
        const detail = replacement.querySelector('#knowledge-detail');
        if (matchMedia('(max-width:850px)').matches) detail?.scrollIntoView({block:'start'});
        detail?.focus({preventScroll:true});
    } catch (error) {
        if (error.name !== 'AbortError') notify(error.message, true);
    } finally { library.removeAttribute('aria-busy'); }
});
window.addEventListener('popstate', () => {
    if (document.querySelector('[data-knowledge-library]') && !document.querySelector('form[data-ajax-filter-form]')) location.reload();
});

document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-library-toggle]');
    if (!form || event.defaultPrevented) return;
    event.preventDefault();
    if (form.dataset.librarySaving) return;
    form.dataset.librarySaving = 'true';
    const button = form.querySelector('button[type="submit"]');
    const contents = [...button.childNodes];
    const icon = button.querySelector('svg')?.cloneNode(true);
    button.disabled = true;
    form.setAttribute('aria-busy','true');
    button.textContent = 'Đang lưu…';
    try {
        const response = await fetch(form.action, { method:'POST', body:new FormData(form), headers:{Accept:'application/json'} });
        const result = await response.json().catch(()=>({}));
        if (!response.ok || typeof result.saved !== 'boolean') throw new Error(response.status === 401 ? 'Hãy đăng nhập lại để lưu kiến thức.' : 'Chưa lưu được kiến thức. Kiểm tra kết nối và thử lại.');
        form.querySelector('[name="_method"]')?.remove();
        if (result.saved) {
            const method = document.createElement('input'); method.type='hidden'; method.name='_method'; method.value='DELETE'; form.append(method);
            form.dataset.confirm = 'Bỏ lưu mục này? Ghi chú cá nhân của mục này cũng sẽ bị xóa.';
            form.dataset.confirmLabel = 'Bỏ lưu kiến thức';
        } else { delete form.dataset.confirm; delete form.dataset.confirmLabel; }
        button.replaceChildren(...(icon ? [icon] : []), document.createTextNode(result.saved ? ' Bỏ lưu khỏi kho kiến thức' : ' Lưu vào kho kiến thức'));
        notify(result.message);
    } catch (error) { button.replaceChildren(...contents); notify(error.message, true); }
    finally { button.disabled=false; delete form.dataset.librarySaving; form.removeAttribute('aria-busy'); }
});
