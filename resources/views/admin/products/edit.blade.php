@extends('layouts.admin', ['title' => 'Editar producto'])

@section('content')
    <section class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <article class="admin-card">
            <form action="{{ route('web.admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                @csrf
                @method('PATCH')
                <div class="md:col-span-2">
                    <label class="label" for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" value="{{ old('nombre', $product->nombre) }}" required class="input">
                </div>
                <div>
                    <label class="label" for="precio">Precio</label>
                    <input id="precio" name="precio" type="number" step="0.01" min="0" value="{{ old('precio', $product->precio) }}" required class="input">
                </div>
                <div>
                    <label class="label" for="stock">Stock</label>
                    <input id="stock" name="stock" type="number" min="0" value="{{ old('stock', $product->stock) }}" required class="input">
                </div>
                <div>
                    <label class="label" for="categoria_id">Categoría</label>
                    <select id="categoria_id" name="categoria_id" required class="input">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('categoria_id', $product->categoria_id) === (int) $category->id)>{{ $category->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="imagen">Cambiar imagen</label>
                    <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="input">
                </div>
                <div class="md:col-span-2">
                    <label class="label" for="descripcion">Descripcion</label>
                    <textarea id="descripcion" name="descripcion" rows="5" class="input min-h-36">{{ old('descripcion', $product->descripcion) }}</textarea>
                </div>
                <label class="checkbox-row md:col-span-2">
                    <input type="checkbox" name="destacado" value="1" @checked(old('destacado', !empty($product->destacado)))>
                    <span>Producto destacado</span>
                </label>
                <button class="btn btn-primary md:col-span-2 w-fit">Guardar cambios</button>
            </form>
        </article>

        <article class="admin-card">
            <img src="{{ $product->imagen_url }}" alt="{{ $product->nombre }}" class="h-52 w-full rounded-[1.5rem] object-cover">
            <p class="mt-4 text-2xl font-semibold text-stone-950">{{ $product->nombre }}</p>
            <p class="mt-2 text-stone-600">{{ $product->categoria_nombre ?: 'Sin categoria' }}</p>
            <p class="mt-3 text-xl font-semibold text-[var(--color-secondary)]">S/ {{ number_format($product->precio, 2) }}</p>
            <div class="mt-5 flex flex-col gap-3">
                <form action="{{ route('web.admin.products.featured', $product->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="destacado_actual" value="{{ !empty($product->destacado) ? 1 : 0 }}">
                    <button class="btn btn-outline-secondary w-full justify-center">{{ !empty($product->destacado) ? 'Quitar destacado' : 'Marcar destacado' }}</button>
                </form>
                <form action="{{ route('web.admin.products.delete', $product->id) }}" method="POST">
                    @csrf
                    <button class="btn btn-primary w-full justify-center">Desactivar producto</button>
                </form>
            </div>
        </article>
    </section>
@endsection
