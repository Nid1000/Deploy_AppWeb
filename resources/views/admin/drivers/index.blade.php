@extends('layouts.admin', ['title' => 'Conductores'])

@section('content')
    @php
        $stateLabels = [
            'sin_salida' => 'Sin salida',
            'en_ruta' => 'En ruta',
            'retornado' => 'Retornado',
        ];
        $stateBadges = [
            'sin_salida' => 'badge-surface',
            'en_ruta' => 'badge-warning',
            'retornado' => 'badge-accent',
        ];
        $dateTimeValue = function ($value) {
            $value = trim((string) $value);
            return $value !== '' ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
        };
    @endphp

    <section class="grid gap-4 md:grid-cols-4">
        <article class="admin-card">
            <p class="text-sm text-stone-500">Pedidos programados</p>
            <p class="mt-2 text-3xl font-semibold text-stone-950">{{ $metrics['programados'] }}</p>
        </article>
        <article class="admin-card">
            <p class="text-sm text-stone-500">Sin salida</p>
            <p class="mt-2 text-3xl font-semibold text-stone-950">{{ $metrics['sin_salida'] }}</p>
        </article>
        <article class="admin-card">
            <p class="text-sm text-stone-500">En ruta</p>
            <p class="mt-2 text-3xl font-semibold text-stone-950">{{ $metrics['en_ruta'] }}</p>
        </article>
        <article class="admin-card">
            <p class="text-sm text-stone-500">Retornados</p>
            <p class="mt-2 text-3xl font-semibold text-stone-950">{{ $metrics['retornados'] }}</p>
        </article>
    </section>

    <section class="admin-card mt-6">
        <form method="GET" class="grid gap-4 md:grid-cols-5">
            <div>
                <label class="label" for="buscar">Buscar</label>
                <input id="buscar" name="buscar" value="{{ $filters['buscar'] }}" class="input" placeholder="Pedido, conductor, DNI">
            </div>
            <div>
                <label class="label" for="estado_control">Control</label>
                <select id="estado_control" name="estado_control" class="input">
                    <option value="">Todos</option>
                    @foreach ($stateLabels as $key => $label)
                        <option value="{{ $key }}" @selected($filters['estado_control'] === $key)>{{ $label }}</option>
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
                <a href="{{ route('web.admin.drivers.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="table-shell mt-6">
        <table class="table-ui">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Entrega</th>
                    <th>Conductor</th>
                    <th>Horario</th>
                    <th>Control</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($orders as $order)
                    @php
                        $state = !empty($order->regreso_reparto_at ?? null)
                            ? 'retornado'
                            : (!empty($order->salida_reparto_at ?? null) ? 'en_ruta' : 'sin_salida');
                    @endphp
                    <tr>
                        <td>
                            <p class="font-semibold text-stone-900">#{{ $order->id }}</p>
                            <p class="text-xs text-stone-500">{{ data_get($order, 'usuario.nombre') }} {{ data_get($order, 'usuario.apellido') }}</p>
                        </td>
                        <td>
                            <p>{{ $order->direccion_entrega ?: 'Sin direccion' }}</p>
                            <p class="text-xs text-stone-500">{{ $order->fecha_entrega ?: 'Sin fecha' }}</p>
                        </td>
                        <td>
                            <p class="font-medium text-stone-900">{{ ($order->conductor ?? '') ?: 'Sin conductor' }}</p>
                            <p class="text-xs text-stone-500">DNI {{ ($order->conductor_dni ?? '') ?: 'pendiente' }}</p>
                            <p class="text-xs text-stone-500">{{ ($order->vehiculo ?? '') ?: 'Sin vehiculo' }}</p>
                        </td>
                        <td>
                            <p class="text-sm">Salida: {{ ($order->salida_reparto_at ?? '') ?: 'Pendiente' }}</p>
                            <p class="text-sm">Ingreso: {{ ($order->regreso_reparto_at ?? '') ?: 'Pendiente' }}</p>
                        </td>
                        <td>
                            <span class="badge {{ $stateBadges[$state] ?? 'badge-surface' }}">{{ $stateLabels[$state] ?? 'Sin salida' }}</span>
                        </td>
                        <td class="min-w-[320px]">
                            <form action="{{ route('web.admin.drivers.update', $order->id) }}" method="POST" class="grid gap-2">
                                @csrf
                                <div class="grid gap-2 md:grid-cols-2">
                                    <input name="conductor" value="{{ old('conductor', $order->conductor ?? '') }}" class="input" placeholder="Conductor">
                                    <input name="conductor_dni" value="{{ old('conductor_dni', $order->conductor_dni ?? '') }}" class="input" inputmode="numeric" maxlength="8" placeholder="DNI">
                                </div>
                                <div class="grid gap-2 md:grid-cols-2">
                                    <input name="salida_reparto_at" type="datetime-local" value="{{ old('salida_reparto_at', $dateTimeValue($order->salida_reparto_at ?? null)) }}" class="input">
                                    <input name="regreso_reparto_at" type="datetime-local" value="{{ old('regreso_reparto_at', $dateTimeValue($order->regreso_reparto_at ?? null)) }}" class="input">
                                </div>
                                <div class="flex gap-2">
                                    <input name="vehiculo" value="{{ old('vehiculo', $order->vehiculo ?? '') }}" class="input" placeholder="Vehiculo">
                                    <button class="btn btn-primary shrink-0">Guardar</button>
                                    <a href="{{ route('web.admin.orders.show', $order->id) }}" class="btn btn-outline-secondary shrink-0">Ver</a>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-stone-500">No hay pedidos para el control de conductores.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
