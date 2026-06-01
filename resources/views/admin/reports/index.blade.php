@extends('layouts.admin', ['title' => 'Reportes'])

@section('content')
    <section class="admin-card">
        <form method="GET" class="grid gap-4 md:grid-cols-4">
            <div>
                <label class="label" for="modo">Modo</label>
                <select id="modo" name="modo" class="input">
                    <option value="diario" @selected($mode === 'diario')>Diario</option>
                    <option value="semanal" @selected($mode === 'semanal')>Semanal</option>
                    <option value="mensual" @selected($mode === 'mensual')>Mensual</option>
                </select>
            </div>
            <div>
                <label class="label" for="desde">Desde</label>
                <input id="desde" name="desde" type="date" value="{{ $from }}" class="input">
            </div>
            <div>
                <label class="label" for="hasta">Hasta</label>
                <input id="hasta" name="hasta" type="date" value="{{ $to }}" class="input">
            </div>
            <div class="mt-8 flex gap-3">
                <button class="btn btn-primary">Aplicar</button>
                <a href="{{ route('web.admin.reports.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="admin-card mt-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="eyebrow">Semana 4</p>
                <h3 class="mt-2 text-2xl font-semibold text-stone-950">Reportes con data para Excel</h3>
                <p class="mt-1 text-sm text-stone-600">Descarga ventas, pedidos y productos vendidos segun el rango de fecha aplicado.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('web.admin.reports.export', array_merge(['tipo' => 'ventas'], request()->only(['modo', 'desde', 'hasta']))) }}" class="btn btn-outline-secondary">Excel ventas</a>
                <a href="{{ route('web.admin.reports.export', array_merge(['tipo' => 'pedidos'], request()->only(['desde', 'hasta']))) }}" class="btn btn-outline-secondary">Excel pedidos</a>
                <a href="{{ route('web.admin.reports.export', array_merge(['tipo' => 'productos'], request()->only(['desde', 'hasta']))) }}" class="btn btn-outline-secondary">Excel productos</a>
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <article class="admin-card">
            <h3 class="text-2xl font-semibold text-stone-950">Serie de ventas</h3>
            <div class="mt-6 space-y-4">
                @forelse ($series as $point)
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="text-stone-600">{{ $point->label }}</span>
                            <strong class="text-stone-900">S/ {{ number_format($point->total, 2) }}</strong>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-stone-100">
                            <div class="h-full rounded-full bg-stone-900" style="width: {{ min(100, $series->max('total') > 0 ? ($point->total / $series->max('total')) * 100 : 0) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">No hay datos para este rango.</p>
                @endforelse
            </div>
        </article>

        <div class="space-y-6">
            <article class="admin-card">
                <h3 class="text-2xl font-semibold text-stone-950">Top productos</h3>
                <div class="mt-5 space-y-3">
                    @forelse ($topProducts as $product)
                        <div class="flex items-center gap-3 rounded-[1.25rem] border border-stone-200 p-3">
                            <img src="{{ $product->imagen_url }}" alt="{{ $product->nombre }}" class="h-14 w-14 rounded-2xl object-cover">
                            <div class="flex-1">
                                <p class="font-semibold text-stone-900">{{ $product->nombre }}</p>
                                <p class="text-sm text-stone-500">Vendidos: {{ $product->cantidad ?? 0 }}</p>
                            </div>
                            <strong class="text-stone-900">S/ {{ number_format((float) ($product->subtotal ?? 0), 2) }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-stone-500">Sin datos.</p>
                    @endforelse
                </div>
            </article>

            <article class="admin-card">
                <h3 class="text-2xl font-semibold text-stone-950">Top categorias</h3>
                <div class="mt-5 space-y-3">
                    @forelse ($topCategories as $category)
                        <div class="flex items-center justify-between rounded-[1.25rem] border border-stone-200 p-4">
                            <div>
                                <p class="font-semibold text-stone-900">{{ $category->nombre }}</p>
                                <p class="text-sm text-stone-500">Vendidos: {{ $category->cantidad }}</p>
                            </div>
                            <strong class="text-stone-900">S/ {{ number_format((float) $category->subtotal, 2) }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-stone-500">Sin datos.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </section>
@endsection
