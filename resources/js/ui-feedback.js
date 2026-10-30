export function notify(message, failure = false) {
    document.querySelector('[data-ui-feedback]')?.remove();
    document.querySelectorAll('[data-admin-toast]').forEach(notice => notice.remove());
    const notice = document.createElement('div');
    notice.className = failure ? 'admin-alert admin-alert-error' : 'admin-toast';
    notice.dataset.uiFeedback = '';
    notice.setAttribute('role', failure ? 'alert' : 'status');
    notice.setAttribute('aria-live', failure ? 'assertive' : 'polite');
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.textContent = '×';
    close.setAttribute('aria-label', 'Đóng thông báo');
    let timeout;
    const dismiss = () => {
        window.clearTimeout(timeout);
        notice.classList.add('is-leaving');
        window.setTimeout(() => notice.remove(), 240);
    };
    close.addEventListener('click', dismiss);
    notice.append(text, close);
    (document.querySelector('.admin-page') ?? document.querySelector('main') ?? document.body).prepend(notice);
    if (!failure) timeout = window.setTimeout(dismiss, 6000);
}

document.addEventListener('click', async event => {
    const link = event.target.closest('a[data-report-export]');
    if (!link) return;
    event.preventDefault();
    if (link.getAttribute('aria-busy') === 'true') return;
    const label = link.textContent;
    link.setAttribute('aria-busy', 'true');
    link.setAttribute('aria-disabled', 'true');
    link.textContent = 'Đang tạo báo cáo…';
    try {
        const response = await fetch(link.href, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            const result = await response.json().catch(() => ({}));
            throw new Error(response.status === 403 ? 'Bạn chưa có quyền xuất báo cáo.' : Object.values(result.errors ?? {}).flat().join(' ') || 'Không thể tạo báo cáo. Hãy thử lại hoặc thu hẹp bộ lọc.');
        }
        if (!/text\/csv|spreadsheetml/.test(response.headers.get('content-type') ?? '')) {
            throw new Error('Phiên đăng nhập đã hết hiệu lực. Hãy đăng nhập lại để xuất báo cáo.');
        }
        const url = URL.createObjectURL(await response.blob());
        const download = document.createElement('a');
        download.href = url;
        download.download = /filename="([^"]+)"/.exec(response.headers.get('content-disposition') ?? '')?.[1] ?? 'vistiq-report';
        download.click();
        setTimeout(() => URL.revokeObjectURL(url), 10000);
        notify('Đã tạo và tải báo cáo theo bộ lọc đang xem.');
    } catch (error) {
        notify(error.message, true);
    } finally {
        link.textContent = label;
        link.removeAttribute('aria-busy');
        link.removeAttribute('aria-disabled');
    }
});

