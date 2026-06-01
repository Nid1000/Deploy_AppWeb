@extends('layouts.admin', ['title' => 'Pedidos'])

@section('content')
    <section class="admin-card">
        <form method="GET" class="grid gap-4 md:grid-cols-5">
            <div>
                <label class="label" for="buscar">Buscar</label>
                <input id="buscar" name="buscar" value="{{ $filters['buscar'] }}" class="input">
            </div>
            <div>
                <label class="label" for="estado">Estado</label>
                <select id="estado" name="estado" class="input">
                    <option value="">Todos</option>
                    @foreach (['pendiente', 'listo', 'entregado', 'cancelado'] as $estado)
                        <option value="{{ $estado }}" @selected($filters['estado'] === $estado)>{{ ucfirst($estado) }}</option>
                    @endforeach
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
                <a href="{{ route('web.admin.orders.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="table-shell mt-6">
        <table class="table-ui">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Entrega</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($orders as $order)
                    <tr>
                        <td>
                            <p class="font-semibold text-stone-900">#{{ $order->id }}</p>
                            <p class="text-xs text-stone-500">{{ $order->created_at }}</p>
                        </td>
                        <td>
                            <p>{{ data_get($order, 'usuario.nombre') }} {{ data_get($order, 'usuario.apellido') }}</p>
                            <p class="text-xs text-stone-500">{{ data_get($order, 'usuario.email', 'Sin email') }}</p>
                        </td>
                        <td>
                            <p>{{ $order->direccion_entrega ?: 'Sin direccion' }}</p>
                            <p class="text-xs text-stone-500">{{ $order->fecha_entrega ?: 'Sin fecha' }}</p>
                        </td>
                        <td>S/ {{ number_format((float) $order->total, 2) }}</td>
                        <td><span class="badge badge-surface">{{ ucfirst($order->estado) }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('web.admin.orders.show', $order->id) }}" class="btn btn-outline-secondary">Ver detalle</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-stone-500">No hay pedidos en este filtro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @include('shared.pagination', ['pagination' => $pagination])
@endsection
