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

const dashboard = document.querySelector('[data-dashboard-animated]');
if (dashboard) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const duration = reducedMotion ? 0 : 950;
    const easeOut = (value) => 1 - Math.pow(1 - value, 3);
    const formatValue = (value, decimals, prefix) => `${prefix}${Number(value).toLocaleString('es-PE', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    })}`;

    dashboard.querySelectorAll('[data-dashboard-progress]').forEach((bar) => {
        const target = Math.max(0, Math.min(100, Number(bar.dataset.dashboardProgress || 0)));
        bar.style.setProperty('--progress-width', `${target}%`);
    });

    dashboard.querySelectorAll('[data-dashboard-donut]').forEach((donut) => {
        const target = Math.max(0, Math.min(100, Number(donut.dataset.dashboardDonut || 0)));
        donut.style.setProperty('--donut-target', `${target}%`);
    });

    dashboard.querySelectorAll('[data-dashboard-line]').forEach((line) => {
        const length = typeof line.getTotalLength === 'function' ? line.getTotalLength() : 1400;
        line.style.setProperty('--line-length', length);
    });

    const animateCounters = () => {
        const start = performance.now();
        const counters = [...dashboard.querySelectorAll('[data-dashboard-counter]')].map((node) => ({
            node,
            target: Number(node.dataset.dashboardCounter || 0),
            prefix: node.dataset.counterPrefix || '',
            decimals: Number(node.dataset.counterDecimals || 0),
        }));

        const tick = (now) => {
            const progress = duration === 0 ? 1 : Math.min(1, (now - start) / duration);
            const eased = easeOut(progress);

            counters.forEach(({ node, target, prefix, decimals }) => {
                node.textContent = formatValue(target * eased, decimals, prefix);
            });

            dashboard.querySelectorAll('[data-dashboard-donut]').forEach((donut) => {
                const target = Math.max(0, Math.min(100, Number(donut.dataset.dashboardDonut || 0)));
                donut.style.setProperty('--donut-percent', `${target * eased}%`);
            });

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    };

    requestAnimationFrame(() => {
        dashboard.classList.add('dashboard-ready');
        animateCounters();
    });
}

const chatReplies = {
    horario: 'Atendemos de lunes a domingo, de 7:00 AM a 9:00 PM.',
    delivery: 'Si, hacemos delivery. Tambien puedes escribirnos o llamar al 993560096 para coordinar tu pedido.',
    pagos: 'Aceptamos pago contra entrega, Yape y tarjeta.',
    pedido: 'Puedes pedir desde la seccion Menu, agregar productos al carrito y finalizar tu compra en checkout.',
    contacto: 'Puedes contactarnos al 993560096 o al correo deliciasdelcentro@gmail.com.',
    torta: 'Si, realizamos tortas personalizadas. Puedes escribirnos por contacto y contarnos el diseno o sabor que deseas.',
    carrito: 'Si ya elegiste productos, revisa tu carrito y continua al checkout para completar el pedido.',
    checkout: 'En checkout debes confirmar direccion, fecha de entrega, metodo de pago y comprobante.',
    historial: 'En tu historial puedes revisar pedidos, comprobantes y el estado de cada compra.',
    default: 'Puedo ayudarte con horarios, delivery, pagos, pedidos y contacto. Si prefieres, usa el boton de WhatsApp.',
};

const createBubble = (text, type) => {
    const bubble = document.createElement('article');
    bubble.className = `chat-bubble ${type === 'user' ? 'chat-bubble-user' : 'chat-bubble-bot'}`;
    bubble.textContent = text;
    return bubble;
};