document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.hasAttribute('data-confirm') && !document.body.classList.contains('admin-mode') && !form.dataset.uiConfirmed) {
        event.preventDefault();
        if (form.dataset.uiConfirming) return;
        form.dataset.uiConfirming = 'true';
        const accepted = await window.vistiqConfirm(form.dataset.confirm, { title: form.dataset.confirmTitle, confirmLabel: form.dataset.confirmLabel ?? event.submitter?.textContent?.trim() });
        delete form.dataset.uiConfirming;
        if (accepted) {
            form.dataset.uiConfirmed = 'true';
            form.requestSubmit(event.submitter);
        }
        return;
    }
    delete form.dataset.uiConfirmed;
    if (form.hasAttribute('data-preserve-upload')) {
        event.preventDefault();
        if (form.dataset.uiSubmitting) return;
        form.dataset.uiSubmitting = 'true';
        form.setAttribute('aria-busy', 'true');
        const body = new FormData(form);
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        const contents = buttons.map(button => [...button.childNodes]);
        buttons.forEach(button => { button.disabled = true; button.textContent = form.dataset.pendingLabel; });
        form.querySelectorAll('.form-field-error').forEach(field => field.textContent = '');
        form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
        try {
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                for (const [field, messages] of Object.entries(result.errors ?? {})) {
                    const error = document.getElementById(`${field}-error`);
                    if (error) error.textContent = messages.join(' ');
                    form.elements.namedItem(field)?.setAttribute('aria-invalid', 'true');
                }
                form.querySelector('[aria-invalid="true"]')?.focus();
                throw new Error(Object.values(result.errors ?? {}).flat().join(' ') || 'Chưa lưu được hồ sơ. Kiểm tra kết nối và thử lại; ảnh đã chọn được giữ trên trang.');
            }
            if (!result.redirect) throw new Error('Phiên đăng nhập đã hết hiệu lực. Hãy đăng nhập lại.');
            window.location.assign(result.redirect);
        } catch (error) { notify(error.message, true); }
        finally {
            delete form.dataset.uiSubmitting;
            form.removeAttribute('aria-busy');
            buttons.forEach((button, index) => { button.disabled = false; button.replaceChildren(...contents[index]); });
        }
        return;
    }
    if (form.hasAttribute('data-download')) {
        event.preventDefault();
        if (form.dataset.uiSubmitting) return;
        form.dataset.uiSubmitting = 'true';
        form.setAttribute('aria-busy', 'true');
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        const labels = buttons.map(button => button.textContent);
        const body = new FormData(form);
        buttons.forEach(button => { button.disabled = true; button.textContent = form.dataset.pendingLabel ?? 'Đang tải…'; });
        try {
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
            if (!response.ok) {
                const result = await response.json().catch(() => ({}));
                const message = Object.values(result.errors ?? {}).flat().join(' ') || 'Không thể tạo hoặc tải bản sao. Kiểm tra quyền, mật khẩu và thử lại.';
                throw new Error(message);
            }
            if (!(response.headers.get('content-type') ?? '').includes('application/sql')) throw new Error('Phiên đăng nhập đã hết hiệu lực. Hãy đăng nhập lại.');
            const url = URL.createObjectURL(await response.blob());
            const download = document.createElement('a');
            download.href = url;
            download.download = /filename="([^"]+)"/.exec(response.headers.get('content-disposition') ?? '')?.[1] ?? 'vistiq-backup.sql';
            download.click();
            setTimeout(() => URL.revokeObjectURL(url), 10000);
            form.querySelectorAll('input[type="password"]').forEach(input => input.value = '');
            notify('Đã kiểm tra integrity và tải bản sao lưu. Tải lại trang để cập nhật danh sách.');
        } catch (error) { notify(error.message, true); }
        finally {
            delete form.dataset.uiSubmitting;
            form.removeAttribute('aria-busy');
            buttons.forEach((button, index) => { button.disabled = false; button.textContent = labels[index]; });
        }
        return;
    }
    if (!form.hasAttribute('data-pending-label')) return;
    if (form.dataset.uiSubmitting) {
        event.preventDefault();
        return;
    }
    form.dataset.uiSubmitting = 'true';
    form.setAttribute('aria-busy', 'true');
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => {
        button.dataset.uiOriginalLabel = button.textContent;
        button.disabled = true;
        button.textContent = form.dataset.pendingLabel;
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-ui-submitting]').forEach(form => {
        delete form.dataset.uiSubmitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-ui-original-label]').forEach(button => {
            button.disabled = false;
            button.textContent = button.dataset.uiOriginalLabel;
        });
    });
});

document.querySelectorAll('.icon-button[aria-label]:not([title])').forEach(control => control.title = control.getAttribute('aria-label'));
if (!document.body.classList.contains('admin-mode')) {
    document.querySelectorAll('[data-admin-toast]').forEach(toast => {
        const dismiss = () => {
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 240);
        };

        toast.querySelector('[data-dismiss-admin-toast]')?.addEventListener('click', dismiss);
        const timeout = Number(toast.dataset.adminToastTimeout ?? 6000);
        if (timeout > 0) window.setTimeout(dismiss, timeout);
    });
}

