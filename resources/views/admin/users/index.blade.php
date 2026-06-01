@extends('layouts.admin', ['title' => 'Usuarios'])

@section('content')
    <section class="admin-card">
        <form method="GET" class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="label" for="buscar">Buscar</label>
                <input id="buscar" name="buscar" value="{{ $filters['buscar'] }}" class="input">
            </div>
            <div>
                <label class="label" for="estado">Estado</label>
                <select id="estado" name="estado" class="input">
                    <option value="">Todos</option>
                    <option value="activos" @selected($filters['estado'] === 'activos')>Activos</option>
                    <option value="inactivos" @selected($filters['estado'] === 'inactivos')>Inactivos</option>
                </select>
            </div>
            <div class="mt-8 flex gap-3">
                <button class="btn btn-primary">Filtrar</button>
                <a href="{{ route('web.admin.users.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="table-shell mt-6">
        <table class="table-ui">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Contacto</th>
                    <th>Distrito</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <p class="font-semibold text-stone-900">{{ $user->nombre }} {{ $user->apellido }}</p>
                            <p class="text-xs text-stone-500">#{{ $user->id }}</p>
                        </td>
                        <td>
                            <p>{{ $user->email }}</p>
                            <p class="text-xs text-stone-500">{{ $user->telefono ?: 'Sin telefono' }}</p>
                        </td>
                        <td>{{ $user->distrito ?: 'Sin distrito' }}</td>
                        <td><span class="badge {{ !empty($user->activo) ? 'badge-accent' : 'badge-surface' }}">{{ !empty($user->activo) ? 'Activo' : 'Inactivo' }}</span></td>
                        <td>
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('web.admin.users.show', $user->id) }}" class="btn btn-outline-secondary">Ver</a>
                                <form action="{{ route('web.admin.users.toggle', $user->id) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-outline-secondary">{{ !empty($user->activo) ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-10 text-center text-stone-500">No hay usuarios para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @include('shared.pagination', ['pagination' => $pagination])
@endsection
