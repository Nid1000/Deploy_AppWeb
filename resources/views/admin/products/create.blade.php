@extends('layouts.admin', ['title' => 'Nuevo producto'])

@section('content')
    <section class="admin-card max-w-4xl">
        <form action="{{ route('web.admin.products.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
            @csrf
            <div class="md:col-span-2">
                <label class="label" for="nombre">Nombre</label>
                <input id="nombre" name="nombre" value="{{ old('nombre') }}" required class="input">
            </div>
            <div>
                <label class="label" for="precio">Precio</label>
                <input id="precio" name="precio" type="number" step="0.01" min="0" value="{{ old('precio') }}" required class="input">
            </div>
            <div>
                <label class="label" for="stock">Stock</label>
                <input id="stock" name="stock" type="number" min="0" value="{{ old('stock', 0) }}" required class="input">
            </div>
            <div>
                <label class="label" for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id" required class="input">
                    <option value="">Selecciona</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('categoria_id') === (int) $category->id)>{{ $category->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="imagen">Imagen del producto</label>
                <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="input">
            </div>
            <div class="md:col-span-2">
                <label class="label" for="descripcion">Descripcion</label>
                <textarea id="descripcion" name="descripcion" rows="5" class="input min-h-36">{{ old('descripcion') }}</textarea>
            </div>
            <label class="checkbox-row md:col-span-2">
                <input type="checkbox" name="destacado" value="1" @checked(old('destacado'))>
                <span>Marcar como destacado</span>
            </label>
            <div class="md:col-span-2 flex gap-3">
                <button class="btn btn-primary">Guardar</button>
                <a href="{{ route('web.admin.products.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