const avatarForm = document.querySelector('[data-avatar-form]');
document.querySelector('[data-profile-section]')?.addEventListener('change', event => { window.location.assign(event.target.value); });
if (avatarForm) {
    const input = avatarForm.querySelector('[name="avatar"]');
    const preview = avatarForm.querySelector('[data-avatar-preview]');
    const error = avatarForm.querySelector('#avatar-error');
    const remove = avatarForm.querySelector('[name="remove_avatar"]');
    const original = preview.getAttribute('src');
    const fallback = avatarForm.querySelector('[data-avatar-fallback]');
    avatarForm.querySelectorAll('[data-avatar-open]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        const controls = avatarForm.querySelector('#profile-avatar-controls');
        if (controls) { controls.open = true; controls.scrollIntoView({ block: 'nearest' }); input.focus({ preventScroll: true }); }
    }));
    preview.addEventListener('error', () => { preview.hidden = true; if (fallback) fallback.hidden = false; });
    const bio = avatarForm.querySelector('[data-profile-bio]');
    bio?.addEventListener('input', () => { avatarForm.querySelector('[data-profile-bio-count]').textContent = [...bio.value].length; });
    let objectUrl;
    input.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = input.files[0];
        error.textContent = '';
        input.setCustomValidity('');
        if (file && (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 2*1024*1024)) {
            error.textContent = 'Chọn ảnh JPEG, PNG hoặc WebP tối đa 2 MB.';
            input.setCustomValidity(error.textContent);
            return;
        }
        if (remove && file) remove.checked = false;
        objectUrl = file ? URL.createObjectURL(file) : null;
        preview.src = objectUrl ?? original;
        preview.hidden = !file && !original;
        if (fallback) fallback.hidden = !preview.hidden;
    });
    remove?.addEventListener('change', () => {
        if (remove.checked) { input.value = ''; input.setCustomValidity(''); error.textContent = ''; preview.hidden = true; if (fallback) fallback.hidden = false; }
        else { preview.src = original; preview.hidden = !original; if (fallback) fallback.hidden = !!original; }
    });
}

const sidebarGroups = [...document.querySelectorAll('[data-sidebar-nav-group]')];
if (sidebarGroups.length) {
    const mobileNavigation = matchMedia('(max-width: 680px)');
    const adaptNavigation = () => sidebarGroups.forEach(group => { group.open = !mobileNavigation.matches && group.classList.contains('is-active'); });
    adaptNavigation();
    mobileNavigation.addEventListener('change', adaptNavigation);
    sidebarGroups.forEach(group => group.addEventListener('toggle', () => {
        if (mobileNavigation.matches && group.open) sidebarGroups.filter(other => other !== group).forEach(other => other.open = false);
    }));
    document.addEventListener('click', event => {
        if (mobileNavigation.matches && !event.target.closest('.admin-nav')) sidebarGroups.forEach(group => group.open = false);
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !mobileNavigation.matches) return;
        const opened = sidebarGroups.find(group => group.open);
        if (opened) { opened.open = false; opened.querySelector('summary').focus(); }
    });
}

const adminAccountMenu = document.querySelector('[data-admin-account-menu]');
if (adminAccountMenu) {
    adminAccountMenu.addEventListener('toggle', () => {
        if (adminAccountMenu.open && matchMedia('(max-width: 680px)').matches) sidebarGroups.forEach(group => { group.open = false; });
    });
    sidebarGroups.forEach(group => group.addEventListener('toggle', () => {
        if (group.open) adminAccountMenu.open = false;
    }));
    document.addEventListener('click', event => {
        if (!adminAccountMenu.contains(event.target)) adminAccountMenu.open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && adminAccountMenu.open) {
            adminAccountMenu.open = false;
            adminAccountMenu.querySelector('summary').focus();
        }
    });
}
