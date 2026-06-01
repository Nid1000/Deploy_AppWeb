<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Delicias Bakery' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logos/logo 1.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logos/logo 1.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|poppins:400,600,700" rel="stylesheet" />
    <script>
        (() => {
            const savedTheme = localStorage.getItem('delicias-theme');
            const theme = savedTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.classList.add(theme === 'dark' ? 'theme-dark' : 'theme-light');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront-body">
    <div class="storefront-bg" aria-hidden="true">
        <div class="storefront-glow storefront-glow-left"></div>
        <div class="storefront-glow storefront-glow-right"></div>
    </div>

    <header class="navbar-shell">
        <div class="topbar">
            <div class="container flex h-9 items-center justify-between gap-4 text-xs text-stone-700">
                <div class="flex flex-wrap items-center gap-3">
                    <span>Envio gratis , pero te tienes que ir a la mrd</span>
                    <a href="tel:993560096" class="hover:text-stone-950">993560096</a>
                </div>
                <div class="hidden items-center gap-3 sm:flex">
                    <a href="{{ route('web.home') }}#contacto" class="hover:text-stone-950">Contactanos</a>
                    <a href="{{ route('web.products') }}" class="hover:text-stone-950">Ordenar ahora</a>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="navbar-main">
                <a href="{{ route('web.home') }}" class="navbar-brand">
                    <img src="{{ asset('images/logos/logo 1.png') }}" alt="Delicias" class="h-12 w-12 rounded-[1.25rem] object-cover ring-1 ring-amber-200 shadow-md shadow-amber-100/50">
                    <div>
                        <h1 class="text-xl font-semibold text-stone-900">Delicias del Centro</h1>
                    </div>
                </a>

                <nav class="hidden items-center gap-6 text-sm lg:flex">
                    <a href="{{ route('web.home') }}" class="hover:text-[var(--color-secondary)]">Inicio</a>
                    <a href="{{ route('web.products') }}" class="hover:text-[var(--color-secondary)]">Menu</a>
                    <a href="{{ route('web.home') }}#nosotros" class="hover:text-[var(--color-secondary)]">Nosotros</a>
                    <a href="{{ route('web.home') }}#contacto" class="hover:text-[var(--color-secondary)]">Contactanos</a>
                </nav>

                <div class="navbar-actions">
                    <form action="{{ route('web.products') }}" method="GET" class="search-shell">
                        @if (request()->filled('categoria'))
                            <input type="hidden" name="categoria" value="{{ request('categoria') }}">
                        @endif
                        <input
                            type="search"
                            name="buscar"
                            value="{{ request('buscar') }}"
                            placeholder="Buscar productos..."
                            class="search-input"
                            aria-label="Buscar productos"
                        >
                        <button type="submit" class="search-button" aria-label="Buscar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="6.5" />
                                <path d="m20 20-3.5-3.5" />
                            </svg>
                        </button>
                    </form>

                    <a href="{{ route('web.products') }}" class="btn btn-order hidden xl:inline-flex">Ordenar Ahora</a>

                    <a href="{{ route('web.checkout') }}" class="cart-icon-pill" aria-label="Carrito">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="9" cy="20" r="1.2" />
                            <circle cx="18" cy="20" r="1.2" />
                            <path d="M2.5 3.5h2.3l2 9.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8l1.5-6.6H6.1" />
                        </svg>
                        @if (($storefrontCartCount ?? 0) > 0)
                            <span class="cart-pill-count">{{ $storefrontCartCount }}</span>
                        @endif
                    </a>

                    <button type="button" class="theme-icon-button" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
                        <span aria-hidden="true">🌙</span>
                    </button>

                    @if ($storefrontUser)
                        <details class="relative">
                            <summary class="cart-icon-pill list-none" aria-label="Notificaciones">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M15 17H5a1 1 0 0 1-.8-1.6l1.3-1.7V10a5.5 5.5 0 1 1 11 0v3.7l1.3 1.7A1 1 0 0 1 17 17h-2" />
                                    <path d="M9 17a3 3 0 0 0 6 0" />
                                </svg>
                                @if (($storefrontNotificationsCount ?? 0) > 0)
                                    <span class="cart-pill-count">{{ $storefrontNotificationsCount }}</span>
                                @endif
                            </summary>

                            <div class="absolute right-0 z-50 mt-3 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-amber-100 bg-white p-4 shadow-xl">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-stone-900">Notificaciones</p>
                                        <p class="text-xs text-stone-500">Avisos para tu cuenta</p>
                                    </div>
                                    @if (($storefrontNotificationsCount ?? 0) > 0)
                                        <form action="{{ route('web.notifications.seen') }}" method="POST">
                                            @csrf
                                            @foreach (($storefrontNotifications ?? collect()) as $notification)
                                                <input type="hidden" name="ids[]" value="{{ $notification->id }}">
                                            @endforeach
                                            <button type="submit" class="text-xs font-medium text-[var(--color-secondary)]">Marcar vistas</button>
                                        </form>
                                    @endif
                                </div>

                                <div class="mt-4 space-y-3">
                                    @forelse (($storefrontNotifications ?? collect()) as $notification)
                                        <article class="rounded-2xl border border-amber-100 bg-amber-50/60 p-3">
                                            <p class="text-sm font-semibold text-stone-900">{{ $notification->title }}</p>
                                            <p class="mt-1 text-sm text-stone-600">{{ $notification->body }}</p>
                                            @if ($notification->createdAt)
                                                <p class="mt-2 text-xs text-stone-500">{{ $notification->createdAt->format('d/m/Y H:i') }}</p>
                                            @endif
                                        </article>
                                    @empty
                                        <p class="text-sm text-stone-500">No hay notificaciones pendientes.</p>
                                    @endforelse
                                </div>
                            </div>
                        </details>
                        <a href="{{ route('web.orders') }}" class="hidden text-sm font-medium text-stone-700 transition hover:text-[var(--color-secondary)] xl:inline-flex">
                            Historial
                        </a>
                        <a href="{{ route('web.profile') }}" class="hidden rounded-full border border-amber-200 bg-white/90 px-4 py-2 font-medium text-stone-700 shadow-sm xl:inline-flex">
                            Mi cuenta
                        </a>
                        <form action="{{ route('web.logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Salir</button>
                        </form>
                    @else
                        <a href="{{ route('web.login') }}" class="text-sm font-medium text-stone-700 transition hover:text-[var(--color-secondary)]">Mi cuenta</a>
                        <a href="{{ route('web.register') }}" class="text-sm font-medium text-stone-700 transition hover:text-[var(--color-secondary)]">Registro</a>
                    @endif
                </div>
            </div>
        </div>

        @if (($storefrontCategories ?? collect())->count() > 0)
            <div class="navbar-categories">
                <div class="container flex gap-3 overflow-x-auto py-3">
                    @foreach ($storefrontCategories as $category)
                        <a href="{{ route('web.products', ['categoria' => $category->id]) }}" class="category-pill">
                            {{ $category->nombre }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </header>

    <main class="container pb-16 pt-8">
        @if (session('success'))
            <div class="flash-success mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="flash-error mb-6">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <div>
                <h3 class="footer-title">Panaderia Delicias</h3>
                <p class="footer-copy">
                    Pan artesanal, dulces y tortas con ingredientes de primera calidad.
                </p>
            </div>
            <div>
                <h4 class="footer-subtitle">Contacto</h4>
                <ul class="footer-list">
                    <li class="footer-item">
                        <span class="footer-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 21s6-5.5 6-11a6 6 0 1 0-12 0c0 5.5 6 11 6 11Z" />
                                <circle cx="12" cy="10" r="2.2" />
                            </svg>
                        </span>
                        <span>Jr. Parra del Riego #164, El Tambo, Huancayo</span>
                    </li>
                    <li class="footer-item">
                        <span class="footer-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7l.4 2.6a2 2 0 0 1-.6 1.8l-1.2 1.2a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 1.8-.6l2.6.4A2 2 0 0 1 22 16.9Z" />
                            </svg>
                        </span>
                        <a href="tel:993560096">993560096</a>
                    </li>
                    <li class="footer-item">
                        <span class="footer-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 6h16v12H4z" />
                                <path d="m4 7 8 6 8-6" />
                            </svg>
                        </span>
                        <a href="mailto:deliciasdelcentro@gmail.com">deliciasdelcentro@gmail.com</a>
                    </li>
                    <li class="footer-item">
                        <span class="footer-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="8" />
                                <path d="M12 8v4l2.5 2.5" />
                            </svg>
                        </span>
                        <span>Horarios: Lunes a Domingo, 7:00 AM - 9:00 PM</span>
                    </li>
                </ul>
            </div>
            <div>
                <h4 class="footer-subtitle">Siguenos</h4>
                <div class="footer-socials">
                    <a href="https://www.instagram.com/delicias_delcentro/?hl=es" class="social-link" target="_blank" rel="noopener noreferrer">
                        <span class="footer-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="3.5" y="3.5" width="17" height="17" rx="4" />
                                <circle cx="12" cy="12" r="4" />
                                <circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none" />
                            </svg>
                        </span>
                        Instagram
                    </a>
                    <a href="https://www.facebook.com/deliciashuancayoperu/?locale=es_LA" class="social-link" target="_blank" rel="noopener noreferrer">
                        <span class="footer-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M13.5 21v-8h2.7l.4-3h-3.1V8.1c0-.9.3-1.5 1.6-1.5H17V3.9c-.3 0-1.3-.1-2.5-.1-2.5 0-4.1 1.5-4.1 4.3V10H7.7v3h2.7v8h3.1Z" />
                            </svg>
                        </span>
                        Facebook
                    </a>
                </div>
            </div>
        </div>
        <div class="border-t border-black/10">
            <div class="container flex flex-col gap-3 py-4 text-sm text-stone-600 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} Delicias. Todos los derechos reservados.</p>
                <div class="footer-bottom-links">
                    <a href="#">Terminos</a>
                    <a href="#">Privacidad</a>
                    <a href="{{ route('web.home') }}#contacto">Contacto</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
