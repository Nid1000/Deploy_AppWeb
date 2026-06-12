@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
    @php
        $growth = (float) $metrics['crecimientoVentas'];
        $chartWidth = 720;
        $chartHeight = 250;
        $chartTop = 18;
        $chartBottom = 210;
        $chartLeft = 14;
        $chartRight = 706;
        $chartRange = $chartBottom - $chartTop;
        $chartStep = ($chartRight - $chartLeft) / max(1, $salesSeries->count() - 1);
        $chartPoints = $salesSeries->values()->map(function ($point, $index) use (
            $chartLeft,
            $chartStep,
            $chartBottom,
            $chartRange,
            $salesChartMax
        ) {
            return [
                'x' => $chartLeft + ($chartStep * $index),
                'y' => $chartBottom - (($point->total / $salesChartMax) * $chartRange),
                'point' => $point,
            ];
        });
        $polyline = $chartPoints->map(fn ($item) => $item['x'] . ',' . $item['y'])->implode(' ');
        $areaPoints = $chartLeft . ',' . $chartBottom . ' ' . $polyline . ' ' . $chartRight . ',' . $chartBottom;
        $statusTotal = max(1, $orderStatuses->sum('total'));
        $receiptBoletaPercent = $receiptTotal > 0 ? ($receiptTypes['boleta'] / $receiptTotal) * 100 : 0;
    @endphp

    <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-icon dashboard-stat-icon-amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3M8 12h8"/></svg>
            </div>
            <div>
                <p class="text-sm text-stone-500">Ventas últimos 7 días</p>
                <p class="mt-2 text-3xl font-semibold text-stone-950">S/ {{ number_format($metrics['ventasSemana'], 2) }}</p>
                <p class="mt-2 text-xs font-semibold {{ $growth >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $growth >= 0 ? '↑' : '↓' }} {{ number_format(abs($growth), 1) }}% frente a la semana anterior
                </p>
            </div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-icon dashboard-stat-icon-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 3h10v18H7z"/><path d="M10 7h4M10 11h4M10 15h4"/></svg>
            </div>
            <div>
                <p class="text-sm text-stone-500">Pedidos de hoy</p>
                <p class="mt-2 text-3xl font-semibold text-stone-950">{{ $metrics['pedidosHoy'] }}</p>
                <p class="mt-2 text-xs text-stone-500">{{ $metrics['pedidosPeriodo'] }} pedidos en los últimos 30 días</p>
            </div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-icon dashboard-stat-icon-green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="8" r="3"/><path d="M4 19a5 5 0 0 1 10 0M17 11a3 3 0 1 0 0-6M20 19a5 5 0 0 0-3-4"/></svg>
            </div>
            <div>
                <p class="text-sm text-stone-500">Clientes registrados</p>
                <p class="mt-2 text-3xl font-semibold text-stone-950">{{ $metrics['usuarios'] }}</p>
                <p class="mt-2 text-xs text-stone-500">{{ $metrics['productos'] }} productos en {{ $metrics['categorias'] }} categorías</p>
            </div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-icon dashboard-stat-icon-violet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg>
            </div>
            <div>
                <p class="text-sm text-stone-500">Ticket promedio</p>
                <p class="mt-2 text-3xl font-semibold text-stone-950">S/ {{ number_format($metrics['ticketPromedio'], 2) }}</p>
                <p class="mt-2 text-xs text-stone-500">Promedio de los últimos 14 días</p>
            </div>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1.55fr_0.75fr]">
        <article class="admin-card dashboard-chart-card">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-stone-500">Últimos 14 días</p>
                    <h3 class="mt-1 text-2xl font-semibold text-stone-950">Tendencia de ventas</h3>
                </div>
                <a href="{{ route('web.admin.reports.index') }}" class="btn btn-outline-secondary">Ver reportes</a>
            </div>

            <div class="dashboard-line-chart mt-6">
                <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="Ventas diarias de los últimos 14 días">
                    <defs>
                        <linearGradient id="sales-area" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#f59e0b" stop-opacity=".32"/>
                            <stop offset="100%" stop-color="#f59e0b" stop-opacity=".02"/>
                        </linearGradient>
                    </defs>
                    @foreach ([0, 1, 2, 3] as $line)
                        @php($gridY = $chartTop + (($chartRange / 3) * $line))
                        <line x1="{{ $chartLeft }}" x2="{{ $chartRight }}" y1="{{ $gridY }}" y2="{{ $gridY }}" class="dashboard-chart-grid"/>
                    @endforeach
                    <polygon points="{{ $areaPoints }}" fill="url(#sales-area)"/>
                    <polyline points="{{ $polyline }}" class="dashboard-chart-line"/>
                    @foreach ($chartPoints as $item)
                        <g class="dashboard-chart-point">
                            <circle cx="{{ $item['x'] }}" cy="{{ $item['y'] }}" r="5"/>
                            <title>{{ $item['point']->fecha }}: S/ {{ number_format($item['point']->total, 2) }}</title>
                        </g>
                    @endforeach
                </svg>
                <div class="dashboard-chart-labels">
                    @foreach ($salesSeries as $index => $point)
                        @if ($index % 2 === 0 || $index === $salesSeries->count() - 1)
                            <span style="left: {{ ($index / max(1, $salesSeries->count() - 1)) * 100 }}%">{{ $point->label }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        </article>

        <article class="admin-card">
            <p class="text-sm text-stone-500">Operación</p>
            <h3 class="mt-1 text-2xl font-semibold text-stone-950">Estado de pedidos</h3>
            <div class="mt-6 space-y-4">
                @foreach ($orderStatuses as $status)
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="inline-flex items-center gap-2 text-stone-600">
                                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $status->color }}"></span>
                                {{ $status->label }}
                            </span>
                            <strong class="text-stone-900">{{ $status->total }}</strong>
                        </div>
                        <div class="dashboard-progress">
                            <span style="width: {{ ($status->total / $statusTotal) * 100 }}%; background: {{ $status->color }}"></span>
                        </div>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('web.admin.orders.index') }}" class="mt-6 inline-flex text-sm font-semibold text-amber-700">Gestionar pedidos →</a>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1fr_0.9fr_0.75fr]">
        <article class="admin-card">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-stone-500">Últimos movimientos</p>
                    <h3 class="mt-1 text-xl font-semibold text-stone-950">Pedidos recientes</h3>
                </div>
            </div>
            <div class="mt-5 space-y-3">
                @forelse ($recentOrders as $order)
                    @php
                        $orderStatus = match ($order->estado ?? '') {
                            'entregado' => ['Entregado', 'dashboard-status-green'],
                            'listo' => ['Listo', 'dashboard-status-blue'],
                            'cancelado' => ['Cancelado', 'dashboard-status-red'],
                            default => ['Pendiente', 'dashboard-status-amber'],
                        };
                        $customer = trim((string) data_get($order, 'usuario.nombre') . ' ' . (string) data_get($order, 'usuario.apellido'));
                    @endphp
                    <a href="{{ route('web.admin.orders.show', $order->id) }}" class="dashboard-order-row">
                        <div>
                            <p class="font-semibold text-stone-900">Pedido #{{ $order->id }}</p>
                            <p class="mt-1 text-xs text-stone-500">{{ $customer !== '' ? $customer : 'Cliente' }} · {{ $order->display_date ?: 'Sin fecha' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-stone-900">S/ {{ number_format((float) $order->total, 2) }}</p>
                            <span class="dashboard-status {{ $orderStatus[1] }}">{{ $orderStatus[0] }}</span>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-stone-500">Aún no hay pedidos recientes.</p>
                @endforelse
            </div>
        </article>

        <article class="admin-card">
            <p class="text-sm text-stone-500">Ranking últimos 30 días</p>
            <h3 class="mt-1 text-xl font-semibold text-stone-950">Productos más vendidos</h3>
            <div class="mt-5 space-y-4">
                @forelse ($topProducts as $index => $product)
                    <div class="dashboard-product-row">
                        <span class="dashboard-rank">{{ $index + 1 }}</span>
                        <img src="{{ $product->imagen_url }}" alt="{{ $product->nombre }}" class="h-11 w-11 rounded-xl object-cover">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <p class="truncate text-sm font-semibold text-stone-900">{{ $product->nombre }}</p>
                                <strong class="text-sm text-stone-900">{{ $product->cantidad ?? 0 }}</strong>
                            </div>
                            <div class="dashboard-progress mt-2">
                                <span class="bg-amber-500" style="width: {{ (($product->cantidad ?? 0) / $topProductMax) * 100 }}%"></span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Aún no hay productos vendidos en este periodo.</p>
                @endforelse
            </div>
        </article>

        <article class="admin-card">
            <p class="text-sm text-stone-500">Comprobantes emitidos</p>
            <h3 class="mt-1 text-xl font-semibold text-stone-950">Boletas y facturas</h3>
            <div class="mt-6 flex items-center justify-center">
                <div class="dashboard-donut {{ $receiptTotal === 0 ? 'dashboard-donut-empty' : '' }}" style="--donut-percent: {{ $receiptBoletaPercent }}%">
                    <div>
                        <strong>{{ $receiptTypes['boleta'] + $receiptTypes['factura'] }}</strong>
                        <span>Total</span>
                    </div>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <div class="dashboard-receipt-summary">
                    <span class="bg-amber-500"></span>
                    <p>Boletas</p>
                    <strong>{{ $receiptTypes['boleta'] }}</strong>
                </div>
                <div class="dashboard-receipt-summary">
                    <span class="bg-orange-700"></span>
                    <p>Facturas</p>
                    <strong>{{ $receiptTypes['factura'] }}</strong>
                </div>
            </div>
        </article>
    </section>
@endsection