const resolveReply = (message) => {
    const normalized = message.toLowerCase();
    const page = document.body?.dataset.page ?? '';

    if (page === 'web.checkout' && (normalized.includes('ayuda') || normalized.includes('sigue') || normalized.includes('continuar'))) {
        return chatReplies.checkout;
    }
    if ((page === 'web.orders' || page === 'web.history') && (normalized.includes('pedido') || normalized.includes('historial') || normalized.includes('comprobante'))) {
        return chatReplies.historial;
    }
    if ((page === 'web.products' || page === 'web.products.show') && (normalized.includes('comprar') || normalized.includes('agregar') || normalized.includes('carrito'))) {
        return chatReplies.carrito;
    }

    if (normalized.includes('hora') || normalized.includes('horario') || normalized.includes('atienden')) {
        return chatReplies.horario;
    }
    if (normalized.includes('delivery') || normalized.includes('envio') || normalized.includes('domicilio')) {
        return chatReplies.delivery;
    }
    if (normalized.includes('pago') || normalized.includes('yape') || normalized.includes('tarjeta')) {
        return chatReplies.pagos;
    }
    if (normalized.includes('pedido') || normalized.includes('comprar') || normalized.includes('ordenar')) {
        return chatReplies.pedido;
    }
    if (normalized.includes('carrito')) {
        return chatReplies.carrito;
    }
    if (normalized.includes('contacto') || normalized.includes('telefono') || normalized.includes('correo')) {
        return chatReplies.contacto;
    }
    if (normalized.includes('torta') || normalized.includes('personalizada')) {
        return chatReplies.torta;
    }

    return chatReplies.default;
};

const chatRoot = document.querySelector('[data-chat-assistant]');
if (chatRoot) {
    const toggle = chatRoot.querySelector('[data-chat-toggle]');
    const close = chatRoot.querySelector('[data-chat-close]');
    const panel = chatRoot.querySelector('[data-chat-panel]');
    const form = chatRoot.querySelector('[data-chat-form]');
    const input = form?.querySelector('input[name="message"]');
    const messages = chatRoot.querySelector('[data-chat-messages]');

    const setOpen = (isOpen) => {
        panel?.classList.toggle('hidden', !isOpen);
        toggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            input?.focus();
        }
    };

    const appendConversation = (userMessage) => {
        if (!messages) {
            return;
        }

        messages.appendChild(createBubble(userMessage, 'user'));
        const reply = resolveReply(userMessage);
        messages.appendChild(createBubble(reply, 'bot'));
        messages.scrollTop = messages.scrollHeight;
    };

    toggle?.addEventListener('click', () => {
        const isClosed = panel?.classList.contains('hidden') ?? true;
        setOpen(isClosed);
    });

    close?.addEventListener('click', () => {
        setOpen(false);
    });

    chatRoot.querySelectorAll('[data-chat-question]').forEach((button) => {
        button.addEventListener('click', () => {
            const question = button.getAttribute('data-chat-question') ?? '';
            const label = button.textContent?.trim() || question;
            setOpen(true);
            appendConversation(label);
        });
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const value = input?.value.trim() ?? '';
        if (!value) {
            return;
        }

        appendConversation(value);
        if (input) {
            input.value = '';
        }
    });

    const page = document.body?.dataset.page ?? '';
    if (messages && page) {
        const welcomeByPage = {
            'web.products': 'Estas viendo el menu. Si quieres, te ayudo a encontrar productos o explicarte como comprar.',
            'web.products.show': 'Puedo ayudarte con este producto, el carrito o el proceso de compra.',
            'web.checkout': 'Estas en checkout. Si tienes dudas con pago, direccion o comprobante, preguntame aqui.',
            'web.orders': 'Desde aqui puedes revisar tus pedidos y comprobantes.',
            'web.history': 'Puedo ayudarte a entender tu historial de compras.',
        };

        if (welcomeByPage[page]) {
            messages.appendChild(createBubble(welcomeByPage[page], 'bot'));
        }
    }

    document.addEventListener('click', (event) => {
        if (!chatRoot.contains(event.target) && !(panel?.classList.contains('hidden') ?? true)) {
            setOpen(false);
        }
    });
}
