@extends('layouts.admin', ['title' => 'Movimiento de almacen'])

@section('content')
    <section class="grid gap-6 xl:grid-cols-[1fr_420px]">
        <div class="admin-card">
            <form method="GET" class="grid gap-4 md:grid-cols-5">
                <div>
                    <label class="label" for="producto_id">Producto</label>
                    <select id="producto_id" name="producto_id" class="input">
                        <option value="0">Todos</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected($filters['producto_id'] === (int) $product->id)>{{ $product->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="tipo_movimiento">Tipo</label>
                    <select id="tipo_movimiento" name="tipo_movimiento" class="input">
                        <option value="">Todos</option>
                        <option value="entrada" @selected($filters['tipo_movimiento'] === 'entrada')>Entrada</option>
                        <option value="salida" @selected($filters['tipo_movimiento'] === 'salida')>Salida</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="desde">Desde</label>
                    <input id="desde" name="desde" type="date" value="{{ $filters['desde'] }}" class="input">
                </div>
                <div>
                    <label class="label" for="hasta">Hasta</label>
                    <input id="hasta" name="hasta" type="date" value="{{ $filters['hasta'] }}" class="input">
                </div>
                <div class="mt-8 flex gap-3">
                    <button class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('web.admin.warehouse.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    <a href="{{ route('web.admin.warehouse.export', request()->query()) }}" class="btn btn-outline-secondary">Excel</a>
                </div>
            </form>
        </div>

        <div class="admin-card">
            <h3 class="mb-4 text-xl font-semibold text-stone-950">Nuevo movimiento</h3>
            <form action="{{ route('web.admin.warehouse.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="nuevo_producto_id">Producto</label>
                    <select id="nuevo_producto_id" name="producto_id" required class="input">
                        <option value="">Selecciona</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(old('producto_id') == $product->id)>{{ $product->nombre }} - Stock: {{ $product->stock }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="label" for="nuevo_tipo">Tipo</label>
                        <select id="nuevo_tipo" name="tipo_movimiento" required class="input">
                            <option value="entrada" @selected(old('tipo_movimiento') === 'entrada')>Entrada</option>
                            <option value="salida" @selected(old('tipo_movimiento') === 'salida')>Salida</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="cantidad">Cantidad</label>
                        <input id="cantidad" name="cantidad" type="number" min="1" value="{{ old('cantidad', 1) }}" required class="input">
                    </div>
                </div>
                <div>
                    <label class="label" for="motivo">Motivo</label>
                    <input id="motivo" name="motivo" value="{{ old('motivo') }}" placeholder="Compra, merma, venta, ajuste..." class="input">
                </div>
                <button class="btn btn-primary w-full">Registrar movimiento</button>
            </form>
        </div>
    </section>

    <section class="table-shell mt-6">
        <table class="table-ui">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Motivo</th>
                    <th>Admin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($movements as $movement)
                    <tr>
                        <td>{{ $movement->fecha }}</td>
                        <td>
                            <p class="font-semibold text-stone-900">{{ $movement->producto_nombre ?: 'Producto eliminado' }}</p>
                            <p class="text-xs text-stone-500">Stock actual: {{ $movement->producto_stock ?? '-' }}</p>
                        </td>
                        <td><span class="badge {{ $movement->tipo_movimiento === 'entrada' ? 'badge-accent' : 'badge-warning' }}">{{ ucfirst($movement->tipo_movimiento) }}</span></td>
                        <td>{{ $movement->cantidad }}</td>
                        <td>{{ $movement->motivo ?: 'Sin motivo' }}</td>
                        <td>{{ $movement->admin_nombre ?: 'Admin' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-stone-500">No hay movimientos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @include('shared.pagination', ['pagination' => $pagination])
@endsection
