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

const animateDashboard = () => {
    const dashboard = document.querySelector('[data-dashboard-animated]');
    if (!dashboard) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    dashboard.classList.add('dashboard-animation-ready');

    dashboard.querySelectorAll('[data-dashboard-line]').forEach((line) => {
        const length = line.getTotalLength();
        line.style.strokeDasharray = `${length}`;
        line.style.strokeDashoffset = reduceMotion ? '0' : `${length}`;
        line.style.transition = reduceMotion
            ? 'none'
            : 'stroke-dashoffset 1200ms cubic-bezier(0.2, 0.75, 0.2, 1) 420ms';

        requestAnimationFrame(() => {
            line.style.strokeDashoffset = '0';
        });
    });

    dashboard.querySelectorAll('[data-dashboard-progress]').forEach((bar, index) => {
        const target = Math.max(0, Math.min(100, Number(bar.dataset.dashboardProgress) || 0));
        bar.style.width = reduceMotion ? `${target}%` : '0';
        window.setTimeout(() => {
            bar.style.width = `${target}%`;
        }, reduceMotion ? 0 : 520 + (index * 90));
    });

    dashboard.querySelectorAll('[data-dashboard-donut]').forEach((donut) => {
        const target = Math.max(0, Math.min(100, Number(donut.dataset.dashboardDonut) || 0));
        if (reduceMotion) {
            donut.style.setProperty('--donut-percent', `${target}%`);
            return;
        }

        donut.style.setProperty('--donut-percent', '0%');
        const start = performance.now();
        const duration = 1100;
        const draw = (time) => {
            const progress = Math.min(1, (time - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            donut.style.setProperty('--donut-percent', `${target * eased}%`);
            if (progress < 1) {
                requestAnimationFrame(draw);
            }
        };
        requestAnimationFrame(draw);
    });

    dashboard.querySelectorAll('[data-dashboard-counter]').forEach((counter) => {
        const target = Number(counter.dataset.dashboardCounter) || 0;
        const decimals = Number(counter.dataset.counterDecimals) || 0;
        const prefix = counter.dataset.counterPrefix || '';
        if (reduceMotion) {
            return;
        }

        const start = performance.now();
        const duration = 900;
        const count = (time) => {
            const progress = Math.min(1, (time - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            counter.textContent = `${prefix}${(target * eased).toLocaleString('es-PE', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            })}`;
            if (progress < 1) {
                requestAnimationFrame(count);
            }
        };
        requestAnimationFrame(count);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', animateDashboard);
} else {
    animateDashboard();
}
