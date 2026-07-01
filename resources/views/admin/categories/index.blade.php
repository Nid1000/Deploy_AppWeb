@extends('layouts.admin', ['title' => 'Categorías'])

@section('content')
    <section class="admin-card">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="buscar" class="label">Buscar</label>
                    <input id="buscar" name="buscar" value="{{ $filters['buscar'] }}" class="input" placeholder="Nombre o descripcion">
                </div>
                <div>
                    <label for="activo" class="label">Estado</label>
                    <select id="activo" name="activo" class="input">
                        <option value="">Todas</option>
                        <option value="1" @selected($filters['activo'] === '1')>Activas</option>
                        <option value="0" @selected($filters['activo'] === '0')>Inactivas</option>
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <button class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('web.admin.categories.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>

            <a href="{{ route('web.admin.categories.create') }}" class="btn btn-primary">Nueva categoria</a>
        </div>
    </section>

    <section class="table-shell mt-6">
        <table class="table-ui">
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Descripcion</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($categories as $category)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <img src="{{ $category->imagen_url }}" alt="{{ $category->nombre }}" class="h-12 w-12 rounded-2xl object-cover">
                                <div>
                                    <p class="font-semibold text-stone-900">{{ $category->nombre }}</p>
                                    <p class="text-xs text-stone-500">#{{ $category->id }}</p>
                                </div>
                            </div>
                        </td>
                        <td>{{ $category->descripcion ?: 'Sin descripcion' }}</td>
                        <td>
                            <span class="badge {{ !empty($category->activo) ? 'badge-accent' : 'badge-surface' }}">
                                {{ !empty($category->activo) ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td>
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('web.admin.categories.edit', $category->id) }}" class="btn btn-outline-secondary">Editar</a>
                                <form action="{{ route('web.admin.categories.toggle', $category->id) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-outline-secondary">{{ !empty($category->activo) ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-stone-500">No hay categorias registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @include('shared.pagination', ['pagination' => $pagination])
@endsection
