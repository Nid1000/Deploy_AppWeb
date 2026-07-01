<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin Delicias' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logos/logo 1.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|poppins:400,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell">
    @php
        $routeName = request()->route()?->getName();
        $adminName = (string) ($adminUser['nombre'] ?? 'Administrador');
        $adminEmail = (string) ($adminUser['email'] ?? 'admin@delicias.com');
        $adminInitial = strtoupper(substr($adminName, 0, 1) ?: 'A');
    @endphp
    <div class="flex min-h-screen">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-brand">
                <div class="admin-sidebar-avatar">{{ $adminInitial }}</div>
                <div>
                    <h1 class="admin-sidebar-title">Panel Admin</h1>
                    <p class="admin-sidebar-subtitle">{{ $adminEmail }}</p>
                </div>
            </div>

            <nav class="space-y-1">
                <a href="{{ route('web.admin.dashboard') }}" class="admin-sidebar-link {{ $routeName === 'web.admin.dashboard' ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 13h6V4H4zM14 20h6v-9h-6zM14 10h6V4h-6zM4 20h6v-3H4z"/></svg>
                    </span>
                    Dashboard
                </a>
                <a href="{{ route('web.admin.receipts.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.receipts') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h5"/><path d="M10 13h6M10 17h6M10 9h2"/></svg>
                    </span>
                    Comprobantes
                </a>
                <a href="{{ route('web.admin.reports.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.reports') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V9"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20h-18"/></svg>
                    </span>
                    Reportes
                </a>
                <a href="{{ route('web.admin.orders.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.orders') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="4" width="12" height="16" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                    </span>
                    Pedidos
                </a>
                <a href="{{ route('web.admin.drivers.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.drivers') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M6 11h5"/></svg>
                    </span>
                    Conductores
                </a>
                <a href="{{ route('web.admin.reservations.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.reservations') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/><path d="M8 14h3M13 14h3M8 17h3"/></svg>
                    </span>
                    Reservas
                </a>
                <a href="{{ route('web.admin.products.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.products') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 7 12 4l6 3v10l-6 3-6-3z"/><path d="M6 7l6 3 6-3"/></svg>
                    </span>
                    Productos
                </a>
                <a href="{{ route('web.admin.warehouse.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.warehouse') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 8 12 4l8 4-8 4z"/><path d="M4 12l8 4 8-4"/><path d="M4 16l8 4 8-4"/></svg>
                    </span>
                    Almacen
                </a>
                <a href="{{ route('web.admin.categories.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.categories') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7a3 3 0 0 1 3-3h10l3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3z"/></svg>
                    </span>
                    Categorías
                </a>
                <a href="{{ route('web.admin.users.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.users') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M4 19a5 5 0 0 1 10 0"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M20 19a5 5 0 0 0-3-4.6"/></svg>
                    </span>
                    Usuarios
                </a>
                <a href="{{ route('web.admin.settings.index') }}" class="admin-sidebar-link {{ str_starts_with((string) $routeName, 'web.admin.settings') ? 'admin-sidebar-link-active' : '' }}">
                    <span class="admin-sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.5h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.4 1Z"/></svg>
                    </span>
                    Configuracion
                </a>
            </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="container admin-topbar-inner">
                    <div class="admin-topbar-left">
                        <button type="button" class="admin-icon-button" aria-label="Menu">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                        </button>
                        <div>
                            <h2 class="text-2xl font-semibold text-stone-950">{{ $title ?? 'Panel de Administracion' }}</h2>
                        </div>
                    </div>

                    <div class="admin-topbar-right">
                        <form class="admin-search" action="#" method="GET">
                            <span class="admin-search-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/></svg>
                            </span>
                            <input type="search" placeholder="Buscar en" aria-label="Buscar en el panel">
                        </form>
                        <button type="button" class="admin-icon-button" data-theme-toggle aria-label="Tema" title="Cambiar tema">
                            <span aria-hidden="true">◔</span>
                        </button>
                        <details class="admin-notifications">
                            <summary class="admin-icon-button list-none" aria-label="Notificaciones">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 17H5a1 1 0 0 1-.8-1.6l1.3-1.7V10a5.5 5.5 0 1 1 11 0v3.7l1.3 1.7A1 1 0 0 1 17 17h-2"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>
                                @if (($adminNotificationsCount ?? 0) > 0)
                                    <span class="admin-notification-count">{{ $adminNotificationsCount }}</span>
                                @endif
                            </summary>

                            <div class="admin-notifications-panel">
                                <div class="admin-notifications-header">
                                    <div>
                                        <p class="text-sm font-semibold text-stone-900">Notificaciones</p>
                                        <p class="text-xs text-stone-500">Avisos enviados desde el panel admin</p>
                                    </div>
                                    @if (($adminNotificationsCount ?? 0) > 0)
                                        <form action="{{ route('web.admin.notifications.seen') }}" method="POST">
                                            @csrf
                                            @foreach (($adminNotifications ?? collect()) as $notification)
                                                <input type="hidden" name="ids[]" value="{{ $notification->id }}">
                                            @endforeach
                                            <button type="submit" class="text-xs font-medium text-[var(--color-secondary)]">Marcar vistas</button>
                                        </form>
                                    @endif
                                </div>

                                <div class="admin-notifications-list">
                                    @forelse (($adminNotifications ?? collect()) as $notification)
                                        <article class="admin-notification-item">
                                            <p class="admin-notification-title">{{ $notification->title }}</p>
                                            <p class="admin-notification-body">{{ $notification->body }}</p>
                                            @if ($notification->createdAt)
                                                <p class="admin-notification-time">{{ $notification->createdAt->format('d/m/Y H:i') }}</p>
                                            @endif
                                        </article>
                                    @empty
                                        <p class="text-sm text-stone-500">No hay notificaciones pendientes.</p>
                                    @endforelse
                                </div>
                            </div>
                        </details>
                        @if ($adminUser)
                            <div class="admin-user-pill">
                                <span class="admin-user-badge">{{ $adminInitial }}</span>
                                <span>{{ $adminName }}</span>
                                <span class="text-stone-400">⌄</span>
                            </div>
                            <form action="{{ route('web.admin.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="admin-icon-button" aria-label="Salir">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                                </button>
                            </form>
                        @else
                            <a href="{{ route('web.admin.login') }}" class="admin-user-pill">
                                <span class="admin-user-badge">{{ $adminInitial }}</span>
                                <span>Administrador</span>
                                <span class="text-stone-400">⌄</span>
                            </a>
                        @endif
                    </div>
                </div>
            </header>

            <main class="container py-8">
                @if (session('success'))
                    <div class="flash-success mb-6">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="flash-error mb-6">{{ session('error') }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
