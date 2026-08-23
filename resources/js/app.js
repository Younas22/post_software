import './bootstrap';

// All interactive chrome (theme, sidebar drawer/collapse, dropdowns, modals)
// is handled with plain delegated listeners rather than a JS framework, to
// keep the admin UI dependency-free — state lives in a class on <html> or
// `hidden` toggles, persisted to localStorage only where it should survive
// a reload (theme, sidebar collapse).

function setLocalStorage(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch (e) {
        // Storage may be unavailable (private browsing, disabled cookies) —
        // the toggle still works for the current page view.
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => menu.classList.add('hidden'));
}

function closeModal(modal) {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

document.addEventListener('click', (event) => {
    // Theme toggle (☀ / 🌙)
    const themeToggle = event.target.closest('[data-theme-toggle]');
    if (themeToggle) {
        const isDark = document.documentElement.classList.toggle('dark');
        setLocalStorage('theme', isDark ? 'dark' : 'light');
        return;
    }

    // Mobile/tablet sidebar drawer
    if (event.target.closest('[data-drawer-toggle]')) {
        document.documentElement.classList.toggle('admin-drawer-open');
        return;
    }
    if (event.target.closest('[data-drawer-close]') || event.target.closest('[data-sidebar-backdrop]')) {
        document.documentElement.classList.remove('admin-drawer-open');
        return;
    }

    // Desktop sidebar collapse (icon rail)
    if (event.target.closest('[data-sidebar-collapse-toggle]')) {
        const collapsed = document.documentElement.classList.toggle('admin-sidebar-collapsed');
        setLocalStorage('admin-sidebar-collapsed', collapsed ? '1' : '0');
        return;
    }

    // Generic dropdown (profile menu, notifications, etc.)
    const dropdownTrigger = event.target.closest('[data-dropdown-trigger]');
    if (dropdownTrigger) {
        const dropdown = dropdownTrigger.closest('[data-dropdown]');
        const menu = dropdown?.querySelector('[data-dropdown-menu]');
        const wasOpen = menu && !menu.classList.contains('hidden');
        closeAllDropdowns();
        if (menu && !wasOpen) {
            menu.classList.remove('hidden');
        }
        return;
    }
    if (!event.target.closest('[data-dropdown-menu]')) {
        closeAllDropdowns();
    }

    // Generic modal (used by confirm dialogs, etc.)
    const modalOpener = event.target.closest('[data-modal-open]');
    if (modalOpener) {
        const modal = document.getElementById(modalOpener.getAttribute('data-modal-open'));
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        return;
    }
    if (event.target.closest('[data-modal-close]') || event.target.closest('[data-modal-backdrop]')) {
        closeModal(event.target.closest('[data-modal]'));
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    document.documentElement.classList.remove('admin-drawer-open');
    closeAllDropdowns();
    document.querySelectorAll('[data-modal]:not(.hidden)').forEach(closeModal);
});
