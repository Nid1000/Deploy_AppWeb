@extends('layouts.admin', ['title' => 'Productos'])

@section('content')
    <section class="admin-card">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="label" for="buscar">Buscar</label>
                    <input id="buscar" name="buscar" value="{{ $filters['buscar'] }}" class="input">
                </div>
                <div>
                    <label class="label" for="categoria">Categoría</label>
                    <select id="categoria" name="categoria" class="input">
                        <option value="0">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['categoria'] === (int) $category->id)>{{ $category->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="checkbox-row mt-8">
                    <input type="checkbox" name="stock_bajo" value="1" @checked($filters['stock_bajo'])>
                    <span>Solo stock bajo</span>
                </label>
                <div class="mt-8 flex gap-3">
                    <button class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('web.admin.products.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>

            <a href="{{ route('web.admin.products.create') }}" class="btn btn-primary">Nuevo producto</a>
        </div>
    </section>

    <section class="table-shell mt-6">
        <table class="table-ui">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($products as $product)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <img src="{{ $product->imagen_url }}" alt="{{ $product->nombre }}" class="h-14 w-14 rounded-2xl object-cover">
                                <div>
                                    <p class="font-semibold text-stone-900">{{ $product->nombre }}</p>
                                    <p class="text-xs text-stone-500">#{{ $product->id }}</p>
                                </div>
                            </div>
                        </td>
                        <td>{{ $product->categoria_nombre ?: 'Sin categoria' }}</td>
                        <td>S/ {{ number_format($product->precio, 2) }}</td>
                        <td>{{ $product->stock }}</td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <span class="badge {{ !empty($product->destacado) ? 'badge-accent' : 'badge-surface' }}">{{ !empty($product->destacado) ? 'Destacado' : 'Normal' }}</span>
                                @if ((int) $product->stock <= 5)
                                    <span class="badge badge-warning">Stock bajo</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('web.admin.products.edit', $product->id) }}" class="btn btn-outline-secondary">Editar</a>
                                <form action="{{ route('web.admin.products.featured', $product->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="destacado_actual" value="{{ !empty($product->destacado) ? 1 : 0 }}">
                                    <button class="btn btn-outline-secondary">{{ !empty($product->destacado) ? 'Quitar' : 'Destacar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-stone-500">No hay productos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @include('shared.pagination', ['pagination' => $pagination])
@endsection
