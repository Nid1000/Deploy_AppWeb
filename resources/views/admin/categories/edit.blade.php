@extends('layouts.admin', ['title' => 'Editar categoria'])

@section('content')
    <section class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <article class="admin-card">
            <form action="{{ route('web.admin.categories.update', $category->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label for="nombre" class="label">Nombre</label>
                    <input id="nombre" name="nombre" value="{{ old('nombre', $category->nombre) }}" required class="input">
                </div>
                <div>
                    <label for="descripcion" class="label">Descripcion</label>
                    <textarea id="descripcion" name="descripcion" rows="5" class="input min-h-36">{{ old('descripcion', $category->descripcion) }}</textarea>
                </div>
                <div>
                    <label for="imagen" class="label">Cambiar imagen</label>
                    <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="input">
                </div>
                <button class="btn btn-primary">Guardar cambios</button>
            </form>
        </article>

        <article class="admin-card">
            <img src="{{ $category->imagen_url }}" alt="{{ $category->nombre }}" class="h-48 w-full rounded-[1.5rem] object-cover">
            <p class="mt-4 text-xl font-semibold text-stone-950">{{ $category->nombre }}</p>
            <p class="mt-2 text-sm text-stone-600">{{ $category->descripcion ?: 'Sin descripcion registrada.' }}</p>
            <div class="mt-5 flex flex-col gap-3">
                <form action="{{ route('web.admin.categories.toggle', $category->id) }}" method="POST">
                    @csrf
                    <button class="btn btn-outline-secondary w-full justify-center">{{ !empty($category->activo) ? 'Desactivar' : 'Activar' }}</button>
                </form>
                <form action="{{ route('web.admin.categories.delete', $category->id) }}" method="POST">
                    @csrf
                    <button class="btn btn-primary w-full justify-center">Desactivar categoria</button>
                </form>
            </div>
        </article>
    </section>
@endsection
