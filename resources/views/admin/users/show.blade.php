@extends('layouts.admin', ['title' => 'Detalle de usuario'])

@section('content')
    <section class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <article class="admin-card">
            <form action="{{ route('web.admin.users.update', $user->id) }}" method="POST" class="grid gap-4 md:grid-cols-2">
                @csrf
                @method('PATCH')
                <div>
                    <label class="label" for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" value="{{ old('nombre', $user->nombre) }}" class="input">
                </div>
                <div>
                    <label class="label" for="apellido">Apellido</label>
                    <input id="apellido" name="apellido" value="{{ old('apellido', $user->apellido) }}" class="input">
                </div>
                <div class="md:col-span-2">
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" value="{{ old('email', $user->email) }}" class="input">
                </div>
                <div>
                    <label class="label" for="telefono">Telefono</label>
                    <input id="telefono" name="telefono" value="{{ old('telefono', $user->telefono) }}" class="input">
                </div>
                <div>
                    <label class="label" for="numero_casa">Numero de casa</label>
                    <input id="numero_casa" name="numero_casa" value="{{ old('numero_casa', $user->numero_casa) }}" class="input">
                </div>
                <div>
                    <label class="label" for="distrito">Distrito</label>
                    <input id="distrito" name="distrito" value="{{ old('distrito', $user->distrito) }}" class="input">
                </div>
                <div class="md:col-span-2">
                    <label class="label" for="direccion">Direccion</label>
                    <input id="direccion" name="direccion" value="{{ old('direccion', $user->direccion) }}" class="input">
                </div>
                <button class="btn btn-primary md:col-span-2 w-fit">Guardar cambios</button>
            </form>
        </article>

        <article class="admin-card">
            <p class="text-sm text-stone-500">Resumen</p>
            <h3 class="mt-2 text-2xl font-semibold text-stone-950">{{ $user->nombre }} {{ $user->apellido }}</h3>
            <div class="mt-5 space-y-3 text-sm text-stone-600">
                <p>Total pedidos: <strong class="text-stone-900">{{ $stats['total_pedidos'] }}</strong></p>
                <p>Total gastado: <strong class="text-stone-900">S/ {{ number_format($stats['total_gastado'], 2) }}</strong></p>
                <p>Estado: <strong class="text-stone-900">{{ !empty($user->activo) ? 'Activo' : 'Inactivo' }}</strong></p>
            </div>
            <form action="{{ route('web.admin.users.toggle', $user->id) }}" method="POST" class="mt-6">
                @csrf
                <button class="btn btn-outline-secondary w-full justify-center">{{ !empty($user->activo) ? 'Desactivar usuario' : 'Activar usuario' }}</button>
            </form>
        </article>
    </section>
@endsection
