@extends('layouts.storefront', ['title' => 'Mi perfil'])

@section('content')
    <section class="page-hero">
        <div class="max-w-3xl">
            <span class="eyebrow">Mi cuenta</span>
            <h2 class="headline mt-4">Gestiona tu perfil, tus pedidos y la seguridad de tu cuenta en un solo lugar.</h2>
            <p class="subheadline mt-4">
                Pedidos realizados: {{ $stats['total_pedidos'] }}. Total gastado: S/ {{ number_format($stats['total_gastado'], 2) }}.
            </p>
        </div>
    </section>

    @include('web.profile.partials.account-nav')

    <section class="mt-8 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <p class="eyebrow">Datos personales</p>
                    <h3 class="mt-3 text-3xl font-semibold text-stone-900">{{ $user->nombre }} {{ $user->apellido }}</h3>
                </div>
                <a href="{{ route('web.orders') }}" class="btn btn-outline-secondary">Ver historial</a>
            </div>

            <form action="{{ route('web.profile.update') }}" method="POST" class="grid gap-4 md:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label for="nombre" class="label">Nombre</label>
                    <input id="nombre" name="nombre" type="text" value="{{ old('nombre', $user->nombre) }}" required class="input">
                </div>
                <div>
                    <label for="apellido" class="label">Apellido</label>
                    <input id="apellido" name="apellido" type="text" value="{{ old('apellido', $user->apellido) }}" required class="input">
                </div>
                <div class="md:col-span-2">
                    <label for="email" class="label">Email</label>
                    <input id="email" type="email" value="{{ $user->email }}" disabled class="input bg-stone-100">
                </div>
                <div>
                    <label for="telefono" class="label">Telefono</label>
                    <input id="telefono" name="telefono" type="tel" value="{{ old('telefono', $user->telefono) }}" class="input" placeholder="9XXXXXXXX">
                </div>
                <div>
                    <label for="numero_casa" class="label">Numero de casa</label>
                    <input id="numero_casa" name="numero_casa" type="text" value="{{ old('numero_casa', $user->numero_casa) }}" required class="input">
                </div>
                <div class="md:col-span-2">
                    <label for="direccion" class="label">Direccion</label>
                    <input id="direccion" name="direccion" type="text" value="{{ old('direccion', $user->direccion) }}" required class="input">
                </div>
                <div class="md:col-span-2">
                    <label for="distrito" class="label">Distrito</label>
                    <select id="distrito" name="distrito" required class="input">
                        @foreach ($distritos as $distrito)
                            <option value="{{ $distrito->nombre }}" @selected(old('distrito', $user->distrito) === $distrito->nombre)>{{ $distrito->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="btn btn-primary">Guardar perfil</button>
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <section class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
                <p class="eyebrow">Seguridad</p>
                <h3 class="mt-3 text-3xl font-semibold text-stone-900">Cambiar contrasena</h3>
                <p class="mt-2 text-sm text-stone-600">La nueva contrasena debe llevar mayuscula, minuscula, numero y minimo 6 caracteres.</p>

                <form action="{{ route('web.profile.password') }}" method="POST" class="mt-6 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="password_actual" class="label">Contrasena actual</label>
                        <input id="password_actual" name="password_actual" type="password" required class="input">
                    </div>
                    <div>
                        <label for="password_nueva" class="label">Nueva contrasena</label>
                        <input id="password_nueva" name="password_nueva" type="password" required class="input" placeholder="Ejemplo: Delicias1">
                    </div>
                    <div>
                        <label for="password_nueva_confirmation" class="label">Confirmar nueva contrasena</label>
                        <input id="password_nueva_confirmation" name="password_nueva_confirmation" type="password" required class="input">
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar contrasena</button>
                </form>
            </section>

            <section class="rounded-[2rem] border border-dashed border-amber-300 bg-amber-50 p-6">
                <p class="text-sm font-semibold text-stone-900">Accesos rapidos</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('web.checkout') }}" class="btn btn-outline-secondary">Ir al checkout</a>
                    <a href="{{ route('web.orders') }}" class="btn btn-outline-secondary">Ver pedidos</a>
                </div>
            </section>
        </div>
    </section>
@endsection
