<nav class="account-nav" aria-label="Navegacion de cuenta">
    <a href="{{ route('web.profile') }}" class="account-nav-link @if (request()->routeIs('web.profile')) account-nav-link-active @endif">
        Mi cuenta
    </a>
    <a href="{{ route('web.orders') }}" class="account-nav-link @if (request()->routeIs('web.orders') || request()->routeIs('web.orders.show') || request()->routeIs('web.history')) account-nav-link-active @endif">
        Historial
    </a>
</nav>
