@extends('layouts.storefront', ['title' => 'Categorías'])

@section('content')
    <section class="page-hero">
        <div class="max-w-3xl">
            <span class="eyebrow">Categorías</span>
            <h2 class="headline mt-4">Explora todas las categorías de nuestra panadería y descubre lo mejor de cada sección.</h2>
            <p class="subheadline mt-4">
                Recorre panes, postres, tortas y especialidades con una presentacion clara y atractiva.
            </p>
        </div>
    </section>

    <section class="section-space">
        @if ($categories->isEmpty())
            <div class="empty-state">
                <h3 class="text-xl font-semibold text-stone-900">No hay categorias disponibles.</h3>
                <p class="mt-2 text-sm text-stone-600">Cuando agregues categorías en el sistema, aparecerán aquí automáticamente.</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($categories as $category)
                    <article class="product-card">
                        <div class="relative aspect-square overflow-hidden">
                            <img src="{{ $category->imagen_url }}" alt="{{ $category->nombre }}" class="h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-black/10 to-transparent"></div>
                            <div class="absolute inset-x-0 bottom-0 p-4">
                                <span class="inline-flex rounded-xl bg-white/85 px-3 py-1 text-sm font-semibold text-stone-900 shadow-sm backdrop-blur-sm">
                                    {{ $category->nombre }}
                                </span>
                            </div>
                        </div>
                        <div class="p-5">
                            <p class="text-sm leading-6 text-stone-600">{{ $category->descripcion }}</p>
                            <a href="{{ route('web.products', ['categoria' => $category->id]) }}" class="btn btn-outline-secondary mt-4">Ver productos</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
