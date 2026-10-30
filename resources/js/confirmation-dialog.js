const adminConfirmDialog = document.querySelector('[data-admin-confirm-dialog]');
const adminConfirmPanel = adminConfirmDialog?.querySelector('[role="dialog"]');
const adminConfirmTitle = adminConfirmDialog?.querySelector('#admin-confirm-title');
const adminConfirmMessage = adminConfirmDialog?.querySelector('#admin-confirm-message');
const adminConfirmSubmit = adminConfirmDialog?.querySelector('[data-admin-confirm-submit]');
const adminConfirmCancel = adminConfirmDialog?.querySelectorAll('[data-admin-confirm-cancel]') ?? [];
let pendingAdminConfirmation = null;
let adminConfirmPreviousFocus = null;

const finishAdminConfirmation = confirmed => {
    if (!pendingAdminConfirmation) {
        return;
    }

    const { resolve } = pendingAdminConfirmation;
    pendingAdminConfirmation = null;
    adminConfirmDialog.hidden = true;
    document.body.classList.remove('admin-confirm-open');
    try {
        adminConfirmPreviousFocus?.focus({ preventScroll: true });
    } catch {}
    adminConfirmPreviousFocus = null;
    resolve(confirmed);
};

export const requestAdminConfirmation = (message, options = {}) => {
    if (!adminConfirmDialog || !adminConfirmPanel || !adminConfirmTitle || !adminConfirmSubmit || !adminConfirmMessage) {
        return Promise.resolve(window.confirm(message));
    }

    if (pendingAdminConfirmation) {
        finishAdminConfirmation(false);
    }

    adminConfirmTitle.textContent = options.title ?? 'Xác nhận thao tác';
    adminConfirmMessage.textContent = message;
    adminConfirmSubmit.textContent = options.confirmLabel ?? 'Xác nhận';
    adminConfirmSubmit.classList.toggle('button-danger', options.danger !== false);
    adminConfirmSubmit.classList.toggle('button-primary', options.danger === false);
    adminConfirmPreviousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    adminConfirmDialog.hidden = false;
    document.body.classList.add('admin-confirm-open');

    return new Promise(resolve => {
        pendingAdminConfirmation = { resolve };
        window.requestAnimationFrame(() => adminConfirmDialog.querySelector('button[data-admin-confirm-cancel]')?.focus());
    });
};

window.vistiqConfirm = requestAdminConfirmation;
adminConfirmCancel.forEach(control => control.addEventListener('click', () => finishAdminConfirmation(false)));
adminConfirmSubmit?.addEventListener('click', () => finishAdminConfirmation(true));
document.addEventListener('keydown', event => {
    if (!pendingAdminConfirmation || adminConfirmDialog?.hidden) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        finishAdminConfirmation(false);
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusable = [adminConfirmDialog.querySelector('button[data-admin-confirm-cancel]'), adminConfirmSubmit].filter(control => control instanceof HTMLElement && !control.disabled);
    if (focusable.length < 2) {
        return;
    }

    const currentIndex = focusable.indexOf(document.activeElement);
    const nextIndex = event.shiftKey
        ? (currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1)
        : (currentIndex === focusable.length - 1 ? 0 : currentIndex + 1);
    event.preventDefault();
    focusable[nextIndex].focus();
});

