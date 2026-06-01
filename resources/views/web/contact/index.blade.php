@extends('layouts.storefront', ['title' => 'Contacto'])

@section('content')
    <section class="page-hero">
        <div class="max-w-3xl">
            <span class="eyebrow">Contacto</span>
            <h2 class="headline mt-4">Cuentanos lo que necesitas y te ayudamos con tus pedidos, consultas y celebraciones.</h2>
            <p class="subheadline mt-4">
                Estamos listos para atenderte con tortas personalizadas, pedidos especiales y cualquier consulta sobre la panaderia.
            </p>
        </div>
    </section>

    <section class="section-space grid gap-6 lg:grid-cols-[1fr_0.9fr]">
        <div class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
            <h3 class="section-title text-2xl">Escribenos</h3>
            <p class="subheadline mt-3">Atendemos pedidos especiales, consultas de productos y coordinaciones para tortas o eventos.</p>

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
                        <label for="telefono" class="label">Telefono</label>
                        <input id="telefono" name="telefono" type="tel" value="{{ old('telefono') }}" required class="input" placeholder="9XXXXXXXX">
                        @error('telefono')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="mensaje" class="label">Mensaje</label>
                    <textarea id="mensaje" name="mensaje" rows="6" required class="input min-h-36" placeholder="Cuentanos que necesitas">{{ old('mensaje') }}</textarea>
                    @error('mensaje')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Enviar mensaje</button>
            </form>
        </div>

        <div class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
            <h3 class="section-title text-2xl">Informacion de contacto</h3>
            <div class="mt-5 space-y-3 text-sm leading-7 text-stone-700">
                <p><strong>Direccion:</strong> Jr. Parra del Riego #164, El Tambo, Huancayo</p>
                <p><strong>Telefono:</strong> 993560096</p>
                <p><strong>Correo:</strong> contacto@delicias.com</p>
                <p><strong>Horario:</strong> Lunes a Domingo, 7:00 AM - 9:00 PM</p>
            </div>

            <div class="map-card mt-6">
                <iframe
                    title="Ubicacion Delicias"
                    src="https://www.google.com/maps?q=Jr.+Parra+del+Riego+164,+El+Tambo,+Huancayo&output=embed"
                    width="100%"
                    height="280"
                    style="border:0"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
            </div>
        </div>
    </section>
@endsection
