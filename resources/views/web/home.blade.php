@extends('layouts.storefront', ['title' => 'Delicias'])

@section('content')
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="relative grid gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-end">
            <div class="max-w-2xl">
                <span class="eyebrow">Recien horneado, hecho para ti</span>
                <h2 class="mt-5 text-2xl font-semibold leading-tight text-[var(--color-secondary)] sm:text-3xl" style="font-family: 'Poppins', var(--font-sans);">
                    Panaderia artesanal con una vitrina mas cuidada para lucir cada producto de la casa.
                </h2>
                <p class="subheadline mt-5">
                    Descubre panes crujientes, dulces irresistibles y tortas personalizadas con una experiencia web mas elegante, clara y lista para vender mejor.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('web.products') }}" class="btn btn-primary">Ver menú</a>
                    <a href="#contacto" class="btn btn-outline-secondary">Pedir ahora</a>
                </div>

                <div class="hero-panel">
                    <div class="hero-stat">
                        <p class="hero-stat-value">{{ $featuredProducts->count() }}</p>
                        <p class="hero-stat-label">Favoritos destacados</p>
                    </div>
                    <div class="hero-stat">
                        <p class="hero-stat-value">{{ $homeCategories->count() }}</p>
                        <p class="hero-stat-label">Categorías visibles</p>
                    </div>
                    <div class="hero-stat">
                        <p class="hero-stat-value">7AM - 9PM</p>
                        <p class="hero-stat-label">Atencion diaria</p>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-[var(--color-secondary)]">Lo mas pedido</p>
                <div class="mt-5 space-y-4">
                    @forelse ($featuredProducts->take(3) as $product)
                        <div class="flex items-center gap-4 rounded-[1.5rem] border border-amber-100 bg-white/90 p-4 shadow-sm shadow-amber-100/30">
                            <img src="{{ $product->imagen_url }}" alt="{{ $product->nombre }}" class="h-18 w-18 rounded-[1.25rem] object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-semibold text-stone-900">{{ $product->nombre }}</p>
                                <p class="mt-1 text-sm text-stone-500">{{ $product->categoria_nombre ?: 'Especialidad de la casa' }}</p>
                            </div>
                            <span class="text-base font-semibold text-[var(--color-secondary)]">S/ {{ number_format($product->precio, 2) }}</span>
                        </div>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-amber-200 bg-white/80 px-4 py-6 text-sm text-stone-600">
                            Aun no hay destacados cargados desde el backend.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section id="categorias" class="section-space">
        <div class="max-w-2xl">
            <span class="eyebrow">Categorías</span>
            <h3 class="mt-3 text-xl font-semibold text-stone-950 sm:text-2xl" style="font-family: 'Poppins', var(--font-sans);">
                Tres clasicos de la casa para empezar.
            </h3>
            <p class="subheadline mt-3">Panes artesanales, dulces irresistibles y tortas personalizadas con identidad propia.</p>
        </div>
        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($homeCategories as $category)
                <article class="category-spotlight">
                    <div class="relative aspect-[4/3] overflow-hidden">
                        <img src="{{ $category->imagen_url }}" alt="{{ $category->nombre }}" class="h-full w-full object-cover">
                        <div class="absolute inset-x-0 bottom-0 z-10 p-5">
                            <h4 class="text-2xl font-semibold text-white">{{ $category->nombre }}</h4>
                        </div>
                    </div>
                    <div class="p-5">
                        <p class="mt-2 text-sm leading-6 text-stone-600">
                            {{ $category->descripcion ?: 'Productos artesanales preparados con ingredientes seleccionados y horneado diario.' }}
                        </p>
                        <a href="{{ route('web.products', ['categoria' => $category->id]) }}" class="btn btn-outline-secondary mt-4">Ver categoria</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section id="nosotros" class="section-space">
        <div class="section-card grid gap-8 lg:grid-cols-[1fr_0.95fr] lg:items-center">
            <div>
                <span class="eyebrow">Nosotros</span>
                <h3 class="mt-3 text-xl font-semibold text-stone-950 sm:text-2xl" style="font-family: 'Poppins', var(--font-sans);">
                    Sabores de siempre con una presentacion moderna.
                </h3>
                <p class="subheadline mt-4">
                    En Delicias horneamos cada dia con dedicacion y carino. Nuestros productos combinan recetas de familia,
                    ingredientes naturales y procesos artesanales para ofrecer sabores autenticos.
                </p>
                <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                    <li class="feature-item">Ingredientes seleccionados y de calidad</li>
                    <li class="feature-item">Recetas tradicionales con toques modernos</li>
                    <li class="feature-item">Horneado diario para asegurar frescura</li>
                    <li class="feature-item">Hecho con carino por nuestro equipo</li>
                </ul>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('web.products') }}" class="btn btn-outline-secondary">Ver el menú</a>
                    <a href="#contacto" class="btn btn-primary">Contacto</a>
                </div>
            </div>

            <div class="overflow-hidden rounded-[2rem] border border-amber-200 bg-white/80 shadow-xl shadow-amber-100/50">
                <img src="{{ asset('images/illustrations/illustrations.png') }}" alt="Panaderia Delicias" class="h-full w-full object-cover">
            </div>
        </div>
    </section>

    <section id="visitanos" class="section-space">
        <div class="section-card grid gap-8 lg:grid-cols-[0.95fr_1fr] lg:items-start">
            <div>
                <span class="eyebrow">Visitanos</span>
                <h3 class="mt-3 text-xl font-semibold text-stone-950 sm:text-2xl" style="font-family: 'Poppins', var(--font-sans);">
                    Ven por pan fresco, quedate por la experiencia.
                </h3>
                <p class="subheadline mt-4">
                    Panaderia Delicias, Jr. Parra del Riego #164, El Tambo, Huancayo. Atendemos todos los dias de 7:00 AM a 9:00 PM.
                </p>
                <div class="mt-4 space-y-2 text-sm text-stone-700">
                    <p><strong>Celular:</strong> 993560096</p>
                    <p><strong>Correo:</strong> contacto@delicias.com</p>
                </div>
                <div class="map-card mt-6">
                    <iframe
                        title="Ubicación Delicias"
                        src="https://www.google.com/maps?q=Jr.+Parra+del+Riego+164,+El+Tambo,+Huancayo&output=embed"
                        width="100%"
                        height="280"
                        style="border:0"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($galleryImages as $image)
                    <div class="gallery-card">
                        <img src="{{ $image }}" alt="Producto Delicias" class="aspect-square w-full object-cover transition duration-300 hover:scale-105">
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="destacados" class="section-space">
        <div class="max-w-2xl">
            <span class="eyebrow">Favoritos</span>
            <h3 class="mt-3 text-xl font-semibold text-stone-950 sm:text-2xl" style="font-family: 'Poppins', var(--font-sans);">
                Productos destacados.
            </h3>
            <p class="subheadline mt-3">Una seleccion de favoritos para antojarse y pedir con mas facilidad.</p>
        </div>
        <div class="product-grid mt-8">
            @foreach ($featuredProducts as $product)
                @include('web.products.partials.card', ['product' => $product])
            @endforeach
        </div>
    </section>

    <section id="testimonios" class="section-space">
        <div class="max-w-2xl">
            <span class="eyebrow">Testimonios</span>
            <h3 class="mt-3 text-xl font-semibold text-stone-950 sm:text-2xl" style="font-family: 'Poppins', var(--font-sans);">
                Lo que dicen nuestros clientes.
            </h3>
        </div>
        <div class="mt-8 grid gap-5 lg:grid-cols-3">
            @foreach ($testimonials as $testimonial)
                <article class="testimonial-card">
                    <div class="text-[var(--color-primary)]">★★★★★</div>
                    <p class="mt-3 text-sm leading-7 text-stone-600">"{{ $testimonial['texto'] }}"</p>
                    <p class="mt-4 text-sm font-semibold text-stone-900">{{ $testimonial['nombre'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section id="contacto" class="section-space">
        <div class="contact-panel mx-auto max-w-2xl">
            <div class="text-center">
                <span class="eyebrow">Contacto</span>
                <h3 class="mt-3 text-xl font-semibold text-stone-950 sm:text-2xl" style="font-family: 'Poppins', var(--font-sans);">
                    Cuéntanos tu pedido especial.
                </h3>
                <p class="subheadline mt-3">
                    Escríbenos para pedidos especiales, tortas personalizadas o consultas sobre nuestros productos.
                </p>
            </div>

            <form action="{{ route('web.contact.submit') }}" method="POST" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label for="nombre" class="label">Nombre</label>
                    <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" required class="input" placeholder="Tu nombre">
                    @error('nombre')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="email" class="label">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="input" placeholder="tu@email.com">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="telefono" class="label">Teléfono</label>
                        <input id="telefono" name="telefono" type="tel" value="{{ old('telefono') }}" required class="input" placeholder="9XXXXXXXX">
                        @error('telefono')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="mensaje" class="label">Mensaje</label>
                    <textarea id="mensaje" name="mensaje" rows="5" required class="input min-h-36" placeholder="Cuéntanos qué necesitas">{{ old('mensaje') }}</textarea>
                    @error('mensaje')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-full justify-center">Enviar mensaje</button>
            </form>
        </div>
    </section>
@endsection
