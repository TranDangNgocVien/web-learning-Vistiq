import './confirmation-dialog.js';
import './ui-feedback.js';
import './exam-runner.js';
import './exam-start.js';
import './personal-library.js';
import { initializeDictionarySearch, syncDictionarySearchFilters } from './dictionary-search.js';
import { initializeAjaxFilters } from './ajax-filters.js';

initializeDictionarySearch();
initializeAjaxFilters();

const mobileNavigationButton = document.querySelector('.user-topbar .mobile-menu');
const mobileNavigation = document.getElementById('user-main-navigation');

if (mobileNavigationButton && mobileNavigation) {
    const closeMobileNavigation = () => {
        mobileNavigation.classList.remove('is-open');
        mobileNavigationButton.setAttribute('aria-expanded', 'false');
        mobileNavigationButton.setAttribute('aria-label', 'Mở điều hướng');
    };

    mobileNavigationButton.addEventListener('click', () => {
        const isOpen = mobileNavigationButton.getAttribute('aria-expanded') === 'true';

        mobileNavigation.classList.toggle('is-open', !isOpen);
        mobileNavigationButton.setAttribute('aria-expanded', String(!isOpen));
        mobileNavigationButton.setAttribute('aria-label', isOpen ? 'Mở điều hướng' : 'Đóng điều hướng');
    });

    mobileNavigation.addEventListener('click', event => {
        if (event.target instanceof Element && event.target.closest('a')) closeMobileNavigation();
    });

    document.addEventListener('click', event => {
        if (event.target instanceof Node && !mobileNavigationButton.closest('.user-topbar')?.contains(event.target)) {
            closeMobileNavigation();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && mobileNavigationButton.getAttribute('aria-expanded') === 'true') {
            closeMobileNavigation();
            mobileNavigationButton.focus();
        }
    });
}

document.addEventListener('vistiq:ajax-results-updated', event => {
    event.detail.targets?.forEach(target => initializeDictionarySearch(target));
    if (event.detail.targets?.some(target => target.id === 'dictionary-search-results')) {
        syncDictionarySearchFilters(event.detail.url);
    }
});

const examCatalog = document.querySelector('[data-exam-catalog]');

if (examCatalog) {
    const filterForm = examCatalog.querySelector('[data-exam-filter-form]');
    const examTypeFilter = filterForm.querySelector('[data-exam-type-filter]');
    const syncExamTabs = () => examCatalog.querySelectorAll('[data-exam-tab]').forEach(tab => {
        const active = tab.dataset.examTab === examTypeFilter.value;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-pressed', String(active));
    });

    examCatalog.querySelectorAll('[data-exam-tab]').forEach(tab => {
        tab.addEventListener('click', () => {
            examTypeFilter.value = tab.dataset.examTab;
            syncExamTabs();
            filterForm.requestSubmit();
        });
    });

    document.addEventListener('vistiq:ajax-results-updated', event => {
        if (event.detail.targets?.some(target => target.id === 'exam-catalog-results')) syncExamTabs();
    });

    filterForm.querySelectorAll('[data-submit-on-change]').forEach(select => {
        select.addEventListener('change', () => filterForm.requestSubmit());
    });
}

document.addEventListener('change', (event) => {
    if (event.target.matches('.answer-option input[type="radio"]')) {
        const options = event.target.closest('.answer-options');

        options?.querySelectorAll('.answer-option').forEach((option) => {
            option.classList.toggle('is-picked', option.contains(event.target) && event.target.checked);
        });
    }
});

document.addEventListener('click', (event) => {
    const saveButton = event.target.closest('.save-heart, .save-button, .bookmark-button');

    if (saveButton) {
        event.preventDefault();
        saveButton.classList.toggle('is-saved');
        saveButton.setAttribute('aria-pressed', String(saveButton.classList.contains('is-saved')));

        if (saveButton.classList.contains('save-button')) {
            saveButton.innerHTML = saveButton.classList.contains('is-saved')
                ? '<svg class="dictionary-icon app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg> <span>Đã lưu vào kho kiến thức</span>'
                : '<svg class="dictionary-icon app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg> <span>Lưu vào kho kiến thức</span>';
        }
    }

    const passwordButton = event.target.closest('.password-input button, [data-password-toggle]');

    if (passwordButton) {
        const controlledInput = passwordButton.getAttribute('aria-controls');
        const passwordInput = controlledInput
            ? document.getElementById(controlledInput)
            : passwordButton.parentElement.querySelector('input');

        if (!passwordInput) {
            return;
        }

        const isVisible = passwordInput.type === 'text';
        const isNowVisible = !isVisible;

        passwordInput.type = isNowVisible ? 'text' : 'password';
        passwordButton.setAttribute('aria-label', isVisible ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
        passwordButton.setAttribute('aria-pressed', String(isNowVisible));
    }
});
