export function initializeDictionarySearch(root = document, request = fetch) {
    root.querySelectorAll('[data-dictionary-search]').forEach((form) => {
        if (form.dataset.dictionaryInitialized === 'true') return;
        form.dataset.dictionaryInitialized = 'true';
        const input = form.querySelector('[data-dictionary-input]');
        const list = form.querySelector('[data-dictionary-suggestions]');
        const status = form.querySelector('[data-dictionary-status]');
        const filter = form.querySelector('select[name="type"]');
        const clearButton = form.querySelector('[data-dictionary-clear]');
        let timer;
        let controller;
        let generation = 0;
        let items = [];
        let selected = -1;

        const close = () => {
            clearTimeout(timer);
            controller?.abort();
            generation += 1;
            items = [];
            selected = -1;
            list.replaceChildren();
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            input.removeAttribute('aria-busy');
            status.textContent = '';
        };

        const choose = (index) => {
            if (items[index]) {
                input.value = items[index].term;
                close();
                form.requestSubmit();
            }
        };

        const suggest = async () => {
            close();
            const term = input.value.trim();

            if (term.length < 2 || term.length > 100) {
                return;
            }

            const currentGeneration = generation;
            controller = new AbortController();
            const url = new URL(form.dataset.suggestionsUrl, window.location.origin);
            url.searchParams.set('q', term);
            url.searchParams.set('type', filter.value);
            input.setAttribute('aria-busy', 'true');
            status.textContent = 'Đang tìm gợi ý…';

            try {
                const response = await request(url, { signal: controller.signal, headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error('Suggestions unavailable');
                }

                const payload = await response.json();

                if (currentGeneration !== generation) {
                    return;
                }

                items = Array.isArray(payload.data) ? payload.data : [];
                items.forEach((item, index) => {
                    const option = root.createElement('li');
                    const label = root.createElement('span');
                    const type = root.createElement('small');
                    option.id = `dictionary-suggestion-${index}`;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    label.textContent = item.term;
                    type.textContent = item.type_label;
                    option.append(label, type);
                    option.addEventListener('mousedown', (event) => event.preventDefault());
                    option.addEventListener('click', () => choose(index));
                    list.append(option);
                });
                list.hidden = items.length === 0;
                input.setAttribute('aria-expanded', String(items.length > 0));
                status.textContent = items.length > 0 ? `${items.length} gợi ý. Dùng phím lên xuống để chọn.` : 'Chưa có gợi ý. Nhấn Enter để tìm trong toàn bộ nội dung.';
            } catch (error) {
                if (error.name !== 'AbortError' && currentGeneration === generation) {
                    status.textContent = 'Không tải được gợi ý. Bạn vẫn có thể nhấn Tra cứu.';
                }
            } finally {
                if (currentGeneration === generation) {
                    input.removeAttribute('aria-busy');
                }
            }
        };

        input.addEventListener('input', () => {
            close();
            timer = setTimeout(suggest, 250);
        });
        if (clearButton) {
            clearButton.hidden = false;
            clearButton.addEventListener('click', () => {
                input.value = '';
                close();
                input.focus();
                if (form.matches('[data-ajax-filter-form]')) form.requestSubmit();
            });
        }
        filter.addEventListener('change', suggest);
        input.addEventListener('blur', close);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
                return;
            }

            if (list.hidden) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                selected = selected < 0
                    ? (event.key === 'ArrowDown' ? 0 : items.length - 1)
                    : (selected + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                [...list.children].forEach((option, index) => option.setAttribute('aria-selected', String(index === selected)));
                input.setAttribute('aria-activedescendant', list.children[selected].id);
                list.children[selected].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter' && selected >= 0) {
                event.preventDefault();
                choose(selected);
            }
        });
        form.addEventListener('submit', () => {
            close();
            if (form.matches('[data-ajax-filter-form]')) return;
            form.querySelector('button[type="submit"]').textContent = 'Đang tra cứu…';
            form.setAttribute('aria-busy', 'true');
        });
        window.addEventListener('pageshow', () => {
            form.removeAttribute('aria-busy');
            form.querySelector('button[type="submit"]').textContent = 'Tra cứu';
        });
    });

    if ('speechSynthesis' in window && 'SpeechSynthesisUtterance' in window) {
        root.querySelectorAll('[data-pronounce]').forEach((button) => {
            if (button.dataset.pronounceInitialized === 'true') return;
            button.dataset.pronounceInitialized = 'true';
            button.hidden = false;
            button.addEventListener('click', () => {
                const status = button.parentElement.querySelector('[data-pronunciation-status]');
                const utterance = new window.SpeechSynthesisUtterance(button.dataset.pronounce);
                utterance.lang = 'en-US';
                utterance.rate = 0.85;
                utterance.onend = () => { status.textContent = ''; };
                utterance.onerror = () => { status.textContent = 'Trình duyệt chưa phát được âm thanh. Hãy thử lại.'; };
                window.speechSynthesis.cancel();
                status.textContent = 'Đang phát âm bằng giọng đọc của trình duyệt…';
                window.speechSynthesis.speak(utterance);
            });
        });
    }
}

export function syncDictionarySearchFilters(url, root = document) {
    const params = url instanceof URL ? url.searchParams : new URL(url, window.location.href).searchParams;
    const selectedType = params.get('type') || 'all';
    const selectedTab = params.get('tab') || '';

    root.querySelectorAll('[data-dictionary-search]').forEach(form => {
        const filter = form.querySelector('select[name="type"]');
        if (filter) filter.value = selectedType;

        form.querySelectorAll('.dictionary-types button[name="type"]').forEach(button => {
            const active = selectedTab !== 'examples' && button.value === selectedType;
            button.classList.toggle('is-active', active);
            if (active) button.setAttribute('aria-current', 'true');
            else button.removeAttribute('aria-current');
        });
        form.querySelectorAll('.dictionary-types button[name="tab"]').forEach(button => {
            const active = button.value === selectedTab;
            button.classList.toggle('is-active', active);
            if (active) button.setAttribute('aria-current', 'true');
            else button.removeAttribute('aria-current');
        });
    });
}
