import './bootstrap';

const storageKey = 'delicias-theme';
const root = document.documentElement;

const applyTheme = (theme) => {
    root.classList.toggle('theme-dark', theme === 'dark');
    root.classList.toggle('theme-light', theme !== 'dark');

    document.querySelectorAll('[data-theme-label]').forEach((node) => {
        node.textContent = theme === 'dark' ? 'Claro' : 'Oscuro';
    });
};

const savedTheme = localStorage.getItem(storageKey);
const preferredTheme = savedTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
applyTheme(preferredTheme);

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-theme-toggle]');
    if (!button) {
        return;
    }

    const nextTheme = root.classList.contains('theme-dark') ? 'light' : 'dark';
    localStorage.setItem(storageKey, nextTheme);
    applyTheme(nextTheme);
});
