@extends('layouts.admin', ['title' => 'Reservas'])

@section('content')
    <section class="admin-card">
        <form method="GET" class="grid gap-4 md:grid-cols-6">
            <div>
                <label class="label" for="buscar">Buscar cliente</label>
                <input id="buscar" name="buscar" value="{{ $filters['buscar'] }}" class="input">
            </div>
            <div>
                <label class="label" for="estado">Estado</label>
                <select id="estado" name="estado" class="input">
                    <option value="">Todos</option>
                    @foreach (['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'asistio' => 'Asistio', 'cancelada' => 'Cancelada'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['estado'] === $value)>{{ $label }}</option>
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
            <div class="mt-8 flex gap-3 md:col-span-2">
                <button class="btn btn-primary">Filtrar</button>
                <a href="{{ route('web.admin.reservations.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                <a href="{{ route('web.admin.reservations.export', request()->query()) }}" class="btn btn-outline-secondary">Excel</a>
            </div>
        </form>
    </section>

    <section class="table-shell mt-6">
        <table class="table-ui">
            <thead>
                <tr>
                    <th>Reserva</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Personas</th>
                    <th>Estado</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($reservations as $reservation)
                    <tr>
                        <td>
                            <p class="font-semibold text-stone-900">#{{ $reservation->id }}</p>
                            <p class="text-xs text-stone-500">{{ $reservation->created_at }}</p>
                        </td>
                        <td>
                            <p>{{ trim(($reservation->cliente_nombre ?? '') . ' ' . ($reservation->cliente_apellido ?? '')) ?: 'Sin cliente' }}</p>
                            <p class="text-xs text-stone-500">{{ $reservation->cliente_email ?: $reservation->cliente_telefono }}</p>
                        </td>
                        <td>
                            <p>{{ $reservation->fecha_reserva }}</p>
                            <p class="text-xs text-stone-500">{{ $reservation->hora_reserva }}</p>
                        </td>
                        <td>{{ $reservation->cantidad_personas }}</td>
                        <td>
                            <form action="{{ route('web.admin.reservations.state', $reservation->id) }}" method="POST" class="flex gap-2">
                                @csrf
                                <select name="estado" class="input min-w-36">
                                    @foreach (['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'asistio' => 'Asistio', 'cancelada' => 'Cancelada'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($reservation->estado === $value) || ($reservation->estado === 'asistió' && $value === 'asistio'))>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-secondary">Guardar</button>
                            </form>
                        </td>
                        <td class="max-w-xs text-sm text-stone-600">{{ $reservation->notas ?: 'Sin notas' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-stone-500">No hay reservas en este filtro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @include('shared.pagination', ['pagination' => $pagination])
@endsection
