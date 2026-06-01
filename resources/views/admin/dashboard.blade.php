@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
    <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <article class="stat-card">
            <p class="text-sm text-stone-500">Productos activos</p>
            <p class="mt-3 text-3xl font-semibold text-stone-950">{{ $metrics['productos'] }}</p>
        </article>
        <article class="stat-card">
            <p class="text-sm text-stone-500">Categorias</p>
            <p class="mt-3 text-3xl font-semibold text-stone-950">{{ $metrics['categorias'] }}</p>
        </article>
        <article class="stat-card">
            <p class="text-sm text-stone-500">Usuarios</p>
            <p class="mt-3 text-3xl font-semibold text-stone-950">{{ $metrics['usuarios'] }}</p>
        </article>
        <article class="stat-card">
            <p class="text-sm text-stone-500">Ventas 7 dias</p>
            <p class="mt-3 text-3xl font-semibold text-stone-950">S/ {{ number_format($metrics['ventasSemana'], 2) }}</p>
        </article>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <article class="admin-card">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-stone-500">Serie semanal</p>
                    <h3 class="mt-1 text-2xl font-semibold text-stone-950">Ventas recientes</h3>
                </div>
                <a href="{{ route('web.admin.reports.index') }}" class="btn btn-outline-secondary">Ver reportes</a>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($salesSeries as $point)
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="text-stone-600">{{ $point->fecha }}</span>
                            <strong class="text-stone-900">S/ {{ number_format($point->total, 2) }}</strong>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-stone-100">
                            <div class="h-full rounded-full bg-amber-500" style="width: {{ min(100, $metrics['ventasSemana'] > 0 ? ($point->total / $metrics['ventasSemana']) * 100 : 0) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Aun no hay ventas registradas en este periodo.</p>
                @endforelse
            </div>
        </article>

        <article class="admin-card">
            <p class="text-sm text-stone-500">Comprobantes</p>
            <h3 class="mt-1 text-2xl font-semibold text-stone-950">Resumen de emision</h3>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-[1.5rem] bg-amber-50 p-5">
                    <p class="text-sm text-amber-900">Boletas</p>
                    <p class="mt-2 text-3xl font-semibold text-stone-950">{{ $receiptTypes['boleta'] }}</p>
                </div>
                <div class="rounded-[1.5rem] bg-orange-50 p-5">
                    <p class="text-sm text-orange-900">Facturas</p>
                    <p class="mt-2 text-3xl font-semibold text-stone-950">{{ $receiptTypes['factura'] }}</p>
                </div>
            </div>
        </article>
    </section>
@endsection
