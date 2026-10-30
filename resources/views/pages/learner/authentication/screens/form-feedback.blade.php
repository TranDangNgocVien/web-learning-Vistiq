@once
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-auth-feedback]').forEach(form => {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (form.dataset.pending === 'true') { event.preventDefault(); return; }
            form.dataset.pending = 'true';
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type=submit]').forEach(button => {
                button.dataset.originalLabel = button.textContent;
                button.disabled = true;
                button.textContent = form.dataset.pendingLabel || 'Đang xử lý…';
            });
        });
        window.addEventListener('pageshow', () => {
            delete form.dataset.pending;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('button[data-original-label]').forEach(button => { button.disabled = false; button.textContent = button.dataset.originalLabel; });
        });
    });
});
</script>
@endonce
